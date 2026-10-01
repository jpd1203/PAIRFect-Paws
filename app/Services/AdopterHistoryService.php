<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Derives staff-only screening context from existing applications and check-ins. */
final class AdopterHistoryService
{
    /** @param Collection<int, AdoptionApplication> $applications
     *  @return array<int, array<string, mixed>> Indexed by the application being reviewed.
     */
    public function summariesForApplications(Collection $applications): array
    {
        $userIds = $applications->pluck('user_id')->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return [];
        }

        $historyByUser = AdoptionApplication::query()
            ->whereIn('user_id', $userIds)
            ->with('postAdoptionLogs')
            ->get()
            ->groupBy('user_id');

        $summaries = [];
        foreach ($applications as $application) {
            $previous = ($historyByUser->get($application->user_id) ?? collect())
                ->filter(fn (AdoptionApplication $past): bool => $past->created_at->lt($application->created_at)
                    || ($past->created_at->equalTo($application->created_at) && $past->id < $application->id));
            $summaries[$application->id] = $this->summarize($previous);
        }

        return $summaries;
    }

    /** @return array<string, mixed> */
    public function getSummary(User $adopter): array
    {
        return $this->summarize($adopter->adoptionApplications()->with('postAdoptionLogs')->get());
    }

    /** @param Collection<int, AdoptionApplication> $applications
     *  @return array<string, mixed>
     */
    public function summarize(Collection $applications): array
    {
        $logs = $applications->flatMap(
            fn (AdoptionApplication $application) => $application->status === ApplicationStatus::Approved
                ? $application->postAdoptionLogs
                : collect()
        );
        $today = app(PostAdoptionClock::class)->today()->toDateString();
        $flaggedReports = $logs->filter(fn (PostAdoptionLog $log): bool => $this->isFlaggedReport($log))->count();
        $missed = $logs->filter(fn (PostAdoptionLog $log): bool => $log->submitted_date === null
            && $log->scheduled_date !== null
            && $log->scheduled_date->toDateString() < $today)->count();
        $late = $logs->filter(fn (PostAdoptionLog $log): bool => $log->submitted_date !== null
            && $log->scheduled_date !== null
            && ManilaTime::at($log->submitted_date)->toDateString() > $log->scheduled_date->toDateString())->count();
        $unresolved = $logs->filter(fn (PostAdoptionLog $log): bool => $log->is_flagged && $log->resolved_at === null)->count();

        $status = match (true) {
            $flaggedReports >= config('adopter_history.administrative_review_flagged_reports', 2),
            $missed >= config('adopter_history.administrative_review_missed_checkins', 2) => 'administrative_review_required',
            $flaggedReports >= config('adopter_history.needs_review_flagged_reports', 1),
            $missed >= config('adopter_history.needs_review_missed_checkins', 1),
            $late >= config('adopter_history.needs_review_late_checkins', 2),
            $unresolved >= config('adopter_history.needs_review_unresolved_flags', 1) => 'needs_review',
            default => 'no_recorded_concerns',
        };

        $explanations = [];
        foreach ([
            'flagged welfare report' => $flaggedReports,
            'missed check-in' => $missed,
            'late check-in' => $late,
            'unresolved monitoring flag' => $unresolved,
        ] as $label => $count) {
            if ($count > 0) {
                $explanations[] = $count.' '.str($label)->plural($count);
            }
        }

        return [
            'previous_applications' => $applications->count(),
            'approved_placements' => $applications->where('status', ApplicationStatus::Approved)->count(),
            'rejected_applications' => $applications->where('status', ApplicationStatus::Rejected)->count(),
            'completed_checkins' => $logs->whereNotNull('submitted_date')->count(),
            'missed_checkins' => $missed,
            'late_checkins' => $late,
            'flagged_welfare_reports' => $flaggedReports,
            'unresolved_monitoring_flags' => $unresolved,
            'review_status' => $status,
            'review_label' => match ($status) {
                'administrative_review_required' => 'Administrative Review Required',
                'needs_review' => 'Needs Review',
                default => 'No Recorded Concerns',
            },
            'badge_class' => match ($status) {
                'administrative_review_required' => 'badge-flagged',
                'needs_review' => 'badge-overdue',
                default => 'badge-completed',
            },
            'explanations' => $explanations,
        ];
    }

    /** @param Collection<int, AdoptionApplication> $applications
     *  @return Collection<int, array<string, mixed>>
     */
    public function timeline(Collection $applications): Collection
    {
        $today = app(PostAdoptionClock::class)->today()->toDateString();
        $events = collect();

        foreach ($applications as $application) {
            $petName = $application->pet?->name ?? 'Unavailable pet record';
            $events->push([
                'date' => $application->created_at,
                'title' => "Application for {$petName}",
                'detail' => 'Submitted; current recorded status: '.$application->status_display,
                'reasons' => [],
            ]);

            if ($application->status !== ApplicationStatus::Approved) {
                continue;
            }
            foreach ($application->postAdoptionLogs as $log) {
                $missed = $log->submitted_date === null && $log->scheduled_date !== null
                    && $log->scheduled_date->toDateString() < $today;
                $late = $log->submitted_date !== null
                    && $log->scheduled_date !== null
                    && ManilaTime::at($log->submitted_date)->toDateString() > $log->scheduled_date->toDateString();
                $status = match (true) {
                    $log->is_flagged && $log->resolved_at === null => 'Flagged for review',
                    $log->is_flagged && $log->resolved_at !== null => 'Flag resolved',
                    $missed => 'Missed',
                    $late => 'Completed late',
                    $log->submitted_date !== null => 'Completed',
                    default => 'Upcoming',
                };
                $events->push([
                    'date' => $log->submitted_date
                        ?? ($log->scheduled_date
                            ? CarbonImmutable::parse($log->scheduled_date->toDateString(), PostAdoptionClock::TIMEZONE)
                            : $application->created_at),
                    'title' => $log->milestone_display.' for '.$petName,
                    'detail' => $status.'; scheduled '.($log->scheduled_date?->format('M j, Y') ?? 'date unavailable'),
                    'reasons' => $this->flagMessages($log),
                ]);
            }
        }

        return $events->sortByDesc(fn (array $event): int => $event['date']->getTimestamp())->values();
    }

    private function isFlaggedReport(PostAdoptionLog $log): bool
    {
        if (! $log->is_flagged || $log->submitted_date === null) {
            return false;
        }

        $reasons = $this->normalizedReasons($log);
        return $reasons === [] || collect($reasons)->contains(
            fn ($reason): bool => ! is_array($reason) || ($reason['code'] ?? null) !== 'missed_check_in'
        );
    }

    /** @return list<string> */
    private function flagMessages(PostAdoptionLog $log): array
    {
        return collect($this->normalizedReasons($log))
            ->map(fn ($reason) => is_array($reason) ? ($reason['message'] ?? null) : $reason)
            ->filter(fn ($message): bool => is_string($message) && trim($message) !== '')
            ->values()->all();
    }

    /** @return list<mixed> */
    private function normalizedReasons(PostAdoptionLog $log): array
    {
        $reasons = $log->flag_reasons ?? [];

        return $reasons !== [] && ! array_is_list($reasons) ? [$reasons] : $reasons;
    }
}
