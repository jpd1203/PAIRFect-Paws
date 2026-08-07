<?php

namespace App\Http\Controllers;

use App\Mail\WelfareReportReceiptMail;
use App\Models\AdoptionApplication;
use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;
use App\Services\FlagEvaluationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MonitoringController extends Controller
{
    /**
     * GET /monitoring/my-checkins
     */
    public function myCheckins()
    {
        // Get all adoption_applications for the current user that are Approved
        $applicationIds = AdoptionApplication::where('user_id', Auth::id())
            ->where('status', 'Approved')
            ->pluck('id');

        $logs = PostAdoptionLog::with('adoptionApplication.pet')
            ->whereIn('adoption_application_id', $applicationIds)
            ->orderBy('scheduled_date')
            ->get()
            ->map(function (PostAdoptionLog $log) {
                $log->display_status = $this->computeDisplayStatus($log);
                return $log;
            });

        return view('monitoring.my-checkins', compact('logs'));
    }

    /**
     * GET /monitoring/reports/{log}/create
     */
    public function createReport(PostAdoptionLog $log)
    {
        // Ownership check — this log must belong to the current user
        $belongsToUser = AdoptionApplication::where('id', $log->adoption_application_id)
            ->where('user_id', Auth::id())
            ->exists();

        abort_if(!$belongsToUser, 403);
        abort_if($log->submitted_date !== null, 422, 'This report has already been submitted.');

        return view('monitoring.create', compact('log'));
    }

    /**
     * POST /monitoring/reports/{log}
     */
    public function submitReport(Request $request, PostAdoptionLog $log, FlagEvaluationService $flagService)
    {
        // Ownership check
        $belongsToUser = AdoptionApplication::where('id', $log->adoption_application_id)
            ->where('user_id', Auth::id())
            ->exists();

        abort_if(!$belongsToUser, 403);
        abort_if($log->submitted_date !== null, 422, 'Already submitted.');

        $validated = $request->validate([
            'pet_current_status'     => 'required|in:Good,Fair,Poor',
            'behavioral_observations'=> 'nullable|string|max:2000',
            'living_conditions'      => 'nullable|string|max:2000',
            'eating_habits'          => 'nullable|string|max:2000',
            'vet_visit_details'      => 'nullable|string|max:2000',
            'concerns'               => 'nullable|string|max:2000',
            'photo'                  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:40960',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('welfare-photos', 'public');
        }

        $log->update([
            'submitted_date'          => now(),
            'pet_current_status'      => $validated['pet_current_status'],
            'behavioral_observations' => $validated['behavioral_observations'] ?? null,
            'living_conditions'       => $validated['living_conditions'] ?? null,
            'eating_habits'           => $validated['eating_habits'] ?? null,
            'vet_visit_details'       => $validated['vet_visit_details'] ?? null,
            'concerns'                => $validated['concerns'] ?? null,
            'photo_path'              => $photoPath,
        ]);

        // Auto-flag evaluation
        $flagService->evaluate($log->id);

        // Receipt email
        Mail::to(Auth::user())->send(new WelfareReportReceiptMail($log));

        AuditLogService::log(
            Auth::id(),
            'Post-Adoption Welfare Report Submitted',
            'PostAdoptionLog',
            $log->id,
            "Milestone: {$log->milestone->value}."
        );

        return redirect()->route('monitoring.my-checkins')
            ->with('success', 'Your welfare report has been submitted. Thank you!');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function computeDisplayStatus(PostAdoptionLog $log): string
    {
        if ($log->submitted_date) {
            return 'Submitted';
        }
        if (Carbon::parse($log->scheduled_date)->isPast()) {
            return 'Overdue';
        }
        return 'Pending';
    }
}
