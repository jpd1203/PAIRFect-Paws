<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Services\PostAdoptionClock;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function __construct(private readonly PostAdoptionClock $clock) {}

    /**
     * GET /admin/dashboard
     */
    public function index()
    {
        $totalPets = Pet::count();
        $availablePets = Pet::where('availability_status', 'Available')->count();

        $now = CarbonImmutable::now(PostAdoptionScheduleService::TIMEZONE);
        $monthStartUtc = $now->startOfMonth()->utc();
        $monthEndUtc = $now->endOfMonth()->utc();
        $today = $this->clock->today()->toDateString();

        $adoptedThisMonth = AdoptionApplication::query()
            ->where('status', ApplicationStatus::Approved->value)
            ->where(function ($query) use ($monthStartUtc, $monthEndUtc) {
                $query->whereBetween('queue_closed_at', [$monthStartUtc, $monthEndUtc])
                    ->orWhere(function ($fallback) use ($monthStartUtc, $monthEndUtc) {
                        $fallback->whereNull('queue_closed_at')
                            ->whereBetween('updated_at', [$monthStartUtc, $monthEndUtc]);
                    });
            })
            ->count();

        $pendingApplications = AdoptionApplication::query()
            ->whereIn('status', [
                ApplicationStatus::Pending->value,
                ApplicationStatus::DocumentFlagged->value,
                ApplicationStatus::PrimaryCandidate->value,
                ApplicationStatus::Waitlisted->value,
                ApplicationStatus::UnderReview->value,
                ApplicationStatus::InterviewScheduled->value,
            ])
            ->count();

        $activeMonitoring = PostAdoptionLog::query()
            ->whereNull('submitted_date')
            ->count();

        $flaggedMonitoring = PostAdoptionLog::query()
            ->where('is_flagged', true)
            ->whereNull('resolved_at')
            ->count();

        $pipeline = [
            'scheduled' => AdoptionApplication::where('status', 'InterviewScheduled')->count(),
            'underreview' => AdoptionApplication::where('status', 'UnderReview')->count(),
            'approved' => AdoptionApplication::where('status', 'Approved')->count(),
            'rejected' => AdoptionApplication::where('status', 'Rejected')->count(),
        ];

        $recentApplications = AdoptionApplication::with(['user', 'pet'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        $overdueCheckIns = PostAdoptionLog::query()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->whereNull('submitted_date')
            ->whereDate('scheduled_date', '<', $today)
            ->orderBy('scheduled_date')
            ->take(5)
            ->get();

        $unresolvedFlags = PostAdoptionLog::query()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->where('is_flagged', true)
            ->whereNull('resolved_at')
            ->orderBy('scheduled_date')
            ->take(5)
            ->get();

        $recentActivity = AuditLog::with('user')
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
            'recentActivity'
        ));
    }
}
