<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function index()
    {
        $applications = AdoptionApplication::with(['pet', 'user'])->latest()->get();
        $volunteers = User::staff()->where('is_active', true)->orderBy('full_name')->get();

        // For each application, find the applicant's most recent OTHER completed
        // adoption (if any) to power the optional "Adoption Record History" row.
        $applications->each(function (AdoptionApplication $app) {
            $app->priorHistory = AdoptionApplication::with('pet')
                ->where('user_id', $app->user_id)
                ->where('id', '!=', $app->id)
                ->where('status', AdoptionApplication::STATUS_APPROVED)
                ->whereHas('checkIns')
                ->latest()
                ->first();
        });

        return view('admin.application.index', compact('applications', 'volunteers'));
    }

    public function scheduleInterview(Request $request)
    {
        $data = $request->validate([
            'application_id' => ['required', 'exists:adoption_applications,id'],
            'interview_date' => ['required', 'date', 'after_or_equal:today'],
            'interview_time' => ['required'],
            'conducted_by' => ['required', 'string'],
        ]);

        $application = AdoptionApplication::findOrFail($data['application_id']);
        $application->update([
            'interview_date' => $data['interview_date'],
            'interview_time' => $data['interview_time'],
            'conducted_by' => $data['conducted_by'],
            'status' => AdoptionApplication::STATUS_SCHEDULED,
        ]);

        AuditLog::record(Auth::user(), "scheduled an interview for {$application->first_name} {$application->last_name} ({$application->pet?->name})");

        return back()->with('toast', ['type' => 'success', 'message' => 'Interview scheduled.']);
    }

    public function saveNotes(Request $request, AdoptionApplication $application)
    {
        $data = $request->validate(['interview_notes' => ['required', 'string', 'max:3000']]);

        $application->update([
            'interview_notes' => $data['interview_notes'],
            'status' => AdoptionApplication::STATUS_UNDER_REVIEW,
        ]);

        AuditLog::record(Auth::user(), "recorded interview notes for {$application->first_name} {$application->last_name}");

        return back()->with('toast', ['type' => 'success', 'message' => 'Interview notes saved.']);
    }

    public function decide(Request $request, AdoptionApplication $application)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update([
            'status' => $data['decision'] === 'approved' ? AdoptionApplication::STATUS_APPROVED : AdoptionApplication::STATUS_REJECTED,
            'decision_remarks' => $data['decision_remarks'] ?? null,
        ]);

        if ($data['decision'] === 'approved') {
            $application->pet?->update(['status' => 'Adopted']);

            // Set up the 3-3-3 post-adoption monitoring schedule
            $adoptedOn = now();
            foreach ([
                ['ThreeDay', $adoptedOn->copy()->addDays(3)],
                ['ThreeWeek', $adoptedOn->copy()->addWeeks(3)],
                ['ThreeMonth', $adoptedOn->copy()->addMonths(3)],
            ] as [$milestone, $dueDate]) {
                CheckIn::create([
                    'user_id' => $application->user_id,
                    'pet_id' => $application->pet_id,
                    'application_id' => $application->id,
                    'milestone' => $milestone,
                    'due_date' => $dueDate,
                    'status' => CheckIn::STATUS_UPCOMING,
                ]);
            }
        }

        AuditLog::record(Auth::user(), "{$data['decision']} the application from {$application->first_name} {$application->last_name} for {$application->pet?->name}");

        return back()->with('toast', ['type' => 'success', 'message' => 'Application ' . $data['decision'] . '.']);
    }

    public function history(AdoptionApplication $application)
    {
        $prior = AdoptionApplication::with(['pet', 'checkIns.report'])
            ->where('user_id', $application->user_id)
            ->where('id', '!=', $application->id)
            ->where('status', AdoptionApplication::STATUS_APPROVED)
            ->whereHas('checkIns')
            ->latest()
            ->first();

        return view('admin.application._history-modal-content', ['prior' => $prior]);
    }

    public function document(AdoptionApplication $application)
    {
        abort_unless($application->document_path && Storage::disk('private')->exists($application->document_path), 404);

        return Storage::disk('private')->response($application->document_path);
    }
}
