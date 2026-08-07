<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Mail\StatusUpdateMail;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    // ─── Adopter: Submit Application ──────────────────────────────────────────

    /**
     * GET /applications/create/{pet}
     */
    public function create(Pet $pet)
    {
        abort_if(
            $pet->availability_status !== AvailabilityStatus::Available,
            422,
            'This pet is no longer available for adoption.'
        );

        return view('applications.create', compact('pet'));
    }

    /**
     * POST /applications
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pet_id'               => 'required|exists:pets,id',
            'motivation_statement' => 'required|string|max:2000',
            'housing_type'         => 'required|string|max:255',
            'income_range'         => 'required|string|max:255',
            'document'             => 'required|file|mimes:pdf,jpg,jpeg,png|max:40960',
        ]);

        $user = Auth::user();

        // Duplicate prevention — block if active application already exists
        $existing = AdoptionApplication::where('user_id', $user->id)
            ->where('pet_id', $validated['pet_id'])
            ->whereNotIn('status', [
                ApplicationStatus::Approved->value,
                ApplicationStatus::Rejected->value,
            ])->exists();

        if ($existing) {
            return back()->withErrors([
                'pet_id' => 'You already have an active application for this pet.',
            ]);
        }

        $documentPath = $request->file('document')->store('documents', 'public');

        $application = AdoptionApplication::create([
            'user_id'              => $user->id,
            'pet_id'               => $validated['pet_id'],
            'status'               => ApplicationStatus::Pending->value,
            'motivation_statement' => $validated['motivation_statement'],
            'housing_type'         => $validated['housing_type'],
            'income_range'         => $validated['income_range'],
            'document_path'        => $documentPath,
        ]);

        AuditLogService::log(
            $user->id,
            'Adoption Application Submitted',
            'AdoptionApplication',
            $application->id,
            "Application for pet ID {$application->pet_id}."
        );

        return redirect()->route('applications.mine')
            ->with('success', 'Your application has been submitted.');
    }

    /**
     * GET /applications/mine
     */
    public function mine()
    {
        $applications = AdoptionApplication::with('pet')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('applications.mine', compact('applications'));
    }

    // ─── Staff: Application Queue & Status Machine ────────────────────────────

    /**
     * GET /applications/queue
     */
    public function queue()
    {
        $applications = AdoptionApplication::with(['user', 'pet'])
            ->get()
            ->groupBy(fn($app) => $app->status->value);

        return view('applications.queue', compact('applications'));
    }

    /**
     * PATCH /applications/{application}/status
     */
    public function updateStatus(Request $request, AdoptionApplication $application)
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $newStatus = ApplicationStatus::from($validated['status']);
        $current   = $application->status;

        // State machine: only allow legal transitions
        $allowedTransitions = [
            ApplicationStatus::Pending->value            => [ApplicationStatus::UnderReview, ApplicationStatus::Rejected],
            ApplicationStatus::UnderReview->value        => [ApplicationStatus::InterviewScheduled, ApplicationStatus::Rejected],
            ApplicationStatus::InterviewScheduled->value => [ApplicationStatus::Approved, ApplicationStatus::Rejected],
        ];

        $allowed = $allowedTransitions[$current->value] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return back()->withErrors([
                'status' => "Cannot transition from {$current->value} to {$newStatus->value}.",
            ]);
        }

        if ($newStatus === ApplicationStatus::Approved) {
            DB::transaction(function () use ($application) {
                $application->update(['status' => ApplicationStatus::Approved->value]);

                // Mark pet as Adopted
                Pet::withoutGlobalScope('notArchived')
                    ->where('id', $application->pet_id)
                    ->update(['availability_status' => AvailabilityStatus::Adopted->value]);

                // Create 3 milestone PostAdoptionLog records
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
        } elseif ($newStatus === ApplicationStatus::UnderReview) {
            $application->update(['status' => ApplicationStatus::UnderReview->value]);
            Pet::withoutGlobalScope('notArchived')
                ->where('id', $application->pet_id)
                ->update(['availability_status' => AvailabilityStatus::Processing->value]);
        } else {
            $application->update(['status' => $newStatus->value]);
        }

        // Email adopter
        Mail::to($application->user)->send(new StatusUpdateMail($application));

        AuditLogService::log(
            Auth::id(),
            'Application Status Updated',
            'AdoptionApplication',
            $application->id,
            "Status changed to {$newStatus->value}."
        );

        return back()->with('success', 'Application status updated.');
    }

    /**
     * POST /applications/{application}/interview
     */
    public function scheduleInterview(Request $request, AdoptionApplication $application)
    {
        $request->validate([
            'interview_date' => 'required|date|after:now',
        ]);

        abort_if(
            !in_array($application->status, [ApplicationStatus::Pending, ApplicationStatus::UnderReview]),
            422,
            'Interview can only be scheduled for Pending or Under Review applications.'
        );

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
}
