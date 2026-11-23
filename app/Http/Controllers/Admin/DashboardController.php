<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\ResolutionOutcome;
use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Services\PostAdoptionClock;
use App\Services\PostAdoptionScheduleService;
use App\Support\ResearchAdminRecords;
use App\Support\ResearchMode;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function __construct(private readonly PostAdoptionClock $clock) {}

    /**
     * GET /admin/dashboard
     */
    public function index(ResearchAdminRecords $research)
    {
        $workflowReadOnly = ResearchMode::ready();
        $pets = Pet::query()->when($workflowReadOnly, fn ($query) => $query->whereIn(
            'pets.id', fn ($catalog) => $catalog->select('pet_id')->from('research_catalog'),
        ));
        $totalPets = (clone $pets)->count();
        $availablePets = $pets->where('availability_status', 'Available')->count();
        $applications = $workflowReadOnly ? $research->applications() : AdoptionApplication::query();

        $now = CarbonImmutable::now(PostAdoptionScheduleService::TIMEZONE);
        $monthStartUtc = $now->startOfMonth()->utc();
        $monthEndUtc = $now->endOfMonth()->utc();
        $today = $this->clock->today()->toDateString();

        $adoptedThisMonth = (clone $applications)
            ->where('status', ApplicationStatus::Approved->value)
            ->when($workflowReadOnly, fn ($query) => $query->whereBetween('approved_at', [$monthStartUtc, $monthEndUtc]),
                fn ($query) => $query->where(function ($query) use ($monthStartUtc, $monthEndUtc) {
                    $query->whereBetween('queue_closed_at', [$monthStartUtc, $monthEndUtc])
                        ->orWhere(function ($fallback) use ($monthStartUtc, $monthEndUtc) {
                            $fallback->whereNull('queue_closed_at')
                                ->whereBetween('updated_at', [$monthStartUtc, $monthEndUtc]);
                        });
                }))
            ->count();

        $pendingApplications = (clone $applications)
            ->whereIn('status', [
                ApplicationStatus::Pending->value,
                ApplicationStatus::DocumentFlagged->value,
                ApplicationStatus::PrimaryCandidate->value,
                ApplicationStatus::Waitlisted->value,
                ApplicationStatus::UnderReview->value,
                ApplicationStatus::InterviewScheduled->value,
            ])
            ->count();

        $activeMonitoring = $workflowReadOnly
            ? $research->receivedHandovers()->whereRaw(
                '(select count(*) from research_checkins where research_checkins.placement_id = research_placements.id) < ?',
                [count(ResearchMode::MILESTONES)],
            )->whereDoesntHave('researchCheckIns', fn ($query) => $query
                ->where('resolution_outcome', ResolutionOutcome::PetReturned->value))->count()
            : PostAdoptionLog::query()
                ->afterCompletedHandover()
                ->beforeRecordedReturn()
                ->whereNull('submitted_date')
                ->count();

        $flaggedMonitoring = $workflowReadOnly ? $research->flaggedReports()->count() : PostAdoptionLog::query()
            ->afterCompletedHandover()
            ->where('is_flagged', true)
            ->whereNull('resolved_at')
            ->count();

        $pipeline = [
            'scheduled' => (clone $applications)->where('status', 'InterviewScheduled')->count(),
            'underreview' => (clone $applications)->where('status', 'UnderReview')->count(),
            'approved' => (clone $applications)->where('status', 'Approved')->count(),
            'rejected' => (clone $applications)->where('status', 'Rejected')->count(),
        ];

        $recentApplications = (clone $applications)->with(['user', 'pet'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        $overdueCheckIns = $workflowReadOnly ? collect() : PostAdoptionLog::query()
            ->afterCompletedHandover()
            ->beforeRecordedReturn()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->whereNull('submitted_date')
            ->whereDate('scheduled_date', '<', $today)
            ->orderBy('scheduled_date')
            ->take(5)
            ->get();

        $unresolvedFlags = $workflowReadOnly ? $research->flaggedCheckIns(limit: 5) : PostAdoptionLog::query()
            ->afterCompletedHandover()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->where('is_flagged', true)
            ->whereNull('resolved_at')
            ->orderBy('scheduled_date')
            ->take(5)
            ->get();

        $recentActivity = AuditLog::with('user')
            ->when($workflowReadOnly, fn ($query) => $query->where(fn ($activity) => $activity
                ->where('action', '!=', 'Pet Assessed')
                ->orWhereDoesntHave('user', fn ($actor) => $actor
                    ->where('is_active', false)->where('email', 'like', 'observer-%@example.invalid'))))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        return view('admin.dashboard.index', compact(
            'totalPets',
            'availablePets',
            'adoptedThisMonth',
            'pendingApplications',
            'activeMonitoring',
            'flaggedMonitoring',
            'pipeline',
            'recentApplications',
            'overdueCheckIns',
            'unresolvedFlags',
            'recentActivity',
        ));
    }
}
