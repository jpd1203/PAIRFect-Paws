<?php

namespace App\Services;

use App\Contracts\GoogleAccessTokenProvider;
use App\Enums\DocumentVerificationStatus;
use App\Support\PhilippineAddress;
use App\ValueObjects\DocumentVerificationResult;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentVerificationService
{
    public function __construct(private GoogleAccessTokenProvider $accessTokens) {}

    public function verify(
        string $disk,
        string $path,
        string $mimeType,
        array $applicant
    ): DocumentVerificationResult {
        if (config('document_verification.provider') !== 'google_vision') {
            return $this->manualReview('The configured OCR provider is unavailable. Shelter staff must review the document.');
        }

        try {
            $bytes = Storage::disk($disk)->get($path);
            if ($bytes === null) {
                return $this->manualReview('The uploaded document could not be read.');
            }

            $providerRequest = $this->providerRequest($bytes, $mimeType);
            if ($providerRequest === null) {
                return new DocumentVerificationResult(
                    DocumentVerificationStatus::NeedsResubmission,
                    null,
                    null,
                    0,
                    ['Please upload a clear PDF, JPG, or PNG document.'],
                );
            }

            // Tier 1 only: text extraction. ADC supplies an OAuth bearer token;
            // no API key, facial, biometric, or identity-comparison feature is used.
            $response = Http::withToken($this->accessTokens->accessToken())
                ->acceptJson()
                ->timeout(35)
                ->retry(
                    2,
                    250,
                    function (\Throwable $exception): bool {
                        if ($exception instanceof RequestException) {
                            $status = $exception->response->status();

                            return $status === 429 || $status >= 500;
                        }

                        return true;
                    },
                    throw: false,
                )
                ->post($providerRequest['endpoint'], $providerRequest['payload']);

            $providerError = $this->providerError($response, $providerRequest['response_prefix']);
            if (! $response->successful() || $providerError !== null) {
                Log::warning('Document OCR provider returned an error.', [
                    'status' => $response->status(),
                    'provider_code' => data_get($providerError, 'code'),
                    'provider_status' => data_get($providerError, 'status'),
                ]);

                return $this->providerFailure($response->status(), $providerError);
            }

            $text = trim((string) $response->json($providerRequest['response_prefix'].'.fullTextAnnotation.text'));
            if ($text === '') {
                $text = trim((string) $response->json($providerRequest['response_prefix'].'.textAnnotations.0.description'));
            }

            return $this->crossReference($text, $applicant);
        } catch (\Throwable $exception) {
            // Never log exception messages here: HTTP exceptions can contain credential-bearing URLs.
            Log::error('Document OCR failed before a provider response was available.', [
                'exception_class' => $exception::class,
            ]);

            return $this->manualReview('Automatic OCR could not process the document. Shelter staff will review it manually.');
        }
    }

    public function crossReference(string $text, array $applicant): DocumentVerificationResult
    {
        $normalizedText = $this->normalize($text);
        $minimumLength = config('document_verification.minimum_text_length', 25);

        if (mb_strlen($normalizedText) < $minimumLength) {
            return new DocumentVerificationResult(
                DocumentVerificationStatus::NeedsResubmission,
                $text ?: null,
                null,
                0,
                ['The document text is incomplete or unclear. Please upload a sharper, well-lit image.'],
            );
        }

        $documentType = $this->detectDocumentType($normalizedText);
        $firstNameScore = $this->textSimilarity($normalizedText, (string) ($applicant['first_name'] ?? ''));
        $lastNameScore = $this->textSimilarity($normalizedText, (string) ($applicant['last_name'] ?? ''));
        $nameScore = ($firstNameScore + $lastNameScore) / 2;
        $addressComparison = $this->compareApplicantAddress($text, $applicant);
        $addressScore = $addressComparison['score'];
        $typeScore = $documentType === null ? 0 : 1;
        $matchScore = round(($nameScore * 0.45) + ($addressScore * 0.45) + ($typeScore * 0.10), 4);

        $reasons = [];
        $minimumName = config('document_verification.minimum_name_similarity', 0.82);
        if ($firstNameScore < $minimumName || $lastNameScore < $minimumName) {
            $reasons[] = 'The name extracted from the document does not consistently match the application.';
        }
        if ($documentType === null) {
            $reasons[] = 'The document type could not be identified as a government ID or proof of address.';
        }
        if (! $addressComparison['consistent']) {
            $reasons[] = 'The address extracted from the document does not consistently match the application.';
        }
        if ($matchScore < config('document_verification.minimum_match_score', 0.72)) {
            $reasons[] = 'The extracted details do not provide a sufficiently consistent match.';
        }

        $status = $reasons === []
            ? DocumentVerificationStatus::Verified
            : DocumentVerificationStatus::NeedsResubmission;

        return new DocumentVerificationResult(
            $status,
            $text,
            null,
            $matchScore,
            array_values(array_unique($reasons)),
            $documentType,
        );
    }

    private function providerRequest(string $bytes, string $mimeType): ?array
    {
        if ($mimeType === 'application/pdf') {
            return [
                'endpoint' => 'https://vision.googleapis.com/v1/files:annotate',
                'payload' => [
                    'requests' => [[
                        'inputConfig' => [
                            'content' => base64_encode($bytes),
                            'mimeType' => 'application/pdf',
                        ],
                        'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                        // Identity/supporting documents should expose the relevant data on page one.
                        'pages' => [1],
                    ]],
                ],
                'response_prefix' => 'responses.0.responses.0',
            ];
        }

        if (! in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            return null;
        }

        return [
            'endpoint' => 'https://vision.googleapis.com/v1/images:annotate',
            'payload' => [
                'requests' => [[
                    'image' => ['content' => base64_encode($bytes)],
                    'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                ]],
            ],
            'response_prefix' => 'responses.0',
        ];
    }

    private function providerError(Response $response, string $responsePrefix): ?array
    {
        foreach (['error', 'responses.0.error', $responsePrefix.'.error'] as $path) {
            $error = $response->json($path);
            if (is_array($error) && $error !== []) {
                return $error;
            }
        }

        return null;
    }

    private function providerFailure(int $httpStatus, ?array $error): DocumentVerificationResult
    {
        $message = mb_strtolower((string) data_get($error, 'message', ''));
        $providerStatus = strtoupper((string) data_get($error, 'status', ''));

        $reason = match (true) {
            str_contains($message, 'billing') => 'Google Vision OCR did not run because billing is not enabled for its Cloud project. An administrator must enable billing, then retry OCR.',
            str_contains($message, 'has not been used') || str_contains($message, 'is disabled') => 'Google Vision OCR is not enabled for its Cloud project. An administrator must enable the Cloud Vision API, then retry OCR.',
            $httpStatus === 401 || $httpStatus === 403 || $providerStatus === 'PERMISSION_DENIED' => 'Google Vision OCR is not authorized. An administrator must review the Application Default Credentials, service-account IAM role, and Vision API access, then retry OCR.',
            $httpStatus === 429 || $providerStatus === 'RESOURCE_EXHAUSTED' => 'Google Vision OCR reached its usage limit. An administrator must review the project quota, then retry OCR.',
            default => 'Google Vision OCR could not process the document. Shelter staff must review it manually or retry OCR later.',
        };

        return $this->manualReview($reason);
    }

    private function detectDocumentType(string $text): ?string
    {
        $types = [
            'Passport' => ['passport', 'passeport'],
            'Driver License' => ['driver license', 'drivers license', 'land transportation office'],
            'Philippine National ID' => ['philippine identification card', 'philsys', 'national id'],
            'Government ID' => ['republic of the philippines', 'philhealth', 'sss', 'umid', 'voters id', 'postal id', 'identification card'],
            'Proof of Address' => ['proof of address', 'utility bill', 'billing statement', 'barangay certificate', 'statement of account'],
        ];

        foreach ($types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $type;
                }
            }
        }

        return null;
    }

    private function textSimilarity(string $haystack, string $value): float
    {
        $needle = $this->normalize($value);
        if ($needle === '') {
            return 0;
        }
        if (preg_match('/(?:^|\s)'.preg_quote($needle, '/').'(?:\s|$)/u', $haystack)) {
            return 1;
        }

        $best = 0.0;
        foreach (explode(' ', $haystack) as $token) {
            similar_text($needle, $token, $percent);
            $best = max($best, $percent / 100);
        }

        return round($best, 4);
    }

    private function compareApplicantAddress(string $text, array $applicant): array
    {
        if (! is_array($applicant['address_components'] ?? null)) {
            return $this->compareAddress($text, (string) ($applicant['address'] ?? ''));
        }

        $components = PhilippineAddress::normalize($applicant['address_components']);
        $coreAddress = PhilippineAddress::ocrCore($components);
        if ($coreAddress === null) {
            // Legacy applications retain their original free-text value in the
            // canonical address while their new PSGC columns remain empty.
            return $this->compareAddress($text, (string) ($applicant['address'] ?? ''));
        }

        $comparison = $this->compareAddress($text, $coreAddress);
        if ($comparison['consistent'] && $this->hasExplicitStructuredAddressConflict($text, $components)) {
            $comparison['consistent'] = false;
        }

        return $comparison;
    }

    /**
     * Optional PSGC divisions may be absent from an ID. They only become a hard
     * failure when OCR clearly labels a different value.
     */
    private function hasExplicitStructuredAddressConflict(string $text, array $components): bool
    {
        $labelPatterns = [
            'barangay' => '/\b(?:barangay|brgy|bgy)\.?\s*[:#-]?\s*([^\r\n,;]+)/iu',
            'province' => '/\bprovince(?:\s+of)?\s*[:#-]?\s*([^\r\n,;]+)/iu',
            'region' => '/\bregion\s*[:#-]?\s*([^\r\n,;]+)/iu',
        ];

        foreach ($labelPatterns as $component => $pattern) {
            $expected = (string) ($components[$component] ?? '');
            $expectedTokens = $this->addressTokens($expected);
            if ($expectedTokens === [] || ! preg_match_all($pattern, $text, $matches)) {
                continue;
            }

            foreach ($matches[1] as $candidate) {
                $candidateTokens = $this->addressTokens((string) $candidate);
                if ($candidateTokens !== [] && array_intersect($expectedTokens, $candidateTokens) === []) {
                    return true;
                }
            }
        }

        $expectedZip = (string) ($components['zip_code'] ?? '');
        if ($expectedZip !== ''
            && preg_match_all('/\b(?:zip|postal(?:\s+code)?)\s*[:#-]?\s*(\d{4})\b/iu', $text, $zipMatches)) {
            foreach ($zipMatches[1] as $candidateZip) {
                if ((string) $candidateZip !== $expectedZip) {
                    return true;
                }
            }
        }

        return false;
    }

    private function compareAddress(string $text, string $address): array
    {
        $applicationTokens = $this->addressTokens($address);
        $applicationNumbers = array_values(array_filter($applicationTokens, 'ctype_digit'));
        $applicationWords = array_values(array_filter($applicationTokens, fn (string $token) => ! ctype_digit($token)));
        $applicationStructure = $this->addressStructure($address);

        // A locality-only or numeric-only value is not enough evidence for automatic verification.
        if ($applicationTokens === []
            || $applicationWords === []
            || ($applicationNumbers === [] && count($applicationWords) < 2)) {
            return ['score' => 0.0, 'consistent' => false];
        }

        $lines = array_values(array_filter(
            preg_split('/\R+/u', $text) ?: [],
            fn (string $line) => trim($line) !== ''
        ));
        if ($lines === []) {
            $lines = [$text];
        }

        $candidateWindows = [];
        foreach ($lines as $start => $_line) {
            $windowLines = [];
            for ($length = 1; $length <= 5 && isset($lines[$start + $length - 1]); $length++) {
                $windowLines[] = $lines[$start + $length - 1];
                $windowText = implode("\n", $windowLines);
                $candidateWindows[] = [
                    'text' => $windowText,
                    'tokens' => $this->addressTokens($windowText),
                ];
            }
        }

        $bestScore = 0.0;
        $bestConsistentScore = null;
        $minimumScore = (float) config('document_verification.minimum_address_similarity', 0.60);

        foreach ($candidateWindows as $candidateWindow) {
            $documentTokens = $candidateWindow['tokens'];
            $documentStructure = $this->addressStructure($candidateWindow['text']);
            $exactMatches = array_values(array_intersect($applicationTokens, $documentTokens));
            $matchedTokens = $exactMatches;

            // Permit one OCR-character error only for long words and only after another exact anchor matches.
            if ($exactMatches !== []) {
                foreach (array_diff($applicationWords, $matchedTokens) as $applicationWord) {
                    if (mb_strlen($applicationWord) < 6) {
                        continue;
                    }

                    foreach ($documentTokens as $documentToken) {
                        if (ctype_digit($documentToken) || abs(mb_strlen($applicationWord) - mb_strlen($documentToken)) > 1) {
                            continue;
                        }
                        if (levenshtein($applicationWord, $documentToken) <= 1) {
                            $matchedTokens[] = $applicationWord;
                            break;
                        }
                    }
                }
            }

            $matchedTokens = array_values(array_unique($matchedTokens));
            $matchedWords = array_values(array_intersect($applicationWords, $matchedTokens));
            $score = count($matchedTokens) / count($applicationTokens);
            $numbersConsistent = $applicationNumbers === []
                || array_diff($applicationNumbers, $documentTokens) === [];
            // Require two lexical anchors when available; a house number plus a city alone is insufficient.
            $minimumWordMatches = min(2, count($applicationWords));
            $documentStreetTokens = $documentStructure['has_street_marker']
                ? $documentStructure['street_core']
                : $documentTokens;
            $streetConsistent = $applicationStructure['street_core'] === []
                || array_intersect($applicationStructure['street_core'], $documentStreetTokens) !== [];
            $localityConsistent = $applicationStructure['locality'] === null
                || $applicationStructure['locality'] === $documentStructure['locality'];
            $districtConsistent = true;
            if ($applicationStructure['district'] !== [] && $documentStructure['has_street_marker']) {
                // An omitted district is acceptable, but a different named district is a hard conflict.
                $districtConsistent = $documentStructure['district'] === []
                    || array_diff($documentStructure['district'], $applicationStructure['district']) === [];
            }
            $consistent = $numbersConsistent
                && count($matchedWords) >= $minimumWordMatches
                && $streetConsistent
                && $localityConsistent
                && $districtConsistent
                && $score >= $minimumScore;

            $bestScore = max($bestScore, $score);
            if ($consistent) {
                $bestConsistentScore = max($bestConsistentScore ?? 0, $score);
            }
        }

        return [
            'score' => round($bestConsistentScore ?? $bestScore, 4),
            'consistent' => $bestConsistentScore !== null,
        ];
    }

    private function addressTokens(string $value): array
    {
        $tokens = [];
        foreach (explode(' ', $this->normalize($value)) as $token) {
            $token = $this->canonicalAddressToken($token);
            if ($token === '' || $this->isGenericAddressToken($token)) {
                continue;
            }
            if (! ctype_digit($token) && mb_strlen($token) < 3) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    private function addressStructure(string $value): array
    {
        $tokens = array_map(
            fn (string $token) => $this->canonicalAddressToken($token),
            array_values(array_filter(explode(' ', $this->normalize($value))))
        );
        $streetMarkers = ['street', 'st', 'road', 'rd', 'avenue', 'ave', 'extension', 'ext', 'highway', 'hwy', 'drive', 'dr', 'lane', 'ln', 'boulevard', 'blvd'];
        $markerIndex = null;
        foreach ($tokens as $index => $token) {
            if (in_array($token, $streetMarkers, true)) {
                $markerIndex = $index;
                break;
            }
        }

        $locality = null;
        $localityIndex = null;
        for ($index = count($tokens) - 1; $index >= 0; $index--) {
            $token = $tokens[$index];
            if (ctype_digit($token) || mb_strlen($token) < 3 || $this->isGenericAddressToken($token)) {
                continue;
            }
            $locality = $token;
            $localityIndex = $index;
            break;
        }

        $streetCore = [];
        if ($markerIndex !== null) {
            for ($index = $markerIndex - 1; $index >= 0 && count($streetCore) < 2; $index--) {
                $token = $tokens[$index];
                if (ctype_digit($token)) {
                    break;
                }
                if (mb_strlen($token) < 3 || $this->isGenericAddressToken($token)) {
                    continue;
                }
                array_unshift($streetCore, $token);
            }
        }

        $district = [];
        if ($markerIndex !== null && $localityIndex !== null && $localityIndex > $markerIndex) {
            for ($index = $markerIndex + 1; $index < $localityIndex; $index++) {
                $token = $tokens[$index];
                if (ctype_digit($token) || mb_strlen($token) < 3 || $this->isGenericAddressToken($token)) {
                    continue;
                }
                $district[] = $token;
            }
        }

        return [
            'has_street_marker' => $markerIndex !== null,
            'street_core' => array_values(array_unique($streetCore)),
            'district' => array_values(array_unique($district)),
            'locality' => $locality,
        ];
    }

    private function canonicalAddressToken(string $token): string
    {
        return match ($token) {
            'santa' => 'sta',
            'santo' => 'sto',
            default => $token,
        };
    }

    private function isGenericAddressToken(string $token): bool
    {
        return in_array($token, [
            'address', 'house', 'unit', 'block', 'blk', 'lot',
            'street', 'st', 'road', 'rd', 'avenue', 'ave', 'extension', 'ext',
            'highway', 'hwy', 'drive', 'dr', 'lane', 'ln', 'boulevard', 'blvd',
            'barangay', 'brgy', 'bgy', 'city', 'municipality', 'province',
            'subdivision', 'subd', 'village', 'sitio', 'purok',
            'metro', 'metropolitan', 'ncr', 'philippines', 'phl',
        ], true);
    }

    private function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($value)) ?: mb_strtolower($value);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    private function manualReview(string $reason): DocumentVerificationResult
    {
        return new DocumentVerificationResult(
            DocumentVerificationStatus::ManualReview,
            null,
            null,
            0,
            [$reason],
        );
    }
}
