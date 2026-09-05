<?php

namespace App\Console\Commands;

use App\Jobs\SendCheckInReminder;
use App\Models\PostAdoptionLog;
use App\Services\EmailNotificationService;
use App\Services\FlagEvaluationService;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCheckinReminders extends Command
{
    protected $signature = 'checkins:send-reminders';

    protected $description = 'Create approved-adoption check-ins and queue due reminder deliveries.';

    public function handle(
        PostAdoptionScheduleService $scheduleService,
        FlagEvaluationService $flagService,
        EmailNotificationService $notifications,
    ): int {
        $now = CarbonImmutable::now(PostAdoptionScheduleService::TIMEZONE);
        $today = $now->toDateString();
        $startOfTodayUtc = $now->startOfDay()->utc();

        $created = $scheduleService->ensureForApprovedApplications();
        $this->info("Created {$created} missing check-in(s).");

        $backstopFlags = $flagService->flagMissedSubmissions();
        if ($backstopFlags > 0) {
            $notifications->staff(
                'Priority welfare reviews require attention',
                'Post-adoption check-ins were flagged',
                [
                    "{$backstopFlags} incomplete check-in(s) reached the two-reminder threshold.",
                    'Sign in to the protected monitoring queue to review them.',
                ],
                'Open Flagged Monitoring',
                route('admin.monitoring.flagged'),
                false,
                'missed_checkin_backstop_alert',
            );
        }

        $queued = 0;
        $failed = 0;

        PostAdoptionLog::query()
            ->whereNull('submitted_date')
            ->where('reminders_sent', '<', 2)
            ->whereDate('scheduled_date', '<=', $today)
            ->where(function ($query) use ($startOfTodayUtc): void {
                $query->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<', $startOfTodayUtc);
            })
            ->orderBy('id')
            ->chunkById(100, function ($logs) use (&$queued, &$failed): void {
                foreach ($logs as $log) {
                    try {
                        SendCheckInReminder::dispatch($log->id);
                        $queued++;
                    } catch (Throwable $exception) {
                        $failed++;
                        Log::error('A post-adoption reminder job could not be queued.', [
                            'post_adoption_log_id' => $log->id,
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });

        $this->info("Queued {$queued} reminder delivery job(s); {$failed} queue attempt(s) failed; backstop-flagged {$backstopFlags} log(s).");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
