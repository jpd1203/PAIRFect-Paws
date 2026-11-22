<?php

namespace App\Services;

use App\Enums\DocumentVerificationStatus;
use App\Support\PhilippineAddress;
use App\Support\ResearchMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ResearchDocumentVerification
{
    public const DISK = 'research-ocr';

    public const CONSENT_VERSION = 'study-ocr-v3';

    public function __construct(private DocumentVerificationService $verification) {}

    /** Persist only the outcome, never the file, original filename, OCR text or provider payload. */
    public function process(UploadedFile $file, array $applicant): array
    {
        abort_unless(ResearchMode::ready(), 404);
        $attributes = [
            'document_consent_at' => now(),
            'document_consent_version' => self::CONSENT_VERSION,
            'document_verification_status' => DocumentVerificationStatus::ManualReview,
            'document_verification_reasons' => ['Automatic document checking is unavailable. No document match was recorded.'],
        ];
        if (config('research.ocr_enabled') !== true || config('document_verification.provider') !== 'google_vision') {
            return $attributes;
        }

        $disk = Storage::disk(self::DISK);
        $path = Str::uuid().'.ocr';
        try {
            if (! $disk->putFileAs('', $file, $path)) {
                return $attributes;
            }
            $result = $this->verification->verify(self::DISK, $path, $file->getMimeType() ?? '', [
                'first_name' => $applicant['first_name'],
                'last_name' => $applicant['last_name'],
                ...PhilippineAddress::ocrPayload($applicant['address']),
            ]);

            return array_replace($attributes, [
                'document_verification_status' => $result->status,
                'document_verification_reasons' => match ($result->status) {
                    DocumentVerificationStatus::Verified => [],
                    DocumentVerificationStatus::NeedsResubmission => $result->reasons,
                    default => $attributes['document_verification_reasons'],
                },
            ]);
        } catch (\Throwable) {
            Log::warning('Research OCR could not process a temporary upload.');

            return $attributes;
        } finally {
            // A crash or filesystem failure is covered by the private-temp purge procedure.
            try {
                if ($disk->exists($path) && ! $disk->delete($path)) {
                    Log::warning('Research OCR temporary file cleanup failed.');
                }
            } catch (\Throwable) {
                Log::warning('Research OCR temporary file cleanup failed.');
            }
        }
    }
}
