<?php

namespace App\Jobs;

use App\Mail\CheckInReminderMail;
use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;
use App\Services\EmailNotificationService;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCheckInReminder implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DELIVERY_BLOCK_REASON_CODE = 'reminder_delivery_blocked';

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $logId,
        public ?int $actorId = null,
        public ?string $customMessage = null,
    ) {
        $this->onQueue('emails')->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->logId.':'.CarbonImmutable::now(PostAdoptionScheduleService::TIMEZONE)->toDateString();
    }

    public function handle(EmailNotificationService $notifications): void
    {
        $now = CarbonImmutable::now(PostAdoptionScheduleService::TIMEZONE);
        $log = PostAdoptionLog::query()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->find($this->logId);

        if (! $log || ! $this->canDeliver($log, $now)) {
            return;
        }

        $adopter = $log->adoptionApplication?->user;
        if (! $adopter?->email || ! $adopter->hasVerifiedEmail()) {
            $blockType = $adopter?->email ? 'unverified_email' : 'missing_email';
            $newlyBlocked = $this->recordDeliveryBlock($now, $blockType);

            Log::warning('Post-adoption reminder skipped because the adopter has no verified email address.', [
                'post_adoption_log_id' => $this->logId,
                'block_type' => $blockType,
            ]);

            if (! $newlyBlocked) {
                return;
            }

            AuditLogService::log(
                $this->actorId,
                'Post-Adoption Reminder Delivery Blocked',
                'PostAdoptionLog',
                $log->id,
                $blockType === 'missing_email'
                    ? 'The adopter has no email address. One staff alert was queued.'
                    : 'The adopter email address is not verified. One staff alert was queued.'
            );

            $notifications->staff(
                "Reminder delivery blocked - check-in #{$log->id}",
                'A post-adoption reminder cannot be delivered',
                [
                    $blockType === 'missing_email'
                        ? "Check-in #{$log->id} cannot receive reminders because the adopter has no email address."
                        : "Check-in #{$log->id} cannot receive reminders because the adopter's email address is not verified.",
                    'Update the adopter account if needed, then ask the adopter to verify their email. Delivery will resume automatically after verification.',
                ],
                'Open Flagged Monitoring',
                route('admin.monitoring.flagged'),
                false,
                'checkin_reminder_delivery_blocked',
                $log->id,
            );

            return;
        }

        // The queue job owns the retry lifecycle. Send synchronously inside the
        // job, then record the reminder only after the SMTP relay accepts it.
        Mail::to($adopter)->sendNow(new CheckInReminderMail($log, $this->customMessage));

        $result = $this->recordSuccessfulDelivery($now);
        if (! $result['recorded']) {
            return;
        }

        AuditLogService::log(
            $this->actorId,
            'Post-Adoption Reminder Sent',
            'PostAdoptionLog',
            $log->id,
            ($this->actorId ? 'Staff queued' : 'Scheduler queued')." reminder number {$result['count']}."
        );

        if (! $result['flagged']) {
            return;
        }

        AuditLogService::log(
            $this->actorId,
            'Post-Adoption Log Flagged (Missed Submission)',
            'PostAdoptionLog',
            $log->id,
            'Flagged after two successfully delivered reminders without a submission.'
        );

        $notifications->staff(
            "Priority welfare review - check-in #{$log->id}",
            'A post-adoption check-in needs priority review',
            [
                "Check-in #{$log->id} for application #{$log->application_id} remains incomplete after two delivered reminders.",
                'Sign in to the protected monitoring queue to review the case. Sensitive welfare details are not included in this email.',
            ],
            'Open Flagged Monitoring',
            route('admin.monitoring.flagged'),
            false,
            'missed_checkin_staff_alert',
            $log->id,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Post-adoption reminder exhausted all queue attempts.', [
            'post_adoption_log_id' => $this->logId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }

    private function canDeliver(PostAdoptionLog $log, CarbonImmutable $now): bool
    {
        return ($log->adoptionApplication?->hasCompletedHandover() ?? false)
            && $log->submitted_date === null
            && $log->reminders_sent < 2
            && ! $log->scheduled_date->gt($now->endOfDay())
            && ! ($log->last_reminder_sent_at
                ?->setTimezone(PostAdoptionScheduleService::TIMEZONE)
                ->isSameDay($now) ?? false);
    }

    /** @return array{recorded: bool, flagged: bool, count: int|null} */
    private function recordSuccessfulDelivery(CarbonImmutable $now): array
    {
        return DB::transaction(function () use ($now): array {
            $log = PostAdoptionLog::query()->lockForUpdate()->find($this->logId);

            if (! $log || ! $this->canDeliver($log, $now)) {
                return ['recorded' => false, 'flagged' => false, 'count' => null];
            }

            $remindersSent = $log->reminders_sent + 1;
            $newlyFlagged = $remindersSent >= 2;
            $updates = [
                'reminders_sent' => $remindersSent,
                'last_reminder_sent_at' => $now->utc(),
            ];

            if ($newlyFlagged) {
                $updates['is_flagged'] = true;
                $updates['resolved_at'] = null;
                $updates['resolved_by_user_id'] = null;
                $updates['resolution_note'] = null;
                $updates['flag_reasons'] = $this->appendMissedCheckInReason(
                    $log->flag_reasons,
                    $now,
                    $remindersSent,
                );
            }

            $deliveryResumed = $this->markDeliveryBlockResolved(
                $updates['flag_reasons'] ?? $log->flag_reasons,
                $now,
            );
            if ($deliveryResumed['resolved']) {
                $updates['flag_reasons'] = $deliveryResumed['reasons'];

                // Keep an existing/open welfare concern active. When this email
                // block was the only open reason, retain it as resolved history
                // without leaving the case in the staff priority queue.
                if (! $deliveryResumed['keep_open'] && $log->resolved_at === null) {
                    $updates['is_flagged'] = true;
                    $updates['resolved_at'] = $now->utc();
                    $updates['resolved_by_user_id'] = null;
                    $updates['resolution_note'] = 'Automatically resolved after a reminder was delivered to the adopter\'s verified email address.';
                }
            }

            $log->update($updates);

            return ['recorded' => true, 'flagged' => $newlyFlagged, 'count' => $remindersSent];
        });
    }

    private function recordDeliveryBlock(CarbonImmutable $now, string $blockType): bool
    {
        return DB::transaction(function () use ($now, $blockType): bool {
            $log = PostAdoptionLog::query()->lockForUpdate()->find($this->logId);

            if (! $log || ! $this->canDeliver($log, $now)) {
                return false;
            }

            $reasons = $this->normalizeFlagReasons($log->flag_reasons);
            $alreadyRecorded = collect($reasons)->contains(
                fn (array $reason): bool => ($reason['code'] ?? null) === self::DELIVERY_BLOCK_REASON_CODE
            );

            if ($alreadyRecorded) {
                return false;
            }

            $reasons[] = [
                'code' => self::DELIVERY_BLOCK_REASON_CODE,
                'message' => $blockType === 'missing_email'
                    ? 'Post-adoption reminders are blocked because the adopter has no email address. Add a valid address and have the adopter verify it.'
                    : 'Post-adoption reminders are blocked because the adopter email address is not verified. Ask the adopter to verify it.',
                'block_type' => $blockType,
                'flagged_at' => $now->toIso8601String(),
                'staff_alert_requested_at' => $now->toIso8601String(),
                'prior_reason_count' => count($reasons),
                'prior_flag_was_open' => $log->is_flagged && $log->resolved_at === null,
            ];

            $log->update([
                'is_flagged' => true,
                'flag_reasons' => array_values($reasons),
                'resolved_at' => null,
                'resolved_by_user_id' => null,
                'resolution_note' => null,
            ]);

            return true;
        });
    }

    /**
     * @param  array<mixed>|null  $reasons
     * @return array{resolved: bool, keep_open: bool, reasons: list<array<string, mixed>>}
     */
    private function markDeliveryBlockResolved(?array $reasons, CarbonImmutable $now): array
    {
        $normalized = $this->normalizeFlagReasons($reasons);
        $blockIndex = null;

        foreach ($normalized as $index => $reason) {
            if (($reason['code'] ?? null) === self::DELIVERY_BLOCK_REASON_CODE) {
                $blockIndex = $index;
                break;
            }
        }

        if ($blockIndex === null || isset($normalized[$blockIndex]['delivery_resumed_at'])) {
            return ['resolved' => false, 'keep_open' => false, 'reasons' => $normalized];
        }

        $block = $normalized[$blockIndex];
        $priorReasonCount = max(0, (int) ($block['prior_reason_count'] ?? 0));
        $otherReasonCount = max(0, count($normalized) - 1);
        $keepOpen = (bool) ($block['prior_flag_was_open'] ?? false)
            || $otherReasonCount > $priorReasonCount;

        $normalized[$blockIndex]['delivery_resumed_at'] = $now->toIso8601String();
        $normalized[$blockIndex]['message'] .= ' Delivery resumed after the verified address accepted a reminder.';

        return [
            'resolved' => true,
            'keep_open' => $keepOpen,
            'reasons' => array_values($normalized),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function normalizeFlagReasons(mixed $reasons): array
    {
        if (! is_array($reasons) || $reasons === []) {
            return [];
        }

        if (! array_is_list($reasons)) {
            $reasons = [$reasons];
        }

        return array_values(array_filter(array_map(function ($reason): ?array {
            if (is_array($reason)) {
                return $reason;
            }

            if (! is_scalar($reason) || trim((string) $reason) === '') {
                return null;
            }

            return [
                'code' => 'legacy_reason',
                'message' => trim((string) $reason),
            ];
        }, $reasons)));
    }

    /**
     * @param  array<mixed>|null  $reasons
     * @return list<mixed>
     */
    private function appendMissedCheckInReason(?array $reasons, CarbonImmutable $now, int $remindersSent): array
    {
        $reasons = $this->normalizeFlagReasons($reasons);

        $alreadyRecorded = collect($reasons)->contains(
            fn ($reason): bool => is_array($reason) && ($reason['code'] ?? null) === 'missed_check_in'
        );

        if (! $alreadyRecorded) {
            $reasons[] = [
                'code' => 'missed_check_in',
                'message' => 'The adopter did not submit this check-in after two reminders.',
                'reminders_sent' => $remindersSent,
                'flagged_at' => $now->toIso8601String(),
            ];
        }

        return array_values($reasons);
    }
}
