<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Enums\Milestone;
use App\Models\ResearchPlacement;
use App\Services\Matching\ApplicantRankingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        return $this->handovers()->whereNotNull('released_at')->whereNotNull('received_at');
    }

    public function checkIns(): Collection
    {
        $placements = $this->receivedHandovers()->orderByDesc('received_at')->orderByDesc('id')->get();
        $reports = DB::table('research_checkins')->whereIn('placement_id', $placements->modelKeys())->get()->groupBy('placement_id');

        return $placements->flatMap(function (ResearchPlacement $placement) use ($reports) {
            $submissions = $reports->get($placement->id, collect())->keyBy('milestone');
            $next = collect(ResearchMode::MILESTONES)->first(fn ($key) => ! $submissions->has($key));

            return collect(ResearchMode::MILESTONES)->map(function (string $key) use ($placement, $submissions, $next) {
                $report = $submissions->get($key);
                $milestone = Milestone::from(str_replace('-', '_', $key).'s');
                $status = $report ? 'completed' : ($key === $next ? 'pending' : 'upcoming');

                return new Fluent([
                    'id' => $report?->id, 'application_id' => $placement->id, 'adoptionApplication' => $placement,
                    'user' => $placement->user, 'pet' => $placement->pet, 'milestone' => $milestone,
                    'milestone_display' => $milestone->label(), 'status_slug' => $status, 'status_display' => ucfirst($status),
                    'submitted_date' => $report ? CarbonImmutable::parse($report->submitted_at, 'UTC') : null,
                    'survey_data' => $report ? json_decode($report->answers, true) : [],
                    'video_path' => $report?->video_path,
                ]);
            });
        });
    }
}
