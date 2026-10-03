<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Log;
use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class VideoDurationProbe
{
    /** @var Closure(array<int, string>): Process */
    private Closure $processFactory;

    /**
     * @param  (Closure(array<int, string>): Process)|null  $processFactory
     */
    public function __construct(?Closure $processFactory = null)
    {
        $this->processFactory = $processFactory
            ?? static fn (array $command): Process => new Process($command);
    }

    /**
     * Verify the container signature and, when ffprobe is configured, obtain
     * duration from the uploaded media itself rather than trusting JavaScript.
     *
     * @return array{status: 'verified'|'probe_unavailable'|'invalid_container'|'invalid_video_stream'|'invalid_duration', duration_ms: int|null}
     */
    public function inspect(string $absolutePath, string $mimeType): array
    {
        $mediaPath = realpath($absolutePath);

        if (
            $mediaPath === false
            || ! is_file($mediaPath)
            || ! is_readable($mediaPath)
            || ! $this->containerMatchesMime($mediaPath, $mimeType)
        ) {
            return ['status' => 'invalid_container', 'duration_ms' => null];
        }

        $binary = $this->configuredBinary();

        if ($binary === null) {
            return ['status' => 'probe_unavailable', 'duration_ms' => null];
        }

        try {
            $process = ($this->processFactory)([
                $binary,
                '-v',
                'error',
                '-protocol_whitelist',
                'file',
                '-show_entries',
                'stream=codec_type:format=duration',
                '-of',
                'json',
                $mediaPath,
            ]);

            if (! $process instanceof Process) {
                return ['status' => 'probe_unavailable', 'duration_ms' => null];
            }

            $timeout = max(
                1,
                (int) config('post_adoption.capture.ffprobe_timeout_seconds', 5),
            );
            $process->setTimeout((float) $timeout);
            $process->run();
        } catch (ProcessTimedOutException) {
            Log::warning('Post-adoption video duration probing timed out.');

            return ['status' => 'probe_unavailable', 'duration_ms' => null];
        } catch (Throwable $exception) {
            Log::warning('Post-adoption video duration probing could not be started.', [
                'exception' => $exception::class,
            ]);

            return ['status' => 'probe_unavailable', 'duration_ms' => null];
        }

        if (! $process->isSuccessful()) {
            return ['status' => 'invalid_duration', 'duration_ms' => null];
        }

        $output = $process->getOutput();

        if (strlen($output) > 65_536) {
            return ['status' => 'invalid_duration', 'duration_ms' => null];
        }

        try {
            $probe = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['status' => 'invalid_duration', 'duration_ms' => null];
        }

        $hasVideoStream = is_array($probe)
            && is_array($probe['streams'] ?? null)
            && collect($probe['streams'])->contains(
                fn (mixed $stream): bool => is_array($stream)
                    && ($stream['codec_type'] ?? null) === 'video',
            );

        if (! $hasVideoStream) {
            return ['status' => 'invalid_video_stream', 'duration_ms' => null];
        }

        $durationSeconds = filter_var(
            is_array($probe['format'] ?? null) ? ($probe['format']['duration'] ?? null) : null,
            FILTER_VALIDATE_FLOAT,
        );

        if (
            $durationSeconds === false
            || ! is_finite((float) $durationSeconds)
            || $durationSeconds <= 0
        ) {
            return ['status' => 'invalid_duration', 'duration_ms' => null];
        }

        return [
            'status' => 'verified',
            'duration_ms' => max(1, (int) round($durationSeconds * 1000)),
        ];
    }

    private function containerMatchesMime(string $path, string $mimeType): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 16);
        } finally {
            fclose($handle);
        }

        if (! is_string($header)) {
            return false;
        }

        return match ($mimeType) {
            'video/webm' => str_starts_with($header, "\x1A\x45\xDF\xA3"),
            'video/mp4' => strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp',
            default => false,
        };
    }

    private function configuredBinary(): ?string
    {
        $configured = trim((string) config('post_adoption.capture.ffprobe_path', ''));

        if ($configured === '' || ! $this->isAbsolutePath($configured)) {
            Log::error('FFPROBE_PATH is missing or is not an absolute path; welfare video verification is unavailable.');
            return null;
        }

        $binary = realpath($configured);

        if (
            $binary === false
            || ! is_file($binary)
            || ! is_readable($binary)
            || ! is_executable($binary)
        ) {
            Log::error('The configured ffprobe binary is unavailable or not executable.');

            return null;
        }

        return $binary;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
