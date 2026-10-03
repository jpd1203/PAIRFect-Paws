<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    public function __construct(private readonly InAppNotificationService $inApp) {}

    /**
     * @param  list<string>  $lines
     */
    public function user(
        ?User $user,
        string $subject,
        string $heading,
        array $lines,
        ?string $actionText = null,
        ?string $actionUrl = null,
        string $event = 'transactional_email',
        ?int $entityId = null,
    ): void {
        if ($user && ! str_starts_with($event, 'handover_') && in_array($event, [
            'application_received', 'document_verified_adopter', 'document_followup_manual_review_adopter',
            'document_replacement_required', 'interview_assignment_staff', 'interview_reschedule_declined',
            'application_queue_closed',
        ], true)) {
            $this->inApp->user(
                $user, $event, $heading, $lines[0] ?? $heading, $actionUrl ?? route('notifications.index'),
                "{$event}:{$entityId}:".hash('sha256', implode('|', $lines)),
                'AdoptionApplication', $entityId,
            );
        }

        if (! $user?->email || ! $user->hasVerifiedEmail()) {
            return;
        }

        $this->queue($user, $subject, $heading, $lines, $actionText, $actionUrl, $event, $entityId);
    }

    /**
     * Queue a minimal operational alert to every active, verified staff account.
     *
     * @param  list<string>  $lines
     */
    public function staff(
        string $subject,
        string $heading,
        array $lines,
        ?string $actionText = null,
        ?string $actionUrl = null,
        bool $administratorsOnly = false,
        string $event = 'staff_email',
        ?int $entityId = null,
    ): void {
        $roles = $administratorsOnly
            ? [Role::Administrator->value]
            : [Role::Administrator->value, Role::Volunteer->value];

        $excludedAddresses = collect(config('mail.staff_notification_excluded_addresses', []))
            ->filter(fn ($email): bool => is_string($email) && filled($email))
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->all();

        $recipients = User::query()
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email')
            ->get()
            ->reject(fn (User $staff): bool => in_array(strtolower(trim($staff->email)), $excludedAddresses, true))
            ->mapWithKeys(fn (User $staff): array => [strtolower(trim($staff->email)) => $staff]);

        $alertAddress = strtolower(trim((string) config('mail.staff_alert_address')));
        if (filter_var($alertAddress, FILTER_VALIDATE_EMAIL) && ! $recipients->has($alertAddress)) {
            $recipients->put($alertAddress, $alertAddress);
        }

        if (in_array($event, [
            'application_received_staff', 'document_verified_staff', 'document_followup_manual_review_staff',
            'document_manual_review_staff', 'welfare_report_staff_alert', 'manual_welfare_flag_staff_alert',
            'missed_checkin_staff_alert', 'missed_checkin_backstop_alert', 'checkin_reminder_delivery_blocked',
            'reservation_queue_staff', 'handover_issue_staff', 'interview_reschedule_requested',
        ], true)) {
            $this->inApp->administrators(
                $event, $heading, $lines[0] ?? $heading, $actionUrl ?? route('notifications.index'),
                "{$event}:{$entityId}:".($entityId === null ? now('Asia/Manila')->toDateString().':' : '')
                    .hash('sha256', implode('|', $lines)),
                $entityId ? match (true) {
                    str_starts_with($event, 'handover_') => 'Handover',
                    str_contains($event, 'checkin'), str_contains($event, 'welfare') => 'PostAdoptionLog',
                    default => 'AdoptionApplication',
                } : null,
                $entityId,
            );
        }

        $recipients->each(function (User|string $recipient) use (
            $subject,
            $heading,
            $lines,
            $actionText,
            $actionUrl,
            $event,
            $entityId,
        ): void {
            $this->queue($recipient, $subject, $heading, $lines, $actionText, $actionUrl, $event, $entityId);
        });
    }

    /** @param list<string> $lines */
    private function queue(
        User|string $recipient,
        string $subject,
        string $heading,
        array $lines,
        ?string $actionText,
        ?string $actionUrl,
        string $event,
        ?int $entityId,
    ): void {
        try {
            Mail::to($recipient)->queue(new TransactionalMail(
                $subject,
                $heading,
                $lines,
                $actionText,
                $actionUrl,
            ));
        } catch (\Throwable $exception) {
            Log::error('Transactional email could not be queued.', [
                'event' => $event,
                'entity_id' => $entityId,
                'recipient_user_id' => $recipient instanceof User ? $recipient->id : null,
                'exception' => $exception::class,
            ]);
        }
    }
}
