<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\PostAdoptionLog;

class DashboardController extends Controller
{
    /**
     * GET /admin/dashboard
     */
    public function index()
    {
        $stats = [
            'total_animals'        => Pet::count(),
            'available_animals'    => Pet::where('availability_status', 'Available')->count(),
            'pending_applications' => AdoptionApplication::where('status', 'UnderReview')->count(),
            'active_monitoring'    => PostAdoptionLog::count(),
            'flagged_count'        => PostAdoptionLog::where('flagged_for_review', true)->whereNull('resolved_at')->count(),
        ];

        $recentApplications = AdoptionApplication::with(['user', 'pet'])
            ->latest()
            ->take(5)
            ->get();

        $postAdoptionAlerts = PostAdoptionLog::with(['adoptionApplication.pet', 'adoptionApplication.user'])
            ->where('flagged_for_review', true)
            ->whereNull('resolved_at')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard.index', compact('stats', 'recentApplications', 'postAdoptionAlerts'));
    }
}
