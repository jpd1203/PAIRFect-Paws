<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

final class ValidateProductionRelease extends Command
{
    protected $signature = 'release:check';

    protected $description = 'Validate required production configuration without printing secrets.';

    public function handle(): int
    {
        $errors = [];

        if (! app()->isProduction()) {
            $errors[] = 'APP_ENV must be production.';
        }
        if (config('app.debug')) {
            $errors[] = 'APP_DEBUG must be false.';
        }

        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https'
            || ! is_string($host)
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || preg_match('/(^localhost$|\.localhost$|\.test$|\.example$|^example\.(com|org|net)$|ngrok)/i', $host)) {
            $errors[] = 'APP_URL must be a real HTTPS production hostname.';
        }

        if (config('post_adoption.time_travel.enabled')) {
            $errors[] = 'POST_ADOPTION_TIME_TRAVEL_ENABLED must be false.';
        }
        if (! filter_var(config('release.privacy_contact_email'), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'PRIVACY_CONTACT_EMAIL must be configured with a valid address.';
        }

        $probePath = (string) config('post_adoption.capture.ffprobe_path');
        $absoluteProbePath = str_starts_with($probePath, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $probePath) === 1;
        $probe = $probePath !== '' ? realpath($probePath) : false;
        if (! $absoluteProbePath || $probe === false || ! is_file($probe) || ! is_executable($probe)) {
            $errors[] = 'FFPROBE_PATH must point to an executable ffprobe binary.';
        } else {
            try {
                $process = new Process([$probe, '-version']);
                $process->setTimeout(5);
                $process->run();
                if (! $process->isSuccessful() || ! str_contains(strtolower($process->getOutput()), 'ffprobe version')) {
                    $errors[] = 'FFPROBE_PATH did not run a valid ffprobe binary.';
                }
            } catch (Throwable) {
                $errors[] = 'FFPROBE_PATH could not be executed.';
            }
        }

        try {
            if (! Schema::hasColumn('users', 'phone_number')
                || ! Schema::hasColumn('adoption_applications', 'knn_distance')) {
                $errors[] = 'Required database migrations have not been applied.';
            }
            if (Schema::hasTable('pets') && DB::table('pets')
                ->where('description', 'like', 'Demo pet record for PAIRfect Paws testing.%')
                ->exists()) {
                $errors[] = 'Synthetic pet records must be removed or replaced before release.';
            }
        } catch (Throwable) {
            $errors[] = 'Database availability and migration status could not be verified.';
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        if ($errors !== []) {
            return self::FAILURE;
        }

        $this->info('Production configuration checks passed. External services still require live verification.');

        return self::SUCCESS;
    }
}
