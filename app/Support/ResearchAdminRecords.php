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
        $ready = $placement->released_at !== null;
        $received = $ready && $placement->received_at !== null;
        $history = [['label' => 'Application approved', 'actor' => 'System', 'at' => $placement->approved_at->toISOString()]];
        if ($ready) {
            $history[] = ['label' => 'Handover marked ready', 'actor' => 'System', 'at' => $placement->released_at->toISOString()];
        }
        if ($received) {
            $history[] = ['label' => 'Receipt confirmation recorded', 'actor' => $placement->user?->full_name ?? 'Adopter', 'at' => $placement->received_at->toISOString()];
        }

        return new Fluent([
            'id' => $placement->id, 'code' => 'APP-'.$placement->id, 'application' => $placement,
            'pet' => $placement->pet, 'photo_url' => $placement->pet?->image_url,
            'adopter_name' => trim($placement->first_name.' '.$placement->last_name),
            'adopter_phone' => $placement->phone_number, 'adopter_email' => $placement->email, 'adopter_address' => $placement->address,
            'approved_at' => $placement->approved_at, 'released_at' => $placement->released_at, 'received_at' => $received ? $placement->received_at : null,
            'adopter_outcome' => $received ? 'received' : null, 'adopter_confirmed_at' => $received ? $placement->received_at : null,
            'history' => $history, 'transportIncidents' => collect(),
            'current_step' => $received ? 'monitoring' : ($ready ? 'awaiting' : 'approved'),
            'completed_steps' => ['approved' => true, 'handover_set' => $ready, 'awaiting' => $received, 'monitoring' => $received],
            'monitoring_locked' => ! $received,
            'status_badge' => match (true) {
                $received => ['label' => 'Monitoring active', 'class' => 'badge-completed', 'icon' => 'fa-solid fa-circle-check'],
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
                ]);
            });
        });
    }
}
