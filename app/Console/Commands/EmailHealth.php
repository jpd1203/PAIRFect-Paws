<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Services\PostAdoptionClock;
use App\Services\PostAdoptionScheduleService;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EmailHealth extends Command
{
    protected $signature = 'email:health';

    protected $description = 'Inspect email, queue, scheduler, recipient, and reminder readiness without sending or changing data.';

    protected $help = 'This command is read-only. It exits nonzero only for configuration or infrastructure failures; queued failures and blocked reminders are reported as operational warnings.';

    public function handle(Schedule $schedule, PostAdoptionClock $clock): int
    {
        $failures = [];
        $warnings = [];

        $this->info('PAIRfect Paws email delivery health');
        $this->line('Read-only: no mail is sent and no records are changed.');
        $this->newLine();

        $this->reportMailConfiguration($failures);
        $this->reportQueue($failures, $warnings);
        $this->reportScheduler($schedule, $failures);
        $this->reportStaffRecipients($failures);
        $this->reportBlockedReminders($clock, $failures, $warnings);

        $failures = array_values(array_unique($failures));
        $warnings = array_values(array_unique($warnings));

        $this->newLine();
        foreach ($warnings as $warning) {
            $this->warn("WARN: {$warning}");
        }
        foreach ($failures as $failure) {
            $this->error("FAIL: {$failure}");
        }

        $this->newLine();
        $this->line('Exit policy: only configuration or infrastructure failures produce a nonzero exit code; existing failed jobs and blocked reminders do not.');

        if ($failures !== []) {
            $this->error('Result: CONFIGURATION ERROR');

            return self::FAILURE;
        }

        $this->info($warnings === [] ? 'Result: HEALTHY' : 'Result: READY WITH OPERATIONAL WARNINGS');

        return self::SUCCESS;
    }

    /** @param list<string> $failures */
    private function reportMailConfiguration(array &$failures): void
    {
        $mailer = trim((string) config('mail.default'));
        $mailerConfig = config("mail.mailers.{$mailer}");
        $transport = is_array($mailerConfig) ? trim((string) ($mailerConfig['transport'] ?? '')) : '';
        $smtp = (array) config('mail.mailers.smtp', []);
        $urlParts = $this->smtpUrlParts($smtp['url'] ?? null);
        $smtpHost = trim((string) ($urlParts['host'] ?? $smtp['host'] ?? ''));
        $smtpPort = $urlParts['port'] ?? $smtp['port'] ?? null;
        $usernameConfigured = filled($urlParts['user'] ?? $smtp['username'] ?? null);
        $passwordConfigured = filled($urlParts['pass'] ?? $smtp['password'] ?? null);
        $fromAddress = trim((string) config('mail.from.address'));
        $staffAlertAddress = trim((string) config('mail.staff_alert_address'));

        $this->section('Mail');
        $this->field('Mailer', $mailer !== '' ? $mailer : 'Not configured');
        $this->field('Transport', $transport !== '' ? $transport : 'Not configured');
        $this->field('SMTP host', $smtpHost !== '' ? $smtpHost : 'Not configured');
        $this->field('SMTP port', filled($smtpPort) ? (string) $smtpPort : 'Not configured');
        $this->field('SMTP username configured', $this->yesNo($usernameConfigured));
        $this->field('SMTP password configured', $this->yesNo($passwordConfigured));
        $this->field('From address', $fromAddress !== '' ? $fromAddress : 'Not configured');
        $this->field('Staff alert address', $staffAlertAddress !== '' ? $staffAlertAddress : 'Not configured');

        if ($mailer === '' || ! is_array($mailerConfig) || $transport === '') {
            $failures[] = 'The default mailer does not resolve to a configured transport.';
        }

        if ($transport === 'smtp') {
            if ($smtpHost === '') {
                $failures[] = 'The SMTP host is not configured.';
            }
            if (! is_numeric($smtpPort) || (int) $smtpPort < 1 || (int) $smtpPort > 65535) {
                $failures[] = 'The SMTP port is missing or invalid.';
            }
            if (! $usernameConfigured) {
                $failures[] = 'The SMTP username is not configured.';
            }
            if (! $passwordConfigured) {
                $failures[] = 'The SMTP password is not configured.';
            }
        }

        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $failures[] = 'The global from address is missing or invalid.';
        }

        if ($staffAlertAddress !== '' && ! filter_var($staffAlertAddress, FILTER_VALIDATE_EMAIL)) {
            $failures[] = 'The configured staff alert address is invalid.';
        }
    }

    /**
     * @param  list<string>  $failures
     * @param  list<string>  $warnings
     */
    private function reportQueue(array &$failures, array &$warnings): void
    {
        $connectionName = trim((string) config('queue.default'));
        $connectionConfig = config("queue.connections.{$connectionName}");
        $driver = is_array($connectionConfig) ? trim((string) ($connectionConfig['driver'] ?? '')) : '';

        $this->section('Queue');
        $this->field('Connection', $connectionName !== '' ? $connectionName : 'Not configured');
        $this->field('Driver', $driver !== '' ? $driver : 'Not configured');

        if ($connectionName === '' || ! is_array($connectionConfig) || $driver === '') {
            $failures[] = 'The default queue connection is not configured.';
            $this->field('Pending emails jobs', 'Unavailable');
            $this->field('Pending default jobs', 'Unavailable');
        } elseif ($driver === 'database') {
            $table = trim((string) ($connectionConfig['table'] ?? 'jobs'));
            $database = filled($connectionConfig['connection'] ?? null)
                ? (string) $connectionConfig['connection']
                : null;
            $defaultQueue = trim((string) ($connectionConfig['queue'] ?? 'default')) ?: 'default';

            try {
                $queueDatabase = $this->database($database);
                if (! $this->hasTable($database, $table)) {
                    $failures[] = "The database queue table [{$table}] does not exist.";
                    $this->field('Pending emails jobs', 'Unavailable');
                    $this->field("Pending {$defaultQueue} jobs", 'Unavailable');
                } else {
                    $this->field('Pending emails jobs', (string) $queueDatabase->table($table)->where('queue', 'emails')->count());
                    $this->field("Pending {$defaultQueue} jobs", (string) $queueDatabase->table($table)->where('queue', $defaultQueue)->count());
                }
            } catch (Throwable) {
                $failures[] = 'The database queue could not be inspected.';
                $this->field('Pending emails jobs', 'Unavailable');
                $this->field("Pending {$defaultQueue} jobs", 'Unavailable');
            }
        } else {
            $this->field('Pending emails jobs', "Unavailable for {$driver} driver");
            $this->field('Pending default jobs', "Unavailable for {$driver} driver");

            if ($driver === 'sync') {
                $warnings[] = 'The sync queue processes mail during the web request and is not durable.';
            } elseif ($driver === 'null') {
                $failures[] = 'The null queue driver discards queued email.';
            }
        }

        $failedConfig = (array) config('queue.failed', []);
        $failedDriver = trim((string) ($failedConfig['driver'] ?? ''));
        if (in_array($failedDriver, ['database', 'database-uuids'], true)) {
            $failedDatabase = filled($failedConfig['database'] ?? null)
                ? (string) $failedConfig['database']
                : null;
            $failedTable = trim((string) ($failedConfig['table'] ?? 'failed_jobs'));

            try {
                if (! $this->hasTable($failedDatabase, $failedTable)) {
                    $failures[] = "The failed jobs table [{$failedTable}] does not exist.";
                    $this->field('Failed jobs', 'Unavailable');
                } else {
                    $failedCount = $this->database($failedDatabase)->table($failedTable)->count();
                    $this->field('Failed jobs', (string) $failedCount);
                    if ($failedCount > 0) {
                        $warnings[] = "{$failedCount} failed queue job(s) require review.";
                    }
                }
            } catch (Throwable) {
                $failures[] = 'The failed jobs store could not be inspected.';
                $this->field('Failed jobs', 'Unavailable');
            }
        } else {
            $this->field('Failed jobs', $failedDriver === 'null' ? 'Not recorded' : 'Unavailable');
        }
    }

    /** @param list<string> $failures */
    private function reportScheduler(Schedule $schedule, array &$failures): void
    {
        $event = collect($schedule->events())->first(
            fn ($event): bool => str_contains((string) ($event->command ?? ''), 'checkins:send-reminders')
        );

        $this->section('Post-adoption scheduler');
        if (! $event) {
            $this->field('Reminder task registered', 'No');
            $this->field('Next due', 'Unavailable');
            $failures[] = 'The checkins:send-reminders scheduler task is not registered.';

            return;
        }

        $this->field('Reminder task registered', 'Yes');
        try {
            $nextDue = $event->nextRunDate()
                ->setTimezone(PostAdoptionScheduleService::TIMEZONE)
                ->format('Y-m-d H:i:s P');
            $this->field('Next due (Asia/Manila)', $nextDue);
        } catch (Throwable) {
            $this->field('Next due', 'Registered; date unavailable');
        }
    }

    /** @param list<string> $failures */
    private function reportStaffRecipients(array &$failures): void
    {
        $excluded = collect(config('mail.staff_notification_excluded_addresses', []))
            ->filter(fn ($email): bool => is_string($email) && filled($email))
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->unique()
            ->values();

        try {
            $staffRecipients = User::query()
                ->whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->whereNotNull('email')
                ->orderBy('email')
                ->pluck('email')
                ->map(fn (string $email): string => strtolower(trim($email)))
                ->reject(fn (string $email): bool => $excluded->contains($email))
                ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
                ->unique()
                ->values();
        } catch (Throwable) {
            $this->section('Staff delivery recipients');
            $this->field('Excluded placeholder addresses', $excluded->isEmpty() ? 'None' : $excluded->implode(', '));
            $this->field('Active verified staff accounts', 'Unavailable');
            $failures[] = 'Active staff delivery recipients could not be inspected.';

            return;
        }

        $staffAlertAddress = strtolower(trim((string) config('mail.staff_alert_address')));
        $effectiveRecipients = collect($staffRecipients->all());
        if (filter_var($staffAlertAddress, FILTER_VALIDATE_EMAIL)) {
            $effectiveRecipients = $effectiveRecipients->push($staffAlertAddress)->unique()->values();
        }

        $this->section('Staff delivery recipients');
        $this->field('Excluded placeholder addresses', $excluded->isEmpty() ? 'None' : $excluded->implode(', '));
        $this->field('Active verified staff accounts', $staffRecipients->isEmpty() ? 'None' : $staffRecipients->implode(', '));
        $this->field('Effective staff recipients', $effectiveRecipients->isEmpty() ? 'None' : $effectiveRecipients->implode(', '));

        if ($effectiveRecipients->isEmpty()) {
            $failures[] = 'No valid staff alert recipient is configured or active.';
        }
    }

    /**
     * @param  list<string>  $failures
     * @param  list<string>  $warnings
     */
    private function reportBlockedReminders(PostAdoptionClock $clock, array &$failures, array &$warnings): void
    {
        $this->section('Blocked post-adoption reminders');

        try {
            $blocked = PostAdoptionLog::query()
                ->afterCompletedHandover()
                ->with('adoptionApplication.user:id,email,email_verified_at')
                ->whereNull('submitted_date')
                ->whereDate('scheduled_date', '<=', $clock->today()->toDateString())
                ->whereHas('adoptionApplication.user', fn ($query) => $query->whereNull('email_verified_at'))
                ->orderBy('id')
                ->get();

            $this->field('Due logs with unverified adopters', (string) $blocked->count());
            $this->field(
                'Blocked log IDs',
                $blocked->isEmpty() ? 'None' : $blocked->pluck('id')->map(fn ($id): string => '#'.$id)->implode(', '),
            );

            if ($blocked->isNotEmpty()) {
                $warnings[] = $blocked->count().' due post-adoption reminder(s) are blocked because the adopter email is unverified.';
            }
        } catch (Throwable) {
            $this->field('Due logs with unverified adopters', 'Unavailable');
            $this->field('Blocked log IDs', 'Unavailable');
            $failures[] = 'Due post-adoption reminders could not be inspected.';
        }
    }

    /** @return array<string, mixed> */
    private function smtpUrlParts(mixed $url): array
    {
        if (! is_string($url) || trim($url) === '') {
            return [];
        }

        $parts = parse_url($url);

        return is_array($parts) ? $parts : [];
    }

    private function database(?string $connection): ConnectionInterface
    {
        return filled($connection) ? DB::connection($connection) : DB::connection();
    }

    private function hasTable(?string $connection, string $table): bool
    {
        $schema = filled($connection)
            ? Schema::connection($connection)
            : Schema::connection((string) config('database.default'));

        return $schema->hasTable($table);
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->comment($title);
    }

    private function field(string $label, string $value): void
    {
        $this->line("{$label}: {$value}");
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
