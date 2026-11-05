<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Mail\TransactionalMail;
use App\Models\Handover;
use App\Models\HandoverEmailOutbox;
use App\Models\User;
use App\Support\ManilaTime;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Durable handover mail intent; queued and transport-accepted are not delivered. */
class HandoverMailOutboxService
{
    public function stage(Handover $handover, string $kind, string $event, User|string $recipient, array $payload): HandoverEmailOutbox
    {
        $address = strtolower(trim($recipient instanceof User ? $recipient->email : $recipient));
        $audience = $recipient instanceof User ? ((int) $recipient->id === (int) $handover->user_id ? 'adopter' : 'staff') : 'alert';
        $row = HandoverEmailOutbox::firstOrCreate([
            'event_key' => hash('sha256', $event.'|'.$address),
        ], [
            'handover_id' => $handover->id, 'recipient_user_id' => $recipient instanceof User ? $recipient->id : null,
            'kind' => $kind, 'audience' => $audience, 'schedule_version' => (int) $handover->schedule_version,
            'release_attempt' => $handover->reopen_count ?? 0,
            'schedule_fingerprint' => $kind === 'delivery_pre_dispatch'
                ? app(HandoverDeliveryClearanceService::class)->notificationFingerprint($handover)
                : $this->fingerprint($handover),
            'payload' => ['recipient' => $address, ...$payload],
        ]);
        DB::afterCommit(fn () => $this->enqueue($row->id));

        return $row;
    }

    public function fingerprint(Handover $handover): string
    {
        // A pending reschedule increments the revision, but does not replace an agreement.
        return hash('sha256', json_encode([
            $handover->scheduled_method,
            $handover->scheduled_start_at?->toIso8601String(),
            $handover->scheduled_end_at?->toIso8601String(),
            $handover->schedule_confirmed_at?->toIso8601String(),
            $handover->schedule_confirmed_by_user_id,
        ]));
    }

    public function dispatchPending(): int
    {
        $count = 0;
        HandoverEmailOutbox::query()->whereNull('transport_accepted_at')->whereNull('suppressed_at')->whereNull('queued_at')
            ->where(fn ($query) => $query->whereNull('dispatch_lease_expires_at')->orWhere('dispatch_lease_expires_at', '<=', now()))
            ->chunkById(100, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    DB::afterCommit(function () use ($row, &$count): void {
                        $count += (int) $this->enqueue($row->id);
                    });
                }
            });

        return $count;
    }

    private function enqueue(int $id): bool
    {
        $token = null;
        try {
            $row = DB::transaction(function () use ($id): ?HandoverEmailOutbox {
                $current = HandoverEmailOutbox::query()->lockForUpdate()->find($id);
                if (! $current || $current->queued_at || $current->transport_accepted_at || $current->suppressed_at
                    || $current->dispatch_lease_expires_at?->isFuture()) {
                    return null;
                }
                // Commit this claim before touching a queue, including a synchronous queue.
                $current->update([
                    'enqueue_token' => (string) Str::uuid(), 'dispatch_lease_expires_at' => now()->addHour(),
                    'queue_attempts' => $current->queue_attempts + 1, 'queued_at' => null,
                ]);

                return $current;
            });
            if (! $row) {
                return false;
            }
            $token = $row->enqueue_token;
            $payload = $row->payload;
            Mail::to($payload['recipient'])->queue((new TransactionalMail(
                $payload['subject'], $payload['heading'], $payload['lines'], $payload['action_text'], $payload['action_url'],
            ))->forHandoverOutbox($row->id, $row->enqueue_token, $row->event_key));
            HandoverEmailOutbox::whereKey($id)->where('enqueue_token', $row->enqueue_token)
                ->update(['queued_at' => now(), 'last_queue_error' => null]);

            return true;
        } catch (Throwable $exception) {
            try {
                HandoverEmailOutbox::whereKey($id)->where('enqueue_token', $token)
                    ->whereNull('transport_accepted_at')->whereNull('queued_at')->update([
                        'enqueue_token' => null, 'dispatch_lease_expires_at' => null,
                        'last_queue_error' => $exception::class,
                    ]);
            } catch (Throwable $cleanupFailure) {
                // The committed intent survives even if the DB is briefly unavailable.
                Log::error('Handover outbox claim awaits lease recovery.', ['outbox_id' => $id, 'exception' => $cleanupFailure::class]);
            }
            Log::error('Handover email remains pending after queue failure.', ['outbox_id' => $id, 'exception' => $exception::class]);

            return false;
        }
    }

    public function deliver(int $id, string $token, Closure $send): mixed
    {
        $error = null;
        $result = DB::transaction(function () use ($id, $token, $send, &$error): mixed {
            $candidate = HandoverEmailOutbox::find($id);
            if (! $candidate) {
                return null;
            }
            // Serialize against schedule/release/receipt mutations and other mail workers.
            $handover = Handover::query()->lockForUpdate()->find($candidate->handover_id);
            $row = HandoverEmailOutbox::query()->lockForUpdate()->find($id);
            if (! $handover || ! $row || $row->enqueue_token !== $token || $row->transport_accepted_at || $row->suppressed_at) {
                return null;
            }
            $reason = $this->obsoleteReason($handover, $row);
            if ($reason !== null) {
                $row->update(['suppressed_at' => now(), 'suppression_reason' => $reason]);

                return null;
            }
            $row->delivery_attempts++;
            try {
                $result = $send();
                if ($result === null || $result === false) {
                    throw new RuntimeException('The mail transport did not accept the message.');
                }
                $row->transport_accepted_at = now();
                $row->last_transport_error = null;
            } catch (Throwable $exception) {
                // Persist the attempt, then rethrow only after this transaction commits.
                $row->last_transport_error = $exception::class;
                $error = $exception;
                $result = null;
            }
            $row->save();

            return $result;
        });
        if ($error) {
            // Do not let SMTP exception messages expose recipient addresses in worker logs.
            throw new RuntimeException('Handover email transport failed: '.$error::class);
        }

        return $result;
    }

    public function failed(int $id, string $token, Throwable $exception): void
    {
        HandoverEmailOutbox::whereKey($id)->where('enqueue_token', $token)
            ->whereNull('transport_accepted_at')->whereNull('suppressed_at')->update([
                'enqueue_token' => null, 'dispatch_lease_expires_at' => null, 'queued_at' => null,
                'last_transport_error' => $exception::class,
            ]);
    }

    private function obsoleteReason(Handover $handover, HandoverEmailOutbox $row): ?string
    {
        // Investigations remain actionable across reopened release attempts.
        if ($row->kind !== 'transport_case_attention' && ($handover->reopen_count ?? 0) !== $row->release_attempt) {
            return 'superseded_attempt';
        }
        $payload = $row->payload;
        if (! filter_var($payload['recipient'], FILTER_VALIDATE_EMAIL)) {
            return 'recipient_ineligible';
        }
        $excluded = array_map(fn ($address) => strtolower(trim($address)), config('mail.staff_notification_excluded_addresses', []));
        if ($row->audience === 'alert') {
            if (strtolower(trim((string) config('mail.staff_alert_address'))) !== $payload['recipient']
                || in_array($payload['recipient'], $excluded, true)) {
                return 'recipient_ineligible';
            }
        } else {
            $user = User::find($row->recipient_user_id);
            if (! $user?->is_active || ! $user->hasVerifiedEmail()
                || strtolower(trim($user->email)) !== $payload['recipient']
                || ($row->audience === 'adopter' ? ((int) $user->id !== (int) $handover->user_id || $user->role?->value !== 'Adopter') : (! $user->isStaff() || in_array($payload['recipient'], $excluded, true)))) {
                return 'recipient_ineligible';
            }
        }
        if ($row->kind === 'transport_case_attention') {
            return app(HandoverTransportIncidentService::class)->noticeIsCurrent($handover, $row) ? null : 'transport_case_changed';
        }
        if ($handover->application?->status !== ApplicationStatus::Approved) {
            return 'application_not_approved';
        }
        $kind = $row->kind;
        if ($kind === 'delivery_pre_dispatch') {
            $clearance = app(HandoverDeliveryClearanceService::class);
            if ($handover->released_at || $handover->adopter_outcome || $handover->received_at) {
                return 'dispatch_preparation_ended';
            }
            if (! $clearance->readinessIsCurrent($handover)
                || $handover->schedule_version !== $row->schedule_version
                || $clearance->notificationFingerprint($handover) !== $row->schedule_fingerprint
                || ManilaTime::now()->gt(ManilaTime::at($handover->scheduled_end_at))) {
                return 'delivery_clearance_changed';
            }

            return null;
        }
        $scheduleDependent = in_array($kind, ['24h_reminder', '2h_reminder', 'receipt_reminder', 'delivery_follow_up', 'missed_pickup', 'method_selected'], true)
            || str_starts_with($kind, 'schedule_') || str_starts_with($kind, 'reschedule_');
        if ($scheduleDependent && ($row->schedule_version > $handover->schedule_version || $this->fingerprint($handover) !== $row->schedule_fingerprint)) {
            return 'superseded_schedule';
        }
        if ($kind === 'completed') {
            return $handover->adopter_outcome === 'received' && $handover->received_at ? null : 'receipt_state_changed';
        }
        if ($kind === 'issue_logged') {
            if ($handover->transportIncidents()->where('release_attempt', $row->release_attempt)
                ->where('source', 'adopter_non_receipt')->where('status', 'resolved')->exists()) {
                return 'transport_case_resolved';
            }

            return $handover->adopter_outcome === 'not_received' ? null : 'receipt_state_changed';
        }
        if ($kind === 'delivery_follow_up' && $handover->transportIncidents()->where('release_attempt', $row->release_attempt)
            ->where('source', 'overdue_delivery')->where('status', 'resolved')->exists()) {
            return 'transport_case_resolved';
        }
        if ($handover->adopter_outcome || $handover->received_at) {
            return 'receipt_already_recorded';
        }
        $now = ManilaTime::now();
        $start = $handover->scheduled_start_at;
        $end = $handover->scheduled_end_at;
        if (in_array($kind, ['24h_reminder', '2h_reminder', 'receipt_reminder', 'delivery_follow_up', 'missed_pickup'], true)) {
            if ($handover->schedule_status !== 'confirmed' || ! $handover->schedule_confirmed_at || ! $start || ! $end) {
                return 'schedule_not_confirmed';
            }
            $valid = match ($kind) {
                '24h_reminder' => $now->gte($start->copy()->subHours(24)) && $now->lt($start->copy()->subHours(2)),
                '2h_reminder' => $now->gte($start->copy()->subHours(2)) && $now->lt($start),
                'receipt_reminder' => $handover->scheduled_method === 'delivery' && $handover->released_at && $now->gte($end->copy()->addHour()),
                'delivery_follow_up' => $handover->scheduled_method === 'delivery' && $handover->released_at && $now->gte($end->copy()->addHours(3)),
                'missed_pickup' => $handover->scheduled_method === 'pickup' && ! $handover->released_at && $now->gte($end->copy()->addHour()),
            };

            return $valid ? null : 'event_no_longer_due';
        }

        return match ($kind) {
            'prepared', 'reopened' => $handover->released_at ? 'already_released' : null,
            'released' => $handover->released_at ? null : 'not_released',
            'method_selected' => $handover->schedule_status === 'unscheduled' ? null : 'schedule_progressed',
            'schedule_proposed' => $handover->schedule_status === 'proposed' ? null : 'proposal_no_longer_active',
            'schedule_confirmed', 'reschedule_approved' => $handover->schedule_status === 'confirmed' ? null : 'agreement_no_longer_active',
            'reschedule_requested' => $handover->reschedule_status === 'pending' ? null : 'request_no_longer_pending',
            'reschedule_declined' => $handover->reschedule_status === 'declined' ? null : 'request_superseded',
            default => null,
        };
    }
}
