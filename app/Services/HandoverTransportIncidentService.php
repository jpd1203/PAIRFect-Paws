<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Handover;
use App\Models\HandoverEmailOutbox;
use App\Models\HandoverTransportIncident;
use App\Models\User;
use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandoverTransportIncidentService
{
    public function staff(User $actor): User
    {
        $current = User::query()->find($actor->id);
        abort_unless($current?->is_active && $current->isStaff() && $current->hasVerifiedEmail(), 403);

        return $current;
    }

    public function canManage(User $actor, HandoverTransportIncident $case): bool
    {
        return $actor->role === Role::Administrator || (int) $case->assigned_to_user_id === (int) $actor->id;
    }

    /** Called inside the existing receipt transaction, with the handover locked. */
    public function reportedNonReceipt(Handover $handover, User $actor): void
    {
        if ($handover->release_method === 'delivery') {
            $this->createLocked($handover, 'adopter_non_receipt', 'pet_not_received',
                'The adopter reported non-receipt. This is not a verified delivery failure.', $actor, false);
        }
    }

    /** Called inside the existing +3h escalation transaction; its alert/outbox is retained. */
    public function overdueDelivery(Handover $handover): void
    {
        $this->createLocked($handover, 'overdue_delivery', 'delayed_delivery',
            'The delivery window ended at least three hours ago without receipt confirmation. Investigation is required.', null, false);
    }

    public function open(Handover $handover, User $actor, array $data, ?array $evidence = null): HandoverTransportIncident
    {
        return DB::transaction(function () use ($handover, $actor, $data, $evidence): HandoverTransportIncident {
            $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            $staff = app(HandoverDeliveryClearanceService::class)->authorizeStaff($actor);
            if ((int) $data['release_attempt'] !== (int) $current->reopen_count
                || $current->release_method !== 'delivery' || ! $current->released_at) {
                throw ValidationException::withMessages(['incident' => 'Refresh the handover. A transport report must reference its current recorded delivery release attempt.']);
            }

            return $this->createLocked($current, 'staff_report', $data['category'], trim($data['description']), $staff, true, $evidence);
        });
    }

    private function createLocked(Handover $handover, string $source, string $category, string $description, ?User $actor, bool $notify, ?array $evidence = null): HandoverTransportIncident
    {
        $key = ['handover_id' => $handover->id, 'release_attempt' => (int) $handover->reopen_count, 'source' => $source, 'category' => $category];
        $existing = HandoverTransportIncident::query()->where($key)->first();
        if ($existing) {
            // One case per category/source/attempt. Add investigation notes to the existing case.
            return $existing;
        }
        $case = new HandoverTransportIncident;
        $case->forceFill($key + [
            'description' => $description,
            'reported_by_user_id' => $source === 'adopter_non_receipt' ? $handover->user_id : $actor?->id,
            'reported_at' => match ($source) {
                'adopter_non_receipt' => $handover->adopter_confirmed_at ?? now(),
                'overdue_delivery' => $handover->follow_up_flagged_at ?? now(),
                default => now(),
            },
            'status' => 'open', 'version' => 1, 'evidence_path' => $evidence['path'] ?? null,
            'evidence_sha256' => $evidence['hash'] ?? null,
            'release_context' => [
                'released_at' => $handover->released_at?->toIso8601String(),
                'scheduled_start_at' => $handover->scheduled_start_at?->toIso8601String(),
                'scheduled_end_at' => $handover->scheduled_end_at?->toIso8601String(),
                'schedule_version' => $handover->schedule_version, 'release_method' => $handover->release_method,
                'release_proof_path' => $handover->proof_path,
                'adopter_report_note' => $source === 'adopter_non_receipt' ? $handover->adopter_note : null,
            ], 'history' => [],
        ])->save();
        $this->history($case, $handover, $actor, 'opened');
        if ($notify) {
            $this->alert($case, $handover, 'opened');
        }

        return $case;
    }

    public function update(HandoverTransportIncident $case, User $actor, array $data, ?array $evidence = null): HandoverTransportIncident
    {
        return DB::transaction(function () use ($case, $actor, $data, $evidence): HandoverTransportIncident {
            $handover = Handover::query()->lockForUpdate()->findOrFail($case->handover_id);
            $current = HandoverTransportIncident::query()->lockForUpdate()->findOrFail($case->id);
            $staff = app(HandoverDeliveryClearanceService::class)->authorizeStaff($actor);
            abort_unless($this->canManage($staff, $current), 403);
            if ((int) $data['version'] !== $current->version || $current->status === 'resolved') {
                throw ValidationException::withMessages(['incident' => 'This investigation changed or is already resolved. Refresh before continuing.']);
            }
            $changes = [
                'status' => $data['status'], 'findings' => trim($data['findings']),
                'next_follow_up_at' => filled($data['next_follow_up_at'] ?? null)
                    ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['next_follow_up_at'], 'Asia/Manila')->utc() : null,
            ];
            if ($staff->role === Role::Administrator) {
                $assignee = filled($data['assigned_to_user_id'] ?? null)
                    ? User::query()->lockForUpdate()->find($data['assigned_to_user_id']) : null;
                if (filled($data['assigned_to_user_id'] ?? null) && (! $assignee?->is_active || ! $assignee->isStaff() || ! $assignee->hasVerifiedEmail())) {
                    throw ValidationException::withMessages(['assigned_to_user_id' => 'Select active, verified shelter staff.']);
                }
                $changes['assigned_to_user_id'] = $assignee?->id;
            } elseif (array_key_exists('assigned_to_user_id', $data)) {
                abort(403, 'Only administrators may assign investigations.');
            }
            if ($data['status'] === 'under_investigation') {
                $responsible = User::query()->lockForUpdate()->find(array_key_exists('assigned_to_user_id', $changes)
                    ? $changes['assigned_to_user_id'] : $current->assigned_to_user_id);
                if (! $responsible?->is_active || ! $responsible->isStaff() || ! $responsible->hasVerifiedEmail()) {
                    throw ValidationException::withMessages(['assigned_to_user_id' => 'Assign active, verified responsible staff before starting investigation.']);
                }
            }
            if ($data['status'] === 'resolved') {
                abort_unless($staff->role === Role::Administrator, 403);
                if (($data['resolution_outcome'] ?? null) === 'adopter_confirmed_receipt'
                    && ((int) $handover->reopen_count !== $current->release_attempt || $handover->adopter_outcome !== 'received'
                        || ! $handover->received_at || ! $handover->receipt_proof_path)) {
                    throw ValidationException::withMessages(['resolution_outcome' => 'This release attempt has no protected adopter receipt confirmation with supporting evidence. Closing a case cannot confirm receipt.']);
                }
                $changes += [
                    'resolution_outcome' => $data['resolution_outcome'], 'resolution_notes' => trim($data['resolution_notes']),
                    'resolved_by_user_id' => $staff->id, 'resolved_at' => now(), 'next_follow_up_at' => null,
                ];
                $changes['next_follow_up_at'] = null;
            } elseif (empty($changes['next_follow_up_at']) || $changes['next_follow_up_at']->lte(now())) {
                throw ValidationException::withMessages(['next_follow_up_at' => 'Set the next follow-up in the future (Philippine time), or document an administrator resolution.']);
            }
            if ($evidence) {
                if ($current->evidence_path) {
                    throw ValidationException::withMessages(['evidence' => 'Supporting evidence is already recorded and cannot be replaced.']);
                }
                $changes += ['evidence_path' => $evidence['path'], 'evidence_sha256' => $evidence['hash']];
            }
            $followUpChanged = ($current->next_follow_up_at?->toIso8601String() !== $changes['next_follow_up_at']?->toIso8601String());
            $current->forceFill($changes + ['version' => $current->version + 1]);
            if ($followUpChanged) {
                $current->follow_up_notified_at = null;
            }
            $current->save();
            $this->history($current, $handover, $staff, $current->status === 'resolved' ? 'resolved' : 'updated');
            if ($current->status !== 'resolved') {
                $this->alert($current, $handover, 'updated');
            }

            return $current;
        });
    }

    private function history(HandoverTransportIncident $case, Handover $handover, ?User $actor, string $event): void
    {
        $history = $case->history ?? [];
        $history[] = [
            'event' => $event, 'at' => now()->toIso8601String(), 'actor_id' => $actor?->id,
            'version' => $case->version, 'status' => $case->status, 'assigned_to_user_id' => $case->assigned_to_user_id,
            'next_follow_up_at' => $case->next_follow_up_at?->toIso8601String(), 'findings' => $case->findings,
            'resolution_outcome' => $case->resolution_outcome, 'resolution_notes' => $case->resolution_notes,
        ];
        $case->history = $history;
        $case->save();
        $handover->recordHistory("Transport investigation #{$case->id} {$event} (release attempt {$case->release_attempt})", $actor?->full_name ?? 'System');
        $handover->save();
        AuditLogService::log($actor?->id, 'handover.transport_'.$event, 'HandoverTransportIncident', $case->id,
            "Handover {$handover->id}; attempt {$case->release_attempt}; version {$case->version}; status {$case->status}.");
    }

    private function alert(HandoverTransportIncident $case, Handover $handover, string $event): void
    {
        $recipients = app(EmailNotificationService::class)->staffRecipients(true);
        $assigned = $case->assignedTo;
        if ($assigned?->is_active && $assigned->isStaff() && $assigned->hasVerifiedEmail()) {
            $recipients->put(strtolower(trim($assigned->email)), $assigned);
        }
        $url = route('admin.handover.show', $handover).'#transport-case-'.$case->id;
        $key = "transport-case:{$case->id}:{$case->version}:{$event}";
        foreach ($recipients as $recipient) {
            if ($recipient instanceof User) {
                // Unlike the generic best-effort feed, case notice persistence is transactional.
                $recipient->inAppNotifications()->firstOrCreate(['dedupe_key' => $key], [
                    'kind' => 'transport_case_attention', 'title' => 'Transport investigation requires attention',
                    'body' => "Investigation #{$case->id} requires staff review. Reports are not verified delivery findings.",
                    'channels' => ['In-app'], 'action_label' => 'Review investigation', 'action_url' => $url,
                    'entity_type' => 'Handover', 'entity_id' => $handover->id, 'read' => false,
                ]);
            }
            app(HandoverMailOutboxService::class)->stage($handover, 'transport_case_attention', $key, $recipient, [
                'subject' => 'Transport investigation requires attention', 'heading' => 'Transport investigation requires attention',
                'lines' => ["Investigation #{$case->id} requires staff review. A report does not establish delivery failure or a missing animal.", 'Open the protected handover record for details.'],
                'action_text' => 'Review investigation', 'action_url' => $url,
                'transport_case_id' => $case->id, 'transport_case_version' => $case->version,
            ]);
        }
    }

    public function noticeIsCurrent(Handover $handover, HandoverEmailOutbox $row): bool
    {
        $payload = $row->payload;
        $case = HandoverTransportIncident::query()->find($payload['transport_case_id'] ?? null);
        if (! $case || (int) $case->handover_id !== (int) $handover->id || $case->status === 'resolved'
            || $case->version !== ($payload['transport_case_version'] ?? null)) {
            return false;
        }
        if ($row->audience === 'alert') {
            return true; // Current configured shelter alert inbox; checked by the outbox.
        }
        $recipient = User::find($row->recipient_user_id);

        return $recipient && ($recipient->role === Role::Administrator || (int) $case->assigned_to_user_id === (int) $recipient->id);
    }

    /** Disabled until a positive whole-hour shelter policy is explicitly configured. */
    public function maximumPendingHours(): ?int
    {
        $value = config('handover.transport_max_pending_hours');

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false ? null : (int) $value;
    }

    public function isOverdue(Handover $handover): bool
    {
        return $handover->release_method === 'delivery' && $handover->released_at && ! $handover->received_at
            && $handover->adopter_outcome !== 'received' && $handover->scheduled_end_at
            && ManilaTime::now()->gte(ManilaTime::at($handover->scheduled_end_at)->addHours(3));
    }

    public function process(): int
    {
        $hours = $this->maximumPendingHours();
        if ($hours !== null) {
            Handover::query()->where('release_method', 'delivery')->where('released_at', '<=', now()->subHours($hours))
                ->whereNull('received_at')->whereNull('adopter_outcome')
                ->chunkById(100, function ($handovers) use ($hours): void {
                    foreach ($handovers as $candidate) {
                        DB::transaction(function () use ($candidate, $hours): void {
                            $handover = Handover::query()->lockForUpdate()->findOrFail($candidate->id);
                            if ($handover->release_method === 'delivery' && $handover->released_at && ! $handover->adopter_outcome
                                && ! $handover->received_at && $handover->released_at->copy()->addHours($hours)->lte(now())
                                && ! $handover->transportIncidents()->where('release_attempt', $handover->reopen_count)->exists()) {
                                $this->createLocked($handover, 'overdue_delivery', 'delayed_delivery',
                                    'Shelter-configured maximum pending duration reached without receipt confirmation. Staff review is required; delivery failure is not established.', null, false);
                            }
                        });
                    }
                });
        }
        // Backfill real unresolved legacy escalation/report records, not completed history.
        Handover::query()->where('release_method', 'delivery')->whereNotNull('released_at')->whereNull('received_at')
            ->where(fn ($query) => $query->where('adopter_outcome', 'not_received')->orWhereNotNull('follow_up_flagged_at'))
            ->chunkById(100, function ($handovers): void {
                foreach ($handovers as $candidate) {
                    DB::transaction(function () use ($candidate): void {
                        $handover = Handover::query()->lockForUpdate()->findOrFail($candidate->id);
                        if ($handover->release_method !== 'delivery' || ! $handover->released_at || $handover->received_at || $handover->adopter_outcome === 'received') {
                            return;
                        }
                        if ($handover->adopter_outcome === 'not_received') {
                            $this->createLocked($handover, 'adopter_non_receipt', 'pet_not_received', 'Existing adopter non-receipt report requires investigation.', null, false);
                        } elseif ($handover->follow_up_flagged_at && $this->isOverdue($handover)) {
                            $this->overdueDelivery($handover);
                        }
                    });
                }
            });
        $count = 0;
        HandoverTransportIncident::query()->where('status', '!=', 'resolved')->chunkById(100, function ($cases) use (&$count): void {
            foreach ($cases as $candidate) {
                $count += DB::transaction(function () use ($candidate): int {
                    $handover = Handover::query()->lockForUpdate()->findOrFail($candidate->handover_id);
                    $case = HandoverTransportIncident::query()->lockForUpdate()->findOrFail($candidate->id);
                    if ($case->status === 'resolved') {
                        return 0;
                    }
                    $due = $case->next_follow_up_at && $case->next_follow_up_at->lte(now()) && ! $case->follow_up_notified_at;
                    $hours = $this->maximumPendingHours();
                    $released = $case->release_context['released_at'] ?? null;
                    $maximum = $hours && $released && ! $case->max_pending_notified_at
                        && CarbonImmutable::parse($released)->addHours($hours)->lte(now());
                    if (! $due && ! $maximum) {
                        return 0;
                    }
                    $case->forceFill([
                        'follow_up_notified_at' => $due ? now() : $case->follow_up_notified_at,
                        'max_pending_notified_at' => $maximum ? now() : $case->max_pending_notified_at,
                        'version' => $case->version + 1,
                    ])->save();
                    $event = $maximum ? 'maximum_pending_review' : 'follow_up_due';
                    $this->history($case, $handover, null, $event);
                    $this->alert($case, $handover, $event);

                    return 1;
                });
            }
        });

        return $count;
    }
}
