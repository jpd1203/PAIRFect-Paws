<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FlaggedCase;
use App\Support\AdminOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FlaggedCasesController extends Controller
{
    public function index()
    {
        $flaggedCases = FlaggedCase::with(['checkIn.pet', 'checkIn.user'])->latest()->get();

        return view('admin.flagged-cases.index', [
            'flaggedCases' => $flaggedCases,
            'options' => AdminOptions::class,
        ]);
    }

    public function markIntervention(Request $request, FlaggedCase $flaggedCase)
    {
        $data = $request->validate([
            'intervention_type' => ['required', Rule::in(AdminOptions::INTERVENTION_TYPES)],
            'intervention_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $flaggedCase->update([
            'marked_for_intervention' => true,
            'intervention_type' => $data['intervention_type'],
            'intervention_notes' => $data['intervention_notes'] ?? null,
        ]);

        AuditLog::record(Auth::user(), "marked case #{$flaggedCase->id} for intervention ({$data['intervention_type']})");

        return back()->with('toast', ['type' => 'success', 'message' => 'Case marked for intervention.']);
    }

    public function escalate(Request $request, FlaggedCase $flaggedCase)
    {
        $data = $request->validate(['escalation_reason' => ['required', 'string', 'max:1000']]);

        $flaggedCase->update(['is_escalated' => true, 'escalation_reason' => $data['escalation_reason']]);

        AuditLog::record(Auth::user(), "escalated flagged case #{$flaggedCase->id}");

        return back()->with('toast', ['type' => 'success', 'message' => 'Case escalated to supervisor.']);
    }

    public function sendReminder(FlaggedCase $flaggedCase)
    {
        AuditLog::record(Auth::user(), "sent a follow-up reminder for flagged case #{$flaggedCase->id}");

        return back()->with('toast', ['type' => 'success', 'message' => 'Reminder sent.']);
    }

    public function resolve(Request $request, FlaggedCase $flaggedCase)
    {
        $data = $request->validate(['resolution_notes' => ['nullable', 'string', 'max:2000']]);

        $flaggedCase->update([
            'resolved' => true,
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolved_at' => now(),
            'resolved_by' => Auth::user()->full_name,
        ]);

        $flaggedCase->checkIn?->update(['status' => \App\Models\CheckIn::STATUS_PENDING]);

        AuditLog::record(Auth::user(), "resolved flagged case #{$flaggedCase->id}");

        return back()->with('toast', ['type' => 'success', 'message' => 'Case resolved.']);
    }
}
