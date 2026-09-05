<?php

namespace App\Services;

use App\Contracts\MediaVerifier;
use App\ValueObjects\MediaVerificationResult;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class MediaVerificationService implements MediaVerifier
{
    /** @var Closure(array<int, string>): Process */
    private Closure $processFactory;

    /** @var array<string, mixed> */
    private array $configuration;

    /**
     * The optional arguments make the policy and process boundary independently testable.
     * Laravel uses the configured defaults when it resolves this service normally.
     *
     * @param  array<string, mixed>|null  $configuration
     * @param  (Closure(array<int, string>): Process)|null  $processFactory
     */
    public function __construct(?array $configuration = null, ?Closure $processFactory = null)
    {
        $this->configuration = $configuration ?? (array) config('post_adoption.c2pa', []);
        $this->processFactory = $processFactory
            ?? static fn (array $command): Process => new Process($command);
    }

    /**
     * Verify an uploaded image before it is moved to permanent storage.
     *
     * The path must be an absolute path to an existing local file, such as the
     * value returned by UploadedFile::getRealPath().
     */
    public function verify(string $absolutePath): MediaVerificationResult
    {
        if (! $this->isAbsolutePath($absolutePath)) {
            return MediaVerificationResult::invalid('media_path_not_absolute');
        }

        $mediaPath = realpath($absolutePath);

        if ($mediaPath === false || ! is_file($mediaPath) || ! is_readable($mediaPath)) {
            return MediaVerificationResult::invalid('media_file_unreadable');
        }

        $binary = $this->configuredLocalFile('binary', executable: true);
        if ($binary === null) {
            return $this->toolUnavailable('binary_unavailable');
        }

        $settings = $this->configuredLocalFile('settings');
        if ($settings === null) {
            return $this->toolUnavailable('settings_unavailable');
        }

        $trustAnchors = $this->configuredLocalFile('trust_anchors');
        if ($trustAnchors === null) {
            return $this->toolUnavailable('trust_anchors_unavailable');
        }

        $timeout = filter_var(
            $this->configuration['timeout_seconds'] ?? 20,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($timeout === false) {
            return $this->toolUnavailable('invalid_timeout_configuration');
        }

        $command = [
            $binary,
            $mediaPath,
            '--settings',
            $settings,
            'trust',
            '--trust_anchors',
            basename($trustAnchors),
        ];

        try {
            $process = ($this->processFactory)($command);

            if (! $process instanceof Process) {
                return $this->toolUnavailable('invalid_process_factory');
            }

            $process->setWorkingDirectory(dirname($trustAnchors));
            $process->setTimeout((float) $timeout);
            $process->run();
        } catch (ProcessTimedOutException) {
            return $this->toolUnavailable('verification_timeout');
        } catch (Throwable $exception) {
            Log::error('C2PA verifier could not be started.', [
                'exception' => $exception::class,
            ]);

            return MediaVerificationResult::unavailable('verification_process_unavailable');
        }

        if (! $process->isSuccessful()) {
            $diagnostic = trim($process->getErrorOutput()."\n".$process->getOutput());

            // c2patool reports an ordinary unsigned browser image as exit 1
            // with this diagnostic. Absence of C2PA is not a tool outage.
            if (
                $process->getExitCode() === 1
                && preg_match('/\bno claim found\b/i', $diagnostic) === 1
            ) {
                return MediaVerificationResult::invalid('missing_active_manifest');
            }

            if ($process->getExitCode() === 1) {
                Log::notice('c2patool rejected C2PA data embedded in a welfare photo.');

                return MediaVerificationResult::invalid('c2pa_asset_rejected');
            }

            Log::error('C2PA verification process returned a failure status.', [
                'exit_code' => $process->getExitCode(),
            ]);

            return MediaVerificationResult::unavailable('verification_process_failed');
        }

        $output = $process->getOutput();
        $maximumOutputBytes = filter_var(
            $this->configuration['max_output_bytes'] ?? 2_097_152,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($maximumOutputBytes === false) {
            return $this->toolUnavailable('invalid_output_limit_configuration');
        }

        if (strlen($output) > $maximumOutputBytes) {
            return $this->toolUnavailable('verifier_output_too_large');
        }

        return $this->evaluateJson($output);
    }

    /**
     * Parse and evaluate c2patool's JSON stdout without executing the CLI.
     */
    public function evaluateJson(
        string $json,
        ?DateTimeImmutable $now = null,
    ): MediaVerificationResult {
        try {
            $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return MediaVerificationResult::unavailable('invalid_verifier_output');
        }

        if (! is_array($report)) {
            return MediaVerificationResult::unavailable('invalid_verifier_output');
        }

        return $this->evaluateReport($report, $now);
    }

    /**
     * Apply the PAIRfect Paws acceptance policy to a decoded c2patool report.
     *
     * @param  array<string, mixed>  $report
     */
    public function evaluateReport(
        array $report,
        ?DateTimeImmutable $now = null,
    ): MediaVerificationResult {
        $manifestId = $report['active_manifest'] ?? null;

        if (! is_string($manifestId) || trim($manifestId) === '') {
            return MediaVerificationResult::invalid('missing_active_manifest');
        }

        $manifests = $report['manifests'] ?? null;
        $manifest = is_array($manifests) ? ($manifests[$manifestId] ?? null) : null;

        if (! is_array($manifest)) {
            return MediaVerificationResult::invalid('missing_active_manifest', $manifestId);
        }

        // c2patool releases through 0.27.15 can incorrectly report a trusted
        // timestamp for v1 claims. Accept only the v2 claim format where the
        // timestamp trust result is covered by the verifier's corrected path.
        $claimVersion = $manifest['claim_version'] ?? null;
        if ($claimVersion !== 2 && $claimVersion !== '2') {
            return MediaVerificationResult::invalid('unsupported_claim_version', $manifestId);
        }

        $signature = $manifest['signature_info'] ?? null;
        if (! is_array($signature) || ! $this->hasNonEmptyString($signature, 'alg')) {
            return MediaVerificationResult::invalid('missing_signature', $manifestId);
        }

        if (! empty($report['validation_status'] ?? [])) {
            return MediaVerificationResult::invalid('validation_status_present', $manifestId);
        }

        if ($this->containsValidationFailure($report['validation_results'] ?? null)) {
            return MediaVerificationResult::invalid('validation_failure', $manifestId);
        }

        if (($report['validation_state'] ?? null) !== 'Trusted') {
            return MediaVerificationResult::invalid('manifest_not_trusted', $manifestId);
        }

        $rawSigningTime = $signature['time'] ?? null;
        $signingTime = is_string($rawSigningTime)
            ? $this->parseRfc3339Timestamp($rawSigningTime)
            : null;

        if ($signingTime === null) {
            return MediaVerificationResult::invalid('missing_or_invalid_signing_time', $manifestId);
        }

        if (($this->configuration['require_trusted_timestamp'] ?? true)
            && ! $this->hasTrustedTimestamp($report)) {
            return MediaVerificationResult::invalid(
                'trusted_timestamp_missing',
                $manifestId,
                $signingTime,
            );
        }

        $maximumAgeMinutes = filter_var(
            $this->configuration['max_signing_age_minutes'] ?? 15,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        $futureSkewSeconds = filter_var(
            $this->configuration['future_skew_seconds'] ?? 120,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );

        if ($maximumAgeMinutes === false || $futureSkewSeconds === false) {
            return MediaVerificationResult::unavailable('invalid_time_policy_configuration');
        }

        $now ??= new DateTimeImmutable('now');

        if ($signingTime > $now->modify("+{$futureSkewSeconds} seconds")) {
            return MediaVerificationResult::invalid(
                'signing_time_in_future',
                $manifestId,
                $signingTime,
            );
        }

        if ($signingTime < $now->modify("-{$maximumAgeMinutes} minutes")) {
            return MediaVerificationResult::invalid(
                'signing_time_too_old',
                $manifestId,
                $signingTime,
            );
        }

        if (! $this->hasDigitalCaptureAction($manifest)) {
            return MediaVerificationResult::invalid(
                'digital_capture_action_missing',
                $manifestId,
                $signingTime,
            );
        }

        return MediaVerificationResult::valid($manifestId, $signingTime);
    }

    private function toolUnavailable(string $reasonCode): MediaVerificationResult
    {
        Log::error('C2PA verifier is unavailable.', ['reason_code' => $reasonCode]);

        return MediaVerificationResult::unavailable($reasonCode);
    }

    private function configuredLocalFile(string $key, bool $executable = false): ?string
    {
        $configuredPath = $this->configuration[$key] ?? null;

        if (! is_string($configuredPath) || ! $this->isAbsolutePath($configuredPath)) {
            return null;
        }

        $path = realpath($configuredPath);
        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        if ($executable && PHP_OS_FAMILY !== 'Windows' && ! is_executable($path)) {
            return null;
        }

        return $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('~^(?:[a-zA-Z]:[\\\\/]|/|\\\\\\\\)~', $path) === 1;
    }

    /** @param array<string, mixed> $values */
    private function hasNonEmptyString(array $values, string $key): bool
    {
        return isset($values[$key])
            && is_string($values[$key])
            && trim($values[$key]) !== '';
    }

    private function containsValidationFailure(mixed $value, ?string $key = null): bool
    {
        if ($key !== null && strcasecmp($key, 'failure') === 0) {
            return ! empty($value);
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $childKey => $childValue) {
            if ($this->containsValidationFailure(
                $childValue,
                is_string($childKey) ? $childKey : null,
            )) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $report */
    private function hasTrustedTimestamp(array $report): bool
    {
        $successes = $report['validation_results']['activeManifest']['success'] ?? null;
        if (! is_array($successes)) {
            return false;
        }

        $codes = [];
        foreach ($successes as $success) {
            if (! is_array($success) || ! is_string($success['code'] ?? null)) {
                continue;
            }

            $codes[] = strtolower((string) preg_replace('/[^a-z]/i', '', $success['code']));
        }

        return in_array('timestamptrusted', $codes, true)
            && in_array('timestampvalidated', $codes, true);
    }

    /** @param array<string, mixed> $manifest */
    private function hasDigitalCaptureAction(array $manifest): bool
    {
        $assertions = $manifest['assertions'] ?? null;
        if (! is_array($assertions)) {
            return false;
        }

        foreach ($assertions as $assertion) {
            if (! is_array($assertion)) {
                continue;
            }

            $label = $assertion['label'] ?? null;
            if (! is_string($label) || ! str_starts_with(strtolower($label), 'c2pa.actions')) {
                continue;
            }

            $actions = $assertion['data']['actions'] ?? null;
            if (! is_array($actions)) {
                continue;
            }

            foreach ($actions as $action) {
                if (! is_array($action) || ($action['action'] ?? null) !== 'c2pa.created') {
                    continue;
                }

                $sourceTypes = [
                    $action['digitalSourceType'] ?? null,
                    $action['digital_source_type'] ?? null,
                    $action['parameters']['digitalSourceType'] ?? null,
                    $action['parameters']['digital_source_type'] ?? null,
                ];

                foreach ($sourceTypes as $sourceType) {
                    if (! is_string($sourceType)) {
                        continue;
                    }

                    $normalized = strtolower(rtrim(trim($sourceType), '/'));
                    if ($normalized === 'digitalcapture'
                        || str_ends_with($normalized, '/digitalcapture')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function parseRfc3339Timestamp(string $timestamp): ?DateTimeImmutable
    {
        if (preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D',
            $timestamp,
        ) !== 1) {
            return null;
        }

        try {
            return new DateTimeImmutable($timestamp);
        } catch (Throwable) {
            return null;
        }
    }
}
