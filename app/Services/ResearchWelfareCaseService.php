<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ResolutionOutcome;
use App\Enums\Role;
use App\Models\ResearchCheckIn;
use App\Models\ResearchPlacement;
use App\Models\User;
use App\Support\ResearchMode;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ResearchWelfareCaseService
{
    public function __construct(
        private readonly FlagEvaluationService $evaluation,
        private readonly InAppNotificationService $notices,
    ) {}

    /** Shares normal criteria without invoking normal report writes or notifications. */
    public function surveyReasons(ResearchCheckIn $report): array
    {
        $survey = $report->answers ?? [];
        foreach (array_unique(['pet_current_status', ...array_keys(config('post_adoption.welfare.negative_answers', []))]) as $field) {
            $value = data_get($survey, $field);
            if ($value !== null && ! is_scalar($value)) {
                Arr::forget($survey, $field);
            }
        }

        return $this->evaluation->surveyReasons($survey);
    }

    /** Called under the placement lock, in the same transaction as a new report or backfill. */
    public function evaluateLocked(ResearchCheckIn $report, bool $historical = false): bool
    {
        if ($report->welfare_evaluated_at !== null) {
            return false;
        }

        $updates = ['welfare_evaluated_at' => now()];
        $state = $report->welfare_case ?? [];
        $reasons = $this->surveyReasons($report);
        $added = false;
        if ($reasons !== [] && ! $report->resolved_at && ! $report->resolution_outcome) {
            $existing = collect($state['flag_reasons'] ?? [])->pluck('message');
            $newReasons = array_values(array_diff($reasons, $existing->all()));
            foreach ($newReasons as $message) {
                $state['flag_reasons'][] = [
                    'code' => 'survey_welfare_concern', 'message' => $message, 'flagged_at' => now()->toIso8601String(),
                ];
            }
            if ($newReasons !== []) {
                $this->event($report, $state, $historical ? 'historical_survey_flagged' : 'survey_flagged', null, [
                    'reasons' => $newReasons,
                ]);
                $updates += ['is_flagged' => true, 'welfare_case' => $state];
                $added = true;
            }
        }
        $report->forceFill($updates)->save();
        if ($added && ! $historical) {
            $this->notifyStaff($report, 'New flagged welfare report', count($state['history']));
        }

        return $added;
    }

    public function backfill(int $id): bool
    {
        return $this->withLockedReport($id, fn (ResearchCheckIn $report) => $this->evaluateLocked($report, historical: true));
    }

    public function flag(int $id, User $staff, string $reason): bool
    {
        return $this->withLockedReport($id, function (ResearchCheckIn $report) use ($staff, $reason): bool {
            if ($report->resolution_outcome === ResolutionOutcome::PetReturned) {
                throw ValidationException::withMessages(['reason' => 'A recorded return outcome cannot be reopened as a monitoring flag.']);
            }

            $state = $report->welfare_case ?? [];
            $reason = trim($reason);
            $activeDuplicate = $report->is_flagged && ! $report->resolved_at
                && collect($state['flag_reasons'] ?? [])->contains(fn (array $item) => ($item['code'] ?? null) === 'manual_staff_flag'
                    && ($item['message'] ?? null) === $reason
                    && ($item['flagged_by_user_id'] ?? null) === $staff->id
                    && ($item['review_cycle'] ?? 0) === ($state['review_cycle'] ?? 0));
            if ($activeDuplicate) {
                return false;
            }
            $updates = ['is_flagged' => true];
            if ($report->resolved_at) {
                $state['review_cycle'] = ($state['review_cycle'] ?? 0) + 1;
                unset($state['resolution_note'], $state['decision_by_user_id'], $state['decision_at']);
                $updates += ['resolved_at' => null, 'resolution_outcome' => null];
            }
            $state['flag_reasons'][] = [
                'code' => 'manual_staff_flag', 'message' => $reason, 'flagged_at' => now()->toIso8601String(),
                'flagged_by_user_id' => $staff->id, 'review_cycle' => $state['review_cycle'] ?? 0,
            ];
            $this->event($report, $state, 'manual_flag', $staff->id, ['reason' => $reason]);
            $report->forceFill($updates + ['welfare_case' => $state])->save();
            $this->notifyStaff($report, 'Welfare case flagged for review', count($state['history']));

            return true;
        });
    }

    public function resolutionRules(): array
    {
        return [
            'resolution_outcome' => ['required', Rule::enum(ResolutionOutcome::class)],
            'resolution_note' => ['required', 'string', 'max:2000'],
            'return_date' => ['required_if:resolution_outcome,pet_returned', 'nullable', 'date_format:Y-m-d',
                'before_or_equal:'.today(PostAdoptionScheduleService::TIMEZONE)->toDateString()],
            'return_reason' => ['required_if:resolution_outcome,pet_returned', 'nullable', 'string', 'max:2000'],
            'return_condition' => ['required_if:resolution_outcome,pet_returned', 'nullable', 'string', 'max:2000'],
            'return_handled_by_user_id' => ['required_if:resolution_outcome,pet_returned', 'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', [Role::Administrator->value, Role::Volunteer->value])->where('is_active', true))],
            'confirm_return' => ['required_if:resolution_outcome,pet_returned', 'accepted_if:resolution_outcome,pet_returned'],
        ];
    }

    public function resolve(int $id, User $staff, array $data): bool
    {
        $outcome = ResolutionOutcome::from($data['resolution_outcome']);

        return $this->withLockedReport($id, function (ResearchCheckIn $report, ResearchPlacement $placement) use ($staff, $data, $outcome): bool {
            if (! $report->is_flagged || $report->resolved_at !== null) {
                return false;
            }
            $state = $report->welfare_case ?? [];
            if ($outcome === ResolutionOutcome::FollowUpRequired && $report->resolution_outcome === $outcome
                && ($state['resolution_note'] ?? null) === trim($data['resolution_note'])) {
                return false;
            }
            if ($outcome === ResolutionOutcome::PetReturned && $placement->hasScenarioReturn()) {
                throw ValidationException::withMessages(['resolution_outcome' => 'A return outcome has already been recorded for this placement.']);
            }
            $state['resolution_note'] = trim($data['resolution_note']);
            $state['decision_by_user_id'] = $staff->id;
            $state['decision_at'] = now()->toIso8601String();
            if ($outcome === ResolutionOutcome::PetReturned) {
                $state['return'] = [
                    'date' => $data['return_date'], 'reason' => trim($data['return_reason']),
                    'condition' => trim($data['return_condition']), 'handled_by_user_id' => (int) $data['return_handled_by_user_id'],
                ];
            }
            $this->event($report, $state, 'resolution_recorded', $staff->id, [
                'outcome' => $outcome->value, 'note' => $state['resolution_note'], 'return' => $state['return'] ?? null,
            ]);
            $report->forceFill([
                'resolution_outcome' => $outcome,
                'resolved_at' => $outcome === ResolutionOutcome::FollowUpRequired ? null : now(),
                'welfare_case' => $state,
            ])->save();

            $revision = count($state['history']);
            if (in_array($outcome, [ResolutionOutcome::FollowUpRequired, ResolutionOutcome::ReturnRecommended, ResolutionOutcome::PetReturned], true)) {
                $this->notifyStaff($report, $outcome === ResolutionOutcome::PetReturned ? 'Return outcome recorded' : $outcome->label(), $revision);
            }
            $reportId = $report->id;
            $recipient = $placement->user;
            DB::afterCommit(function () use ($recipient, $outcome, $reportId, $revision): void {
                $title = $outcome === ResolutionOutcome::PetReturned ? 'Return outcome recorded' : $outcome->label();
                $message = $outcome === ResolutionOutcome::FollowUpRequired
                    ? 'Open your check-in report to review the guidance and send a follow-up.'
                    : 'Open your check-in report to review the recorded outcome and staff guidance.';
                $this->notices->user($recipient, 'research_welfare_outcome', $title, $message,
                    route('monitoring.my-checkins', absolute: false), "research-welfare:{$reportId}:outcome:{$revision}", 'ResearchCheckIn', $reportId);
            });

            return true;
        });
    }

    public function followUp(int $id, User $adopter, string $note): ResearchCheckIn
    {
        return $this->withLockedReport($id, function (ResearchCheckIn $report) use ($adopter, $note): ResearchCheckIn {
            abort_unless($report->is_flagged && ! $report->resolved_at && $report->resolution_outcome === ResolutionOutcome::FollowUpRequired,
                409, 'This case is not awaiting an adopter follow-up.');
            $state = $report->welfare_case ?? [];
            if (($state['follow_up_notes'] ?? null) === trim($note)) {
                return $report;
            }
            $state['follow_up_notes'] = trim($note);
            $state['follow_up_submitted_at'] = now()->toIso8601String();
            $this->event($report, $state, 'adopter_follow_up', $adopter->id, ['note' => $state['follow_up_notes']]);
            $report->forceFill(['welfare_case' => $state])->save();

            return $report;
        }, $adopter->id);
    }

    private function withLockedReport(int $id, Closure $operation, ?int $owner = null): mixed
    {
        abort_unless(ResearchMode::ready(), 404);
        $report = ResearchCheckIn::query()->afterReceipt()
            ->when($owner !== null, fn ($query) => $query->whereHas('placement', fn ($placement) => $placement->where('user_id', $owner)))
            ->findOrFail($id);

        return DB::transaction(function () use ($report, $operation, $owner) {
            // Same lock order as camera submission; serializes placement-level return outcomes.
            $placement = ResearchPlacement::query()->lockForUpdate()->findOrFail($report->placement_id);
            abort_unless($placement->status === ApplicationStatus::Approved && $placement->received_at
                && ($owner === null || (int) $placement->user_id === $owner), 404);
            $locked = $placement->researchCheckIns()->lockForUpdate()->findOrFail($report->id);

            return $operation($locked, $placement);
        }, 3);
    }

    private function event(ResearchCheckIn $report, array &$state, string $event, ?int $actor, array $details): void
    {
        $state['history'][] = ['event' => $event, 'actor_user_id' => $actor, 'at' => now()->toIso8601String(), ...$details];
        AuditLogService::log($actor, match ($event) {
            'manual_flag' => 'Research Check-in Manually Flagged',
            'resolution_recorded' => 'Research Welfare Outcome Recorded',
            'adopter_follow_up' => 'Research Welfare Follow-up Submitted',
            default => 'Research Check-in Flagged',
        }, 'ResearchCheckIn', $report->id, isset($details['outcome']) ? 'Outcome: '.$details['outcome'].'.' : null);
    }

    private function notifyStaff(ResearchCheckIn $report, string $title, int $revision): void
    {
        $id = $report->id;
        DB::afterCommit(fn () => $this->notices->administrators(
            'research_welfare_staff', $title, 'Open Monitoring to review the check-in and case history.',
            route('admin.monitoring.index', absolute: false), "research-welfare:{$id}:staff:{$revision}", 'ResearchCheckIn', $id,
        ));
    }
}
