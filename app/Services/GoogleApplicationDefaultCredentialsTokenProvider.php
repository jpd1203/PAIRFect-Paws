<?php

namespace App\Services;

use App\Contracts\GoogleAccessTokenProvider;
use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GoogleApplicationDefaultCredentialsTokenProvider implements GoogleAccessTokenProvider
{
    public function accessToken(): string
    {
        $cacheKey = 'google-cloud-vision-adc-access-token';
        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $this->configureCredentialPath();

        $credentials = ApplicationDefaultCredentials::getCredentials(
            config('document_verification.google_vision_scope')
        );
        $token = $credentials->fetchAuthToken();
        $accessToken = (string) ($token['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Application Default Credentials did not return an access token.');
        }

        $expiresIn = max(60, (int) ($token['expires_in'] ?? 3600) - 60);
        Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn));

        return $accessToken;
    }

    private function configureCredentialPath(): void
    {
        $configuredPath = trim((string) config('document_verification.google_application_credentials'));
        if ($configuredPath === '') {
            return;
        }

        $path = $this->isAbsolutePath($configuredPath)
            ? $configuredPath
            : base_path($configuredPath);
        $resolvedPath = realpath($path);

        if ($resolvedPath === false || ! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
            throw new RuntimeException('The configured Google Application Credentials file is unavailable.');
        }

        putenv('GOOGLE_APPLICATION_CREDENTIALS='.$resolvedPath);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
