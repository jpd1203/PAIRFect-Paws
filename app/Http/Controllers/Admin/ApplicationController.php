<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\AdopterHistoryService;
use App\Services\DocumentVerificationNotificationService;
use App\Services\DocumentVerificationService;
use App\Services\EmailNotificationService;
use App\Services\ReservationQueueService;
use App\Services\Matching\ApplicantRankingService;
use App\Support\ManilaTime;
use App\Support\ApplicationListFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    public function __construct(
        private ReservationQueueService $reservationQueue,
        private DocumentVerificationNotificationService $documentNotifications,
        private DocumentVerificationService $documentVerification,
        private \App\Services\Matching\ApplicationMatchService $matching,
        private ApplicantRankingService $ranking,
        private EmailNotificationService $emailNotifications,
        private AdopterHistoryService $adopterHistory,
    ) {}

    /**
     * GET /admin/applications — list all applications with applicant/pet details
     */
    public function index(Request $request)
    {
        $filters = ApplicationListFilters::validate($request);
        // Start newest-first so pet groups and applications within each group retain recency order.
        $applications = ApplicationListFilters::apply(AdoptionApplication::with(['user', 'pet']), $filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $this->matching->refreshMany($applications);
        $historySummaries = $this->adopterHistory->summariesForApplications($applications);

        $petGroups = $applications->groupBy('pet_id');
        $petGroups->each(function ($petApplications) {
            $position = 0;
            $this->ranking->sort($petApplications)->each(
                function ($application) use (&$position) {
                    if ($this->ranking->isEligible($application)) {
                        $application->setAttribute('queue_position', ++$position);
                    }
                }
            );
        });

        // Keep applications for the same pet adjacent without changing their queue positions.
        $applications = $petGroups->flatMap(fn ($petApplications) => $petApplications->all())->values();

        $volunteers = User::whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('admin.application.index', compact('applications', 'volunteers', 'historySummaries', 'filters'));
    }

    /**
     * POST /admin/applications/{application}/interview — schedule an interview
     */
    public function scheduleInterview(Request $request, AdoptionApplication $application)
    {
        return $this->schedule($request, $application);
    }

    /**
     * POST /admin/applications/schedule — schedule a selected pending application.
     */
    public function scheduleSelectedInterview(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'required|integer|exists:adoption_applications,id',
        ]);

        $application = AdoptionApplication::with('user')->findOrFail($validated['application_id']);

        return $this->schedule($request, $application);
    }

    private function schedule(Request $request, AdoptionApplication $application)
    {
        $validated = $request->validate([
            'interview_date' => 'required|date_format:Y-m-d',
            'interview_time' => 'required|date_format:H:i',
            'staff_id' => 'required|integer|exists:users,id',
        ]);

        $scheduledAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['interview_date']} {$validated['interview_time']}",
            ManilaTime::timezone()
        );

        if ($scheduledAt->lte(ManilaTime::now())) {
            throw ValidationException::withMessages([
                'interview_date' => 'The interview date and time must be in the future.',
            ]);
        }

        $interviewer = User::whereKey($validated['staff_id'])
            ->where('is_active', true)
            ->whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
            ->first();

        if (! $interviewer) {
            throw ValidationException::withMessages([
                'staff_id' => 'Please select an active staff member or volunteer.',
            ]);
        }

        $wasRescheduled = $application->status === ApplicationStatus::InterviewScheduled
            && $application->is_primary_candidate
            && $application->interview_date !== null;

        $this->reservationQueue->schedule($application, $scheduledAt, $interviewer, Auth::id());

        return back()->with('success', $wasRescheduled
            ? 'Interview rescheduled. Updated notifications were queued for the adopter and interviewer.'
            : 'Interview scheduled and the pet is now soft-reserved. Notifications were queued.');
    }

    public function declineReschedule(AdoptionApplication $application)
    {
        DB::transaction(function () use ($application): void {
            $current = AdoptionApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($current->reschedule_status !== 'pending'
                || $current->status !== ApplicationStatus::InterviewScheduled
                || ! $current->is_primary_candidate) {
                throw ValidationException::withMessages([
                    'reschedule' => 'There is no pending reschedule request for this active interview.',
                ]);
            }
            $current->update([
                'reschedule_status' => 'declined',
                'reschedule_reviewed_at' => now(),
            ]);
            AuditLogService::log(Auth::id(), 'Interview Reschedule Declined', 'AdoptionApplication', $current->id);
        });

        $application->refresh()->loadMissing(['user', 'pet']);
        $this->emailNotifications->user(
            $application->user,
            "Interview reschedule request update - {$application->pet?->name}",
            'Interview reschedule request declined',
            [
                'Shelter staff could not approve your requested times.',
                'Your original interview remains scheduled for '.ManilaTime::format($application->interview_date, 'F j, Y \a\t g:i A').' (Asia/Manila).',
                'Please contact the shelter if you cannot attend.',
            ],
            'View My Applications',
            route('application.index'),
            'interview_reschedule_declined',
            $application->id,
        );

        return back()->with('success', 'Reschedule request declined. The original interview time remains in effect.');
    }

    /**
     * POST /admin/applications/{application}/notes
     */
    public function saveNotes(Request $request, AdoptionApplication $application)
    {
        $validated = $request->validate([
            'interview_notes' => 'required|string',
        ]);

        $this->reservationQueue->recordInterviewNotes(
            $application,
            $validated['interview_notes'],
            Auth::user()->full_name,
            Auth::id()
        );

        return back()->with('success', 'Interview notes saved and application moved to Under Review.');
    }

    /**
     * POST /admin/applications/{application}/decision — approve or reject
     */
    public function decision(Request $request, AdoptionApplication $application)
    {
        $validated = $request->validate([
            'decision' => 'required|in:Approved,Rejected',
            'decision_remarks' => 'nullable|string',
        ]);

        if ($validated['decision'] === 'Approved') {
            $this->reservationQueue->approve(
                $application,
                $validated['decision_remarks'] ?? null,
                Auth::id()
            );
        } else {
            $reason = trim((string) ($validated['decision_remarks'] ?? ''));

            $this->reservationQueue->rejectApplication(
                $application,
                $reason !== '' ? $reason : 'Application rejected during administrative review.',
                Auth::id()
            );
        }

        return back()->with('success', "Application {$validated['decision']}.");
    }

    public function queueOutcome(Request $request, AdoptionApplication $application)
    {
        $validated = $request->validate([
            'outcome' => 'required|in:Withdrawn,NoShow',
            'reason' => 'required|string|max:2000',
        ]);

        $outcome = ApplicationStatus::from($validated['outcome']);
        $promoted = $this->reservationQueue->resolvePrimary(
            $application,
            $outcome,
            $validated['reason'],
            Auth::id()
        );

        return back()->with('success', $promoted
            ? "Candidate marked {$outcome->value}; the next waitlisted applicant was promoted."
            : "Candidate marked {$outcome->value}; the queue is exhausted and the pet is available again.");
    }

    public function overridePrimary(Request $request, AdoptionApplication $application)
    {
        $validated = $request->validate([
            'override_reason' => 'required|string|min:10|max:2000',
        ]);

        $this->reservationQueue->overridePrimary($application, $validated['override_reason'], Auth::id());

        return back()->with('success', 'Administrative override recorded and the selected applicant was promoted.');
    }

    public function retryDocumentOcr(AdoptionApplication $application)
    {
        $retryableDocumentStatuses = [
            DocumentVerificationStatus::ManualReview,
            DocumentVerificationStatus::LegacyReview,
            DocumentVerificationStatus::NeedsResubmission,
        ];
        $retryableApplicationStatuses = [
            ApplicationStatus::Pending,
            ApplicationStatus::DocumentFlagged,
        ];

        if (! in_array($application->document_verification_status, $retryableDocumentStatuses, true)
            || ! in_array($application->status, $retryableApplicationStatuses, true)
            || $application->is_primary_candidate) {
            throw ValidationException::withMessages([
                'document' => 'OCR can only be retried for an inactive application awaiting document review.',
            ]);
        }

        $disk = $application->document_disk ?: 'local';
        $path = $application->document_path;
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages([
                'document' => 'The private document file could not be found.',
            ]);
        }

        $verification = $this->documentVerification->verify(
            $disk,
            $path,
            $application->document_mime_type ?: Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream',
            [
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                ...$application->ocr_address_payload,
            ]
        );

        $updatedApplication = DB::transaction(function () use (
            $application,
            $path,
            $retryableDocumentStatuses,
            $retryableApplicationStatuses,
            $verification
        ) {
            $locked = AdoptionApplication::lockForUpdate()->findOrFail($application->id);

            if ($locked->document_path !== $path
                || ! in_array($locked->document_verification_status, $retryableDocumentStatuses, true)
                || ! in_array($locked->status, $retryableApplicationStatuses, true)
                || $locked->is_primary_candidate) {
                throw ValidationException::withMessages([
                    'document' => 'The application changed while OCR was running. Refresh the page before retrying.',
                ]);
            }

            $pet = Pet::withoutGlobalScope('notArchived')
                ->whereKey($locked->pet_id)
                ->lockForUpdate()
                ->firstOrFail();
            $hasPrimary = AdoptionApplication::where('pet_id', $pet->id)
                ->where('is_primary_candidate', true)
                ->exists();

            $applicationStatus = match ($verification->status) {
                DocumentVerificationStatus::Verified => $hasPrimary || $pet->availability_status === AvailabilityStatus::SoftReserved
                        ? ApplicationStatus::Waitlisted
                        : ApplicationStatus::Pending,
                DocumentVerificationStatus::NeedsResubmission => ApplicationStatus::DocumentFlagged,
                default => ApplicationStatus::Pending,
            };

            $locked->update([
                'status' => $applicationStatus->value,
                'document_verification_status' => $verification->status->value,
                'document_type' => $verification->documentType,
                'ocr_extracted_text' => $verification->extractedText,
                'ocr_confidence' => $verification->ocrConfidence,
                'document_match_score' => $verification->matchScore,
                'document_verification_reasons' => $verification->reasons,
                'document_verified_at' => $verification->isVerified() ? now() : null,
            ]);

            AuditLogService::log(
                Auth::id(),
                'Document OCR Retried',
                'AdoptionApplication',
                $locked->id,
                "Google Vision retry result: {$verification->status->value}."
            );

            return $locked;
        });

        $this->documentNotifications->send($updatedApplication);

        return back()->with(
            $verification->isVerified() ? 'success' : 'warning',
            match ($verification->status) {
                DocumentVerificationStatus::Verified => 'Google Vision extracted and verified the document text.',
                DocumentVerificationStatus::NeedsResubmission => 'Google Vision ran, but the document needs replacement. Review the verification notes.',
                default => 'Google Vision did not run successfully. Review the provider note below before retrying.',
            }
        );
    }

    public function documentDecision(Request $request, AdoptionApplication $application)
    {
        $validated = $request->validate([
            'document_decision' => 'required|in:Verified,NeedsResubmission',
            'document_reason' => 'required|string|min:10|max:2000',
        ]);

        DB::transaction(function () use ($application, $validated) {
            $locked = AdoptionApplication::lockForUpdate()->findOrFail($application->id);
            $pet = Pet::withoutGlobalScope('notArchived')->lockForUpdate()->findOrFail($locked->pet_id);
            $decision = DocumentVerificationStatus::from($validated['document_decision']);

            if ($locked->is_primary_candidate || in_array($locked->status, [
                ApplicationStatus::Approved,
                ApplicationStatus::Rejected,
                ApplicationStatus::Withdrawn,
                ApplicationStatus::NoShow,
                ApplicationStatus::Closed,
            ], true)) {
                throw ValidationException::withMessages([
                    'document_decision' => 'Document verification can no longer be changed for this application.',
                ]);
            }
            if ($decision === DocumentVerificationStatus::NeedsResubmission
                && $locked->document_reupload_count >= config('document_verification.maximum_adopter_reuploads', 1)) {
                throw ValidationException::withMessages([
                    'document_decision' => 'The adopter has already used the one follow-up upload. Staff must verify the existing document or resolve the application manually.',
                ]);
            }

            $hasPrimary = AdoptionApplication::where('pet_id', $pet->id)
                ->where('is_primary_candidate', true)
                ->exists();

            $locked->update([
                'document_verification_status' => $decision->value,
                'document_verification_reasons' => [$validated['document_reason']],
                'document_verified_at' => $decision === DocumentVerificationStatus::Verified ? now() : null,
                'status' => $decision === DocumentVerificationStatus::Verified
                    ? ($hasPrimary || $pet->availability_status === AvailabilityStatus::SoftReserved
                        ? ApplicationStatus::Waitlisted->value
                        : ApplicationStatus::Pending->value)
                    : ApplicationStatus::DocumentFlagged->value,
            ]);

            AuditLogService::log(
                Auth::id(),
                'Manual Document Verification Decision',
                'AdoptionApplication',
                $locked->id,
                "Decision: {$decision->value}. Reason: {$validated['document_reason']}"
            );
        });

        $application->refresh();
        $this->documentNotifications->send($application);

        return back()->with('success', 'The document verification decision was recorded.');
    }
}
