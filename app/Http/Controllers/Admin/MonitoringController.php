<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\FlaggedCase;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    public function index()
    {
        $checkIns = CheckIn::with(['pet', 'user', 'report'])->orderBy('due_date')->get();

        return view('admin.monitoring.index', compact('checkIns'));
    }

    public function sendReminder(CheckIn $checkIn)
    {
        // In production this would dispatch an email/SMS notification job.
        AuditLog::record(Auth::user(), "sent a reminder to {$checkIn->user?->full_name} for {$checkIn->pet?->name}'s {$checkIn->milestone_display}");

        return back()->with('toast', ['type' => 'success', 'message' => 'Reminder sent.']);
    }

    public function flagAndNotify(CheckIn $checkIn)
    {
        $checkIn->update(['status' => CheckIn::STATUS_FLAGGED]);

        FlaggedCase::firstOrCreate(
            ['check_in_id' => $checkIn->id],
            ['description' => "{$checkIn->milestone_display} flagged for follow-up"]
        );

        AuditLog::record(Auth::user(), "flagged {$checkIn->pet?->name}'s {$checkIn->milestone_display} for review");

        return back()->with('toast', ['type' => 'success', 'message' => 'Case flagged and moved to Flagged Cases.']);
    }
}
