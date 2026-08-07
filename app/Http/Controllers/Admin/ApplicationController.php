<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Mail\StatusUpdateMail;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Enums\Milestone;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ApplicationController extends Controller
{
    /**
     * GET /admin/applications — list all applications with applicant/pet details
     */
    public function index()
    {
        $applications = AdoptionApplication::with(['user', 'pet'])
            ->latest()
            ->paginate(20);

        return view('admin.application.index', compact('applications'));
    }

    /**
     * POST /admin/applications/{application}/interview — schedule an interview
     */
    public function scheduleInterview(Request $request, AdoptionApplication $application)
    {
        $request->validate([
            'interview_date' => 'required|date|after:now',
        ]);

        $application->update([
            'status'         => ApplicationStatus::InterviewScheduled->value,
            'interview_date' => $request->interview_date,
        ]);

        Mail::to($application->user)->send(new StatusUpdateMail($application));

        AuditLogService::log(
            Auth::id(),
            'Interview Scheduled',
            'AdoptionApplication',
            $application->id,
            "Interview set for {$request->interview_date}."
        );

        return back()->with('success', 'Interview scheduled.');
    }

    /**
     * POST /admin/applications/{application}/decision — approve or reject
     */
    public function decision(Request $request, AdoptionApplication $application)
    {
        $validated = $request->validate([
            'decision' => 'required|in:Approved,Rejected',
        ]);

        if ($validated['decision'] === 'Approved') {
            DB::transaction(function () use ($application) {
                $application->update(['status' => ApplicationStatus::Approved->value]);

                Pet::withoutGlobalScope('notArchived')
                    ->where('id', $application->pet_id)
                    ->update(['availability_status' => AvailabilityStatus::Adopted->value]);

                $schedules = [
                    Milestone::ThreeDays   => Carbon::now()->addDays(3),
                    Milestone::ThreeWeeks  => Carbon::now()->addWeeks(3),
                    Milestone::ThreeMonths => Carbon::now()->addMonths(3),
                ];

                foreach ($schedules as $milestone => $date) {
                    PostAdoptionLog::create([
                        'adoption_application_id' => $application->id,
                        'milestone'               => $milestone->value,
                        'scheduled_date'          => $date->toDateString(),
                        'flagged_for_review'       => false,
                        'reminders_sent'           => 0,
                        'version'                  => 1,
                    ]);
                }
            });
        } else {
            $application->update(['status' => ApplicationStatus::Rejected->value]);
        }

        Mail::to($application->user)->send(new StatusUpdateMail($application));

        AuditLogService::log(
            Auth::id(),
            'Application Status Updated',
            'AdoptionApplication',
            $application->id,
            "Decision: {$validated['decision']}."
        );

        return back()->with('success', "Application {$validated['decision']}.");
    }
}
