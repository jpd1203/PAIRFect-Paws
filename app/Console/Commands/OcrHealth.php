<?php

namespace App\Console\Commands;

use App\Contracts\GoogleAccessTokenProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OcrHealth extends Command
{
    protected $signature = 'ocr:health';

    protected $description = 'Check OCR configuration, Google authentication, and Vision availability without exposing credentials or personal data.';

    public function handle(GoogleAccessTokenProvider $tokens): int
    {
        $this->info('PAIRfect Paws OCR Health');
        $provider = (string) config('document_verification.provider');
        $configuredPath = trim((string) config('document_verification.google_application_credentials'));
        $absolutePath = str_starts_with($configuredPath, '/')
            || str_starts_with($configuredPath, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
        $path = $absolutePath ? $configuredPath : base_path($configuredPath);
        $credentialReadable = $configuredPath !== '' && is_file($path) && is_readable($path);

        $this->line('Provider: '.$provider);
        $this->line('Credential path configured: '.($configuredPath !== '' ? 'Yes' : 'No'));
        $this->line('Credential file exists: '.($configuredPath !== '' && is_file($path) ? 'Yes' : 'No'));
        $this->line('Credential file readable: '.($credentialReadable ? 'Yes' : 'No'));

        $authenticated = false;
        $visionAvailable = false;
        if ($provider === 'google_vision' && $credentialReadable) {
            try {
                $token = $tokens->accessToken();
                $authenticated = $token !== '';
                if ($authenticated) {
                    // Synthetic one-pixel PNG; no adopter document or personal information is sent.
                    $response = Http::withToken($token)->acceptJson()->timeout(10)
                        ->post('https://vision.googleapis.com/v1/images:annotate', [
                            'requests' => [[
                                'image' => ['content' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=='],
                                'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                            ]],
                        ]);
                    $visionAvailable = $response->successful()
                        && is_array($response->json('responses.0'))
                        && $response->json('responses.0.error') === null;
                }
            } catch (Throwable) {
                // Provider exceptions can contain sensitive URLs or tokens; never print them.
            }
        }

        $this->line('Google authentication: '.($authenticated ? 'PASS' : 'FAIL'));
        $this->line('Google Vision API: '.($visionAvailable ? 'PASS' : 'FAIL'));
        $this->newLine();
        $this->line('Minimum text length: '.config('document_verification.minimum_text_length'));
        $this->line('Minimum name similarity: '.round((float) config('document_verification.minimum_name_similarity') * 100).'%');
        $this->line('Minimum address similarity: '.round((float) config('document_verification.minimum_address_similarity') * 100).'%');
        $this->line('Minimum composite similarity: '.round((float) config('document_verification.minimum_match_score') * 100).'%');
        $this->newLine();

        if ($provider !== 'google_vision' || ! $credentialReadable || ! $authenticated || ! $visionAvailable) {
            $this->error('Result: CONFIGURATION OR PROVIDER ERROR');

            return self::FAILURE;
        }

        $this->info('Result: HEALTHY');

        return self::SUCCESS;
    }
}
