<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Enums\Milestone;
use App\Enums\PetCurrentStatus;
use App\Enums\ResolutionOutcome;
use App\Models\ResearchCheckIn;
use App\Models\ResearchPlacement;
use App\Models\User;
use App\Services\Matching\ApplicantRankingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/** Read-only projections for the existing staff pages, never production records. */
final class ResearchAdminRecords
{
    public function applications(): Builder
    {
        return ResearchPlacement::with(['user', 'pet']);
    }

    public function rankForDisplay(Collection $applications, ApplicantRankingService $ranking): Collection
    {
        foreach ($applications->where('status', ApplicationStatus::Waitlisted)->groupBy('pet_id') as $visible) {
            $pet = $visible->first()->pet;
            if (! $pet) {
                continue;
            }
            // Rank against the whole waitlist, including rows hidden by the page filters.
            $waitlist = $ranking->eligibleResearchForPet($pet, locked: false)->keyBy('id');
            $positions = $waitlist->keys()->flip();
            foreach ($visible as $application) {
                if ($candidate = $waitlist->get($application->id)) {
                    $application->queue_position = $positions[$application->id] + 1;
                    $application->compatibility_result = $candidate->compatibility_result;
                    $application->compatibility_score = $candidate->compatibility_score;
                }
            }
        }

        return $applications;
    }

    public function handovers(): Builder
    {
        return $this->applications()->where('status', ApplicationStatus::Approved->value)->whereNotNull('approved_at');
    }

    public function handover(ResearchPlacement $placement): Fluent
    {
        $schedule = $placement->handover_schedule ?? [];
        $receipt = $placement->handover_receipt ?? [];
        $received = $placement->received_at !== null;
        $ready = $received || ($schedule['status'] ?? null) === 'confirmed';
        $outcome = $received ? 'received' : ($receipt['outcome'] ?? null);
        $occurredAt = isset($receipt['at']) ? CarbonImmutable::parse($receipt['at']) : $placement->received_at;
        $history = [['label' => 'Application approved', 'actor' => 'System', 'at' => $placement->approved_at->toISOString()]];
        if (isset($schedule['reschedule']['requested_at'])) {
            $history[] = ['label' => 'Reschedule requested', 'actor' => $placement->user?->full_name ?? 'Adopter', 'at' => $schedule['reschedule']['requested_at']];
        }
        if (isset($schedule['proposed_at'])) {
            $history[] = ['label' => 'Schedule proposed', 'actor' => 'System', 'at' => $schedule['proposed_at']];
        }
        if ($ready && $placement->released_at) {
            $history[] = ['label' => 'Handover marked ready', 'actor' => 'System', 'at' => $placement->released_at->toISOString()];
        }
        if (isset($receipt['previous_not_received']['at'])) {
            $history[] = ['label' => 'Reported not received', 'actor' => $placement->user?->full_name ?? 'Adopter', 'at' => $receipt['previous_not_received']['at']];
        }
        if ($received) {
            $history[] = ['label' => 'Receipt confirmation recorded', 'actor' => $placement->user?->full_name ?? 'Adopter', 'at' => $placement->received_at->toISOString()];
        }

        return new Fluent([
            'id' => $placement->id, 'code' => 'APP-'.$placement->id, 'application' => $placement,
            'pet' => $placement->pet, 'photo_url' => $placement->pet?->image_url,
            'adopter_name' => trim($placement->first_name.' '.$placement->last_name),
            'adopter_phone' => $placement->phone_number, 'adopter_email' => $placement->email, 'adopter_address' => $placement->address,
            'approved_at' => $placement->approved_at, 'released_at' => $ready ? $placement->released_at : null, 'received_at' => $placement->received_at,
            'adopter_outcome' => $outcome, 'adopter_confirmed_at' => $occurredAt, 'adopter_note' => $receipt['note'] ?? null,
            'receipt_proof_path' => $receipt['path'] ?? null,
            'scheduled_method' => $schedule['method'] ?? null, 'schedule_status' => $schedule['status'] ?? 'unscheduled',
            'can_select_schedule_method' => $placement->canSelectHandoverMethod(),
            'schedule_version' => $schedule['version'] ?? 0, 'reschedule_status' => isset($schedule['reschedule']) ? 'proposal updated' : null,
            'reschedule_reason' => $schedule['reschedule']['reason'] ?? null,
            'scheduled_start_at' => isset($schedule['start_at']) ? CarbonImmutable::parse($schedule['start_at']) : null,
            'scheduled_end_at' => isset($schedule['end_at']) ? CarbonImmutable::parse($schedule['end_at']) : null,
            'schedule_confirmed_at' => isset($schedule['confirmed_at']) ? CarbonImmutable::parse($schedule['confirmed_at']) : null,
            'history' => collect($history)->sortBy('at')->values()->all(), 'transportIncidents' => collect(),
            'current_step' => $received ? 'monitoring' : ($ready ? 'awaiting' : 'approved'),
            'completed_steps' => ['approved' => true, 'handover_set' => $ready, 'awaiting' => $received, 'monitoring' => $received],
            'monitoring_locked' => ! $received,
            'status_badge' => match (true) {
                $received => ['label' => 'Monitoring active', 'class' => 'badge-completed', 'icon' => 'fa-solid fa-circle-check'],
                $outcome === 'not_received' => ['label' => 'Not received', 'class' => 'badge-rejected', 'icon' => 'fa-solid fa-triangle-exclamation'],
                $ready => ['label' => 'Awaiting adopter', 'class' => 'badge-pending', 'icon' => 'fa-solid fa-clock'],
                default => ['label' => 'Needs handover', 'class' => 'badge-upcoming', 'icon' => 'fa-solid fa-box-archive'],
            },
        ]);
    }

    public function receivedHandovers(): Builder
    {
        return $this->handovers()->whereNotNull('received_at');
    }

    public function checkIns(?int $owner = null): Collection
    {
        $placements = $this->receivedHandovers()
            ->when($owner !== null, fn (Builder $query) => $query->where('user_id', $owner))
            ->orderByDesc('received_at')->orderByDesc('id')->get();
        $reports = ResearchCheckIn::whereIn('placement_id', $placements->modelKeys())->get();
        $byPlacement = $reports->groupBy('placement_id');
        $staff = $owner === null ? $this->staffAttribution($reports) : null;

        return $placements->flatMap(function (ResearchPlacement $placement) use ($byPlacement, $staff) {
            $submissions = $byPlacement->get($placement->id, collect())->keyBy('milestone');
            $next = collect(ResearchMode::MILESTONES)->first(fn ($key) => ! $submissions->has($key));
            $ended = $submissions->contains('resolution_outcome', ResolutionOutcome::PetReturned);

            return collect(ResearchMode::MILESTONES)->map(function (string $key) use ($placement, $submissions, $next, $ended, $staff) {
                $report = $submissions->get($key);
                $status = $ended ? 'Placement ended' : ($key === $next ? 'Pending' : 'Upcoming');

                return $this->checkInEntry($placement, $key, $report, $status, $staff);
            });
        });
    }

    public function flaggedReports(?int $owner = null): Builder
    {
        return ResearchCheckIn::query()->afterReceipt()->openFlags()
            ->when($owner !== null, fn (Builder $query) => $query->whereHas('placement', fn (Builder $placement) => $placement->where('user_id', $owner)));
    }

    public function flaggedCheckIns(?int $owner = null, ?int $limit = null): Collection
    {
        $reports = $this->flaggedReports($owner)->with('placement.pet', 'placement.user')
            ->latest('submitted_at')->latest('id')
            ->when($limit !== null, fn (Builder $query) => $query->limit($limit))->get();
        $staff = $owner === null ? $this->staffAttribution($reports) : null;

        return $reports->map(fn (ResearchCheckIn $report) => $this->checkInEntry($report->placement, $report->milestone, $report, 'Submitted', $staff));
    }

    public function adopterReport(ResearchCheckIn $report): Fluent
    {
        return $this->checkInEntry($report->placement, $report->milestone, $report, 'Submitted', null);
    }

    private function checkInEntry(ResearchPlacement $placement, string $key, ?ResearchCheckIn $report, string $unsubmittedStatus, ?Collection $staff): Fluent
    {
        $milestone = Milestone::from(str_replace('-', '_', $key).'s');
        $case = $report?->welfare_case ?? [];
        $answers = $report?->answers ?? [];
        $active = $report?->is_flagged && $report->resolved_at === null;
        $status = $active ? 'flagged' : ($report ? 'completed' : ($unsubmittedStatus === 'Pending' ? 'pending' : 'upcoming'));
        $health = data_get($answers, 'pet_current_status');
        $date = $case['return']['date'] ?? null;

        return new Fluent([
            'id' => $report?->id, 'application_id' => $placement->id, 'adoptionApplication' => $placement,
            'pet' => $placement->pet, 'milestone' => $milestone, 'milestone_display' => $milestone->label(),
            'status_slug' => $status, 'status_display' => ! $report && $unsubmittedStatus === 'Placement ended' ? $unsubmittedStatus : ucfirst($status),
            'display_status' => $report ? 'Submitted' : $unsubmittedStatus,
            'scheduled_date' => null, 'submitted_date' => $report ? CarbonImmutable::parse($report->getRawOriginal('submitted_at'), 'UTC') : null,
            'pet_current_status' => is_string($health) ? (PetCurrentStatus::tryFrom($health) ?? (object) ['value' => $health]) : null,
            'eating_habits' => $answers['eating_habits'] ?? null, 'living_conditions' => $answers['living_conditions'] ?? null,
            'behavioral_observations' => $answers['behavioral_observations'] ?? null,
            'vet_visit_details' => $answers['vet_visit_details'] ?? null, 'concerns' => $answers['concerns'] ?? null,
            'is_flagged' => $report?->is_flagged ?? false, 'display_is_flagged' => $active,
            'resolved_at' => $report?->resolved_at, 'resolution_outcome' => $report?->resolution_outcome,
            'resolution_outcome_label' => $report?->resolution_outcome === ResolutionOutcome::PetReturned
                ? 'Pet Returned to Shelter (scenario outcome)' : ($report?->resolution_outcome?->label() ?? 'Not resolved'),
            'resolution_note' => $case['resolution_note'] ?? null, 'return_date' => $date ? CarbonImmutable::parse($date, 'Asia/Manila') : null,
            'follow_up_notes' => $case['follow_up_notes'] ?? null,
            'follow_up_submitted_at' => isset($case['follow_up_submitted_at']) ? CarbonImmutable::parse($case['follow_up_submitted_at']) : null,
            'has_presentation_demo' => false, 'research_monitoring' => true,
            'submit_url' => route('research.report', $placement, absolute: false),
            'modal_url' => $report ? route('research.checkin.modal', $report->id) : null,
            'follow_up_url' => $report ? route('research.checkin.follow-up', $report->id) : null,
            ...($staff === null ? [] : [
                'user' => $placement->user, 'survey_data' => $answers, 'video_path' => $report?->video_path,
                'display_flag_reasons' => $case['flag_reasons'] ?? [],
                'resolvedBy' => $report?->resolved_at ? $staff->get($case['decision_by_user_id'] ?? 0) : null,
                'return_reason' => $case['return']['reason'] ?? null, 'return_condition' => $case['return']['condition'] ?? null,
                'returnHandledBy' => $staff->get($case['return']['handled_by_user_id'] ?? 0),
                'case_history_summary' => $this->caseHistory($case['history'] ?? [], $staff),
                'flag_url' => $report && $report->resolution_outcome !== ResolutionOutcome::PetReturned
                    ? route('admin.research.monitoring.flag', $report->id) : null,
                'resolve_url' => $report ? route('admin.research.monitoring.resolve', $report->id) : null,
                'video_url' => $report?->video_path ? route('admin.research.video', $report->id) : null,
            ]),
        ]);
    }

    private function staffAttribution(Collection $reports): Collection
    {
        $ids = $reports->flatMap(function (ResearchCheckIn $report): array {
            $case = $report->welfare_case ?? [];

            return [$case['decision_by_user_id'] ?? null, $case['return']['handled_by_user_id'] ?? null,
                ...collect($case['history'] ?? [])->pluck('actor_user_id')->all()];
        })->filter()->unique()->values();

        return User::query()->whereIn('id', $ids)->get(['id', 'first_name', 'last_name'])->keyBy('id');
    }

    private function caseHistory(array $events, Collection $users): string
    {
        return collect($events)->map(function (array $entry) use ($users): string {
            $actor = isset($entry['actor_user_id']) ? ($users->get($entry['actor_user_id'])?->full_name ?? 'Former user') : 'System';
            $title = match ($entry['event'] ?? null) {
                'survey_flagged' => 'Flagged from the check-in',
                'historical_survey_flagged' => 'Flagged during historical review',
                'manual_flag' => 'Manually flagged',
                'adopter_follow_up' => 'Follow-up submitted',
                default => ResolutionOutcome::tryFrom($entry['outcome'] ?? '')?->label() ?? 'Outcome recorded',
            };
            $note = $entry['note'] ?? $entry['reason'] ?? null;

            return sprintf('%s · %s · %s%s',
                ManilaTime::parseAndFormat($entry['at'] ?? null, 'M j, Y g:i A'), $actor, $title,
                filled($note) ? ': '.$note : '');
        })->implode("\n");
    }
}
