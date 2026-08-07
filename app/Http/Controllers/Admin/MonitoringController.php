<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    /**
     * GET /admin/monitoring — group all logs into Compliant, Due Soon, Overdue
     */
    public function index()
    {
        $allLogs = PostAdoptionLog::with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->get();

        $compliant = $allLogs->filter(fn($log) => $log->submitted_date !== null);
        $dueSoon   = $allLogs->filter(fn($log) =>
            $log->submitted_date === null
            && Carbon::parse($log->scheduled_date)->isFuture()
            && Carbon::parse($log->scheduled_date)->diffInDays(Carbon::now()) <= 3
        );
        $overdue   = $allLogs->filter(fn($log) =>
            $log->submitted_date === null
            && Carbon::parse($log->scheduled_date)->isPast()
        );

        return view('admin.monitoring.index', compact('compliant', 'dueSoon', 'overdue'));
    }

    /**
     * GET /admin/monitoring/flagged — list all unresolved flagged logs
     */
    public function flagged()
    {
        $flagged = PostAdoptionLog::with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->where('flagged_for_review', true)
            ->whereNull('resolved_at')
            ->latest()
            ->get();

        return view('admin.monitoring.flagged', compact('flagged'));
    }

    /**
     * POST /admin/monitoring/flagged/{log}/resolve
     */
    public function resolve(Request $request, PostAdoptionLog $log)
    {
        $validated = $request->validate([
            'resolution_note' => 'required|string|max:2000',
        ]);

        $log->update([
            'resolution_note'    => $validated['resolution_note'],
            'resolved_at'        => now(),
            'resolved_by_user_id'=> Auth::id(),
        ]);

        AuditLogService::log(
            Auth::id(),
            'Post-Adoption Flag Resolved',
            'PostAdoptionLog',
            $log->id,
            $validated['resolution_note']
        );

        return back()->with('success', 'Case resolved successfully.');
    }
}
