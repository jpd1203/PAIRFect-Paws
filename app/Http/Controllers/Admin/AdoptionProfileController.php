<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\Milestone;
use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Services\PostAdoptionClock;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AdoptionProfileController extends Controller
{
    /**
     * List one profile per account that has submitted an adoption application.
     */
    public function index()
    {
        $adopters = User::query()
            ->whereHas('adoptionApplications')
            ->with([
                'adoptionApplications' => fn ($query) => $query
                    ->with('pet')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id'),
            ])
            ->withMax('adoptionApplications as latest_application_at', 'created_at')
            ->withMax('adoptionApplications as latest_application_id', 'id')
            ->orderByDesc('latest_application_at')
            ->orderByDesc('latest_application_id')
            ->orderByDesc('users.id')
            ->paginate(20);

        return view('admin.adopter-profile.index', compact('adopters'));
    }

    /**
     * Return the selected approved placement's monitoring history alongside a
     * compact record of every application attempt owned by the adopter.
     */
    public function history(Request $request, User $user)
    {
        $applications = $user->adoptionApplications()
            ->with('pet')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        abort_if($applications->isEmpty(), 404);

        $adopter = $user;
        $approvedPlacements = $applications
            ->filter(
                fn (AdoptionApplication $application): bool => $application->status === ApplicationStatus::Approved
            )
            ->sort(function (AdoptionApplication $left, AdoptionApplication $right): int {
                return [
                    $this->adoptionDate($right)->getTimestamp(),
                    $right->id,
                ] <=> [
                    $this->adoptionDate($left)->getTimestamp(),
                    $left->id,
                ];
            })
            ->values();

        $selectedPlacement = $this->selectedPlacement(
            $request,
            $approvedPlacements,
        );

        $isApprovedPlacement = $selectedPlacement?->status === ApplicationStatus::Approved;
        $adoptionDate = $isApprovedPlacement
            ? $this->adoptionDate($selectedPlacement)
            : null;

        $monitoringLogs = $isApprovedPlacement
            ? $this->orderedMonitoringLogs($selectedPlacement)
            : collect();

        $monitoringTimeline = $this->monitoringTimeline(
            $monitoringLogs,
            $adoptionDate,
        );
        $monitoringMetrics = $this->monitoringMetrics(
            $monitoringTimeline,
            $isApprovedPlacement,
        );

        return view('admin.adopter-profile._history-modal-content', compact(
            'adopter',
            'applications',
            'approvedPlacements',
            'selectedPlacement',
            'adoptionDate',
            'monitoringLogs',
            'monitoringTimeline',
            'monitoringMetrics',
        ));
    }

    /**
     * An explicit selection must be an approved placement owned by the route
     * adopter. With no selection, prefer the most recent approved placement.
     * Accounts without one keep a null selection; their applications remain
     * available separately for the compact attempt-history section.
     *
     * @param  Collection<int, AdoptionApplication>  $approvedPlacements
     */
    private function selectedPlacement(
        Request $request,
        Collection $approvedPlacements,
    ): ?AdoptionApplication {
        $requestedApplication = $request->query('application');

        if ($requestedApplication === null) {
            return $approvedPlacements->first();
        }

        abort_unless(
            (is_string($requestedApplication) || is_int($requestedApplication))
                && ctype_digit((string) $requestedApplication)
                && (int) $requestedApplication > 0,
            404,
        );

        $selectedPlacement = $approvedPlacements->firstWhere(
            'id',
            (int) $requestedApplication,
        );

        // Selecting through the account-owned collection prevents another
        // adopter's placement (or a non-approved attempt) from being exposed.
        abort_if($selectedPlacement === null, 404);

        return $selectedPlacement;
    }

    /**
     * @return Collection<int, PostAdoptionLog>
     */
    private function orderedMonitoringLogs(AdoptionApplication $placement): Collection
    {
        $order = $this->milestoneOrder();

        return $placement->postAdoptionLogs()
            ->get()
            ->sortBy(
                fn (PostAdoptionLog $log): int => $order[$log->milestone->value] ?? PHP_INT_MAX
            )
            ->values();
    }

    /**
     * Produce exactly one display row for every required 3-3-3 milestone.
     * Missing database logs are represented without writing from this GET
     * request; their official target date is still derived from the adoption.
     *
     * @param  Collection<int, PostAdoptionLog>  $monitoringLogs
     * @return Collection<int, array<string, mixed>>
     */
    private function monitoringTimeline(
        Collection $monitoringLogs,
        ?CarbonImmutable $adoptionDate,
    ): Collection {
        $today = app(PostAdoptionClock::class)->today();
        $logsByMilestone = $monitoringLogs->keyBy(
            fn (PostAdoptionLog $log): string => $log->milestone->value
        );

        return collect($this->milestones())->map(function (Milestone $milestone) use (
            $adoptionDate,
            $logsByMilestone,
            $today,
        ): array {
            /** @var PostAdoptionLog|null $log */
            $log = $logsByMilestone->get($milestone->value);
            $targetDate = $log?->scheduled_date
                ? CarbonImmutable::parse(
                    $log->scheduled_date->toDateString(),
                    PostAdoptionClock::TIMEZONE,
                )->startOfDay()
                : $this->targetDate($adoptionDate, $milestone);
            $isSubmitted = $log?->submitted_date !== null;
            $hasUnresolvedFlag = (bool) ($log?->is_flagged && $log->resolved_at === null);

            [$statusSlug, $statusLabel] = $this->timelineStatus(
                $targetDate,
                $today,
                $isSubmitted,
                $hasUnresolvedFlag,
            );

            return [
                'milestone' => $milestone,
                'milestone_value' => $milestone->value,
                'short_label' => $milestone->shortLabel(),
                'label' => $milestone->label(),
                'target_date' => $targetDate,
                'scheduled_date' => $targetDate,
                'log' => $log,
                'log_id' => $log?->id,
                'submitted_date' => $log?->submitted_date,
                'is_submitted' => $isSubmitted,
                'has_unresolved_flag' => $hasUnresolvedFlag,
                'status_slug' => $statusSlug,
                'status_label' => $statusLabel,
                'badge_class' => 'badge-'.$statusSlug,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $timeline
     * @return array<string, mixed>
     */
    private function monitoringMetrics(Collection $timeline, bool $hasApprovedPlacement): array
    {
        $requiredReports = count($this->milestones());
        $filedReports = $timeline->where('is_submitted', true)->count();
        $pendingReports = $timeline->where('status_slug', 'pending')->count();
        $upcomingReports = $timeline->where('status_slug', 'upcoming')->count();
        $overdueReports = $timeline->where('status_slug', 'overdue')->count();
        $flaggedReports = $timeline->where('has_unresolved_flag', true)->count();

        $overallStatus = match (true) {
            $flaggedReports > 0 => 'needs_review',
            $overdueReports > 0 => 'overdue',
            $filedReports === $requiredReports => 'completed',
            $filedReports > 0 => 'in_progress',
            $pendingReports > 0 => 'pending',
            default => 'scheduled',
        };

        [$overallStatusLabel, $headerBadgeLabel, $headerBadgeClass] = match ($overallStatus) {
            'needs_review' => ['Needs review', 'Needs review', 'badge-flagged'],
            'overdue' => ['Overdue', 'Overdue', 'badge-overdue'],
            'completed' => ['Completed', 'Complete', 'badge-completed'],
            'in_progress' => ['In progress', 'In Progress', 'badge-pending'],
            'pending' => ['Pending', 'Pending', 'badge-pending'],
            default => ['Scheduled', 'Scheduled', 'badge-upcoming'],
        };

        return [
            'required_reports' => $requiredReports,
            'filed_reports' => $filedReports,
            'reports_fraction' => $filedReports.'/'.$requiredReports,
            'pending_reports' => $pendingReports,
            'upcoming_reports' => $upcomingReports,
            'overdue_reports' => $overdueReports,
            'flagged_reports' => $flaggedReports,
            'has_unresolved_flags' => $flaggedReports > 0,
            'has_approved_placement' => $hasApprovedPlacement,
            'is_complete' => $filedReports === $requiredReports,
            'overall_status' => $overallStatus,
            'overall_status_slug' => $overallStatus,
            'overall_status_label' => $overallStatusLabel,
            'header_badge_label' => $headerBadgeLabel,
            'header_badge_class' => $headerBadgeClass,
        ];
    }

    /**
     * @return array{string, string}
     */
    private function timelineStatus(
        ?CarbonImmutable $targetDate,
        CarbonImmutable $today,
        bool $isSubmitted,
        bool $hasUnresolvedFlag,
    ): array {
        if ($hasUnresolvedFlag) {
            return ['flagged', 'Flagged'];
        }

        if ($isSubmitted) {
            return ['completed', 'Completed'];
        }

        if ($targetDate === null) {
            return ['upcoming', 'Not scheduled'];
        }

        if ($targetDate->lt($today)) {
            return ['overdue', 'Overdue'];
        }

        if ($today->diffInDays($targetDate) <= 3) {
            return ['pending', 'Pending'];
        }

        return ['upcoming', 'Upcoming'];
    }

    private function targetDate(
        ?CarbonImmutable $adoptionDate,
        Milestone $milestone,
    ): ?CarbonImmutable {
        if ($adoptionDate === null) {
            return null;
        }

        return app(PostAdoptionScheduleService::class)->targetDate($adoptionDate, $milestone);
    }

    private function adoptionDate(AdoptionApplication $application): CarbonImmutable
    {
        return app(PostAdoptionScheduleService::class)->adoptionDate($application);
    }

    /** @return list<Milestone> */
    private function milestones(): array
    {
        return [
            Milestone::ThreeDays,
            Milestone::ThreeWeeks,
            Milestone::ThreeMonths,
        ];
    }

    /** @return array<string, int> */
    private function milestoneOrder(): array
    {
        return array_flip(array_map(
            fn (Milestone $milestone): string => $milestone->value,
            $this->milestones(),
        ));
    }

    /**
     * GET /admin/adoption-profiles/{application}/document — redirect to document
     */
    public function document(AdoptionApplication $application)
    {
        abort_if(! $application->document_path, 404);

        $disk = $application->document_disk ?: 'public';
        abort_unless(Storage::disk($disk)->exists($application->document_path), 404);

        return Storage::disk($disk)->response(
            $application->document_path,
            $application->document_original_name ?: basename($application->document_path),
            [
                'Content-Type' => $application->document_mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function verification(AdoptionApplication $application)
    {
        return view('admin.application.document-verification', compact('application'));
    }
}
