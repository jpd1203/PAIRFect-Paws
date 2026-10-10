<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Handover;
use App\Models\User;
use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandoverScheduleService
{
    public const MARKERS = ['schedule_24h_reminder_sent_at', 'schedule_2h_reminder_sent_at', 'receipt_reminder_sent_at', 'follow_up_flagged_at', 'missed_pickup_notified_at'];

    public function __construct(private HandoverNotificationService $notifications) {}

    public function window(array $data): array
    {
        $date = $data['date'] ?? null;
        $start = $data['start_time'] ?? null;
        $end = $data['end_time'] ?? null;
        if (! $date || ! $start || ! $end) {
            throw ValidationException::withMessages(['options' => 'Enter a date, start time, and end time for each schedule option.']);
        }
        $from = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$start}", ManilaTime::timezone());
        $to = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$end}", ManilaTime::timezone());
        if ($from->lte(ManilaTime::now()) || $to->lte($from)) {
            throw ValidationException::withMessages(['options' => 'Schedules must be in the future, with the end after the start on the same day.']);
        }

        return [$from->utc(), $to->utc()];
    }

    private function editable(Handover $handover): void
    {
        if ($handover->application?->status !== ApplicationStatus::Approved || $handover->released_at || $handover->adopter_outcome || $handover->received_at) {
            throw ValidationException::withMessages(['schedule' => 'Only an approved handover awaiting release can be scheduled or rescheduled.']);
        }
    }

    private function version(Handover $handover, int $version): void
    {
        if ($handover->schedule_version !== $version) {
            throw ValidationException::withMessages(['schedule' => 'The schedule has changed. Refresh the page before continuing.']);
        }
    }

    public function propose(Handover $handover, User $actor, array $data): void
    {
        abort_unless($actor->is_active && $actor->isStaff(), 403);
        [$start, $end] = $this->window($data);
        DB::transaction(function () use ($handover, $actor, $data, $start, $end): void {
            $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            $this->editable($current);
            $this->version($current, (int) $data['schedule_version']);
            $current->update([
                'scheduled_method' => $data['method'], 'scheduled_start_at' => $start,
                'scheduled_end_at' => $end, 'schedule_status' => 'proposed',
                'schedule_version' => $current->schedule_version + 1,
                'schedule_confirmed_at' => null, 'schedule_confirmed_by_user_id' => null,
                'reschedule_status' => null, 'reschedule_options' => null, 'reschedule_reason' => null,
                'reschedule_requested_at' => null, 'reschedule_reviewed_at' => null,
                'reschedule_reviewed_by_user_id' => null,
                ...array_fill_keys(self::MARKERS, null),
            ]);
            $this->history($current, $actor, 'schedule.proposed');
            $this->notice($current, 'schedule_proposed', 'Please confirm your proposed handover schedule', 'Shelter staff proposed '.$this->label($current).'. Confirm it or request another window.');
        });
    }

    public function confirm(Handover $handover, User $actor, int $version): void
    {
        abort_unless((int) $handover->user_id === (int) $actor->id, 403);
        DB::transaction(function () use ($handover, $actor, $version): void {
            $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            abort_unless((int) $current->user_id === (int) $actor->id, 403);
            $this->editable($current);
            $this->version($current, $version);
            if ($current->schedule_status !== 'proposed' || ! $current->scheduled_start_at || $current->scheduled_start_at->lte(now())) {
                throw ValidationException::withMessages(['schedule' => 'There is no future proposed schedule to confirm.']);
            }
            $current->update(['schedule_status' => 'confirmed', 'schedule_confirmed_at' => now(), 'schedule_confirmed_by_user_id' => $actor->id]);
            $this->history($current, $actor, 'schedule.confirmed');
            $this->notice($current, 'schedule_confirmed', 'Handover schedule confirmed', $this->label($current).' is confirmed.');
            $this->notifications->staffEvent($current, 'schedule_confirmed', 'Handover schedule confirmed', 'The adopter confirmed the transfer window.');
        });
    }

    public function requestReschedule(Handover $handover, User $actor, array $data): void
    {
        abort_unless((int) $handover->user_id === (int) $actor->id, 403);
        $options = [];
        foreach ($data['options'] as $option) {
            if (! filled($option['date'] ?? null) && ! filled($option['start_time'] ?? null) && ! filled($option['end_time'] ?? null)) {
                continue;
            }
            $this->window($option);
            $options[] = ['date' => $option['date'], 'start_time' => $option['start_time'], 'end_time' => $option['end_time']];
        }
        if (count($options) < 1 || count($options) > 3 || count(array_unique(array_map(fn ($option) => json_encode($option), $options))) !== count($options)) {
            throw ValidationException::withMessages(['options' => 'Provide one to three distinct future schedule options.']);
        }
        DB::transaction(function () use ($handover, $actor, $data, $options): void {
            $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            abort_unless((int) $current->user_id === (int) $actor->id, 403);
            $this->editable($current);
            $this->version($current, (int) $data['schedule_version']);
            if (! in_array($current->schedule_status, ['proposed', 'confirmed'], true) || $current->reschedule_status === 'pending') {
                throw ValidationException::withMessages(['schedule' => 'A reschedule request is already pending.']);
            }
            $current->update([
                'reschedule_options' => $options, 'reschedule_reason' => $data['reason'] ?? null,
                'schedule_version' => $current->schedule_version + 1,
                'reschedule_status' => 'pending', 'reschedule_requested_at' => now(),
                'reschedule_reviewed_at' => null, 'reschedule_reviewed_by_user_id' => null,
                // Keep an agreed schedule active while staff review alternatives.
                'schedule_status' => $current->schedule_confirmed_at ? 'confirmed' : 'reschedule_pending',
            ]);
            $this->history($current, $actor, 'schedule.reschedule_requested');
            $this->notice($current, 'reschedule_requested', 'Handover reschedule requested', 'Staff will review your preferred windows. Any currently confirmed schedule remains in place until a replacement is approved.');
            $this->notifications->staffEvent($current, 'reschedule_requested', 'Handover reschedule requested', 'The adopter submitted preferred transfer windows for review.');
        });
    }

    public function review(Handover $handover, User $actor, array $data): void
    {
        abort_unless($actor->is_active && $actor->isStaff(), 403);
        DB::transaction(function () use ($handover, $actor, $data): void {
            $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            $this->editable($current);
            $this->version($current, (int) $data['schedule_version']);
            if ($current->reschedule_status !== 'pending' || ! $current->reschedule_options) {
                throw ValidationException::withMessages(['schedule' => 'There is no pending request to review.']);
            }
            $approved = $data['decision'] === 'approved';
            $updates = ['reschedule_status' => $data['decision'], 'reschedule_reviewed_at' => now(), 'reschedule_reviewed_by_user_id' => $actor->id];
            if ($approved) {
                $option = $current->reschedule_options[$data['option_index'] ?? -1] ?? null;
                if (! $option) {
                    throw ValidationException::withMessages(['option_index' => 'Select one of the requested schedule options.']);
                }
                [$start, $end] = $this->window($option);
                $updates += [
                    'scheduled_start_at' => $start, 'scheduled_end_at' => $end, 'schedule_status' => 'confirmed',
                    'schedule_version' => $current->schedule_version + 1,
                    'schedule_confirmed_at' => now(), 'schedule_confirmed_by_user_id' => $current->user_id,
                    ...array_fill_keys(self::MARKERS, null),
                ];
            } elseif (! $current->schedule_confirmed_at) {
                $updates += ['schedule_status' => 'unscheduled', 'scheduled_start_at' => null, 'scheduled_end_at' => null];
            }
            $current->update($updates);
            $this->history($current, $actor, 'schedule.reschedule_'.$data['decision']);
            $message = $approved ? 'Staff accepted your preferred window: '.$this->label($current).'.'
                : ($current->schedule_confirmed_at ? 'Your current confirmed schedule remains in place.' : 'Staff must propose another schedule before release.');
            $this->notice($current, 'reschedule_'.$data['decision'], 'Handover reschedule '.$data['decision'], $message);
        });
    }

    public function label(Handover $handover): string
    {
        if (! $handover->scheduled_start_at || ! $handover->scheduled_end_at) {
            return 'No agreed schedule';
        }

        return ucfirst($handover->scheduled_method).' on '.ManilaTime::format($handover->scheduled_start_at, 'M j, Y g:i A').' – '.ManilaTime::format($handover->scheduled_end_at, 'g:i A').' (Asia/Manila)';
    }

    public function notice(Handover $handover, string $kind, string $title, string $body): void
    {
        $this->notifications->create($handover, $kind, [
            'title' => $title, 'body' => $body, 'channels' => ['In-app', 'Email'],
            'action_label' => 'View handover status', 'action_url' => route('adopter.handover.status', $handover),
        ]);
    }

    private function history(Handover $handover, User $actor, string $event): void
    {
        $handover->recordHistory($event.' (schedule version '.$handover->schedule_version.')', $actor->full_name);
        $handover->save();
        AuditLogService::log($actor->id, $event, 'Handover', $handover->id, 'Schedule version '.$handover->schedule_version.'.');
    }
}
