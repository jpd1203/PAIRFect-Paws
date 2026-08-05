<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\FlaggedCase;
use App\Models\Pet;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $totalPets = Pet::count();
        $availablePets = Pet::where('status', 'Available')->count();
        $pendingApplications = AdoptionApplication::where('status', AdoptionApplication::STATUS_PENDING)->count();
        $activeAdoptions = CheckIn::distinct('pet_id')->count('pet_id');

        $pipeline = [
            'scheduled' => AdoptionApplication::where('status', AdoptionApplication::STATUS_SCHEDULED)->count(),
            'underreview' => AdoptionApplication::where('status', AdoptionApplication::STATUS_UNDER_REVIEW)->count(),
            'approved' => AdoptionApplication::where('status', AdoptionApplication::STATUS_APPROVED)->count(),
            'rejected' => AdoptionApplication::where('status', AdoptionApplication::STATUS_REJECTED)->count(),
        ];

        $recentApplications = AdoptionApplication::with(['pet', 'user'])->latest()->take(5)->get();

        $overdueCheckIns = CheckIn::with('pet')->where('status', CheckIn::STATUS_OVERDUE)->take(5)->get();
        $unresolvedFlags = FlaggedCase::with('checkIn.pet')->where('resolved', false)->take(5)->get();

        $recentActivity = AuditLog::latest('timestamp')->take(3)->get();

        return view('admin.dashboard.index', compact(
            'totalPets', 'availablePets', 'pendingApplications', 'activeAdoptions',
            'pipeline', 'recentApplications', 'overdueCheckIns', 'unresolvedFlags', 'recentActivity'
        ));
    }
}
