<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Mail\StatusUpdateMail;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\User;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReservationQueueService
{
    private const TERMINAL_STATUSES = [
        'Approved', 'Rejected', 'Withdrawn', 'NoShow', 'Closed',
    ];

    public function __construct(
        private readonly PostAdoptionScheduleService $postAdoptionSchedule,
        private readonly EmailNotificationService $emailNotifications,
        private readonly HandoverService $handovers,
    ) {}

    public function schedule(
        AdoptionApplication $application,
        Carbon $scheduledAt,
        User $interviewer,
        ?int $actorId
    ): AdoptionApplication {
        // Interview form values are entered in Asia/Manila. Normalize the
        // instant before persistence; mail and UI formatting convert it back
        // at the presentation boundary.
        $scheduledAt = $scheduledAt->copy()->utc();

        $result = DB::transaction(function () use ($application, $scheduledAt, $interviewer, $actorId): array {
            $pet = Pet::withoutGlobalScope('notArchived')->lockForUpdate()->findOrFail($application->pet_id);
            $candidate = AdoptionApplication::lockForUpdate()->findOrFail($application->id);

            $isReschedule = $candidate->status === ApplicationStatus::InterviewScheduled
                && $candidate->is_primary_candidate;

            if (! $isReschedule && ! in_array($candidate->status, [ApplicationStatus::Pending, ApplicationStatus::PrimaryCandidate, ApplicationStatus::UnderReview], true)) {
                throw ValidationException::withMessages([
                    'application_id' => 'Only a document-verified pending application, an application under review, a promoted primary candidate, or the currently scheduled candidate can be scheduled.',
                ]);
            }

            if (! in_array($candidate->document_verification_status, [
                DocumentVerificationStatus::Verified,
                DocumentVerificationStatus::LegacyReview,
            ], true)) {
                throw ValidationException::withMessages([
                    'application_id' => 'The supporting document must be verified before an interview can be scheduled.',
                ]);
            }

            if (! $isReschedule) {
                $firstEligible = AdoptionApplication::where('pet_id', $pet->id)
                    ->whereIn('status', [ApplicationStatus::Pending->value, ApplicationStatus::UnderReview->value, ApplicationStatus::PrimaryCandidate->value])
                    ->whereIn('document_verification_status', [
                        DocumentVerificationStatus::Verified->value,
                        DocumentVerificationStatus::LegacyReview->value,
                    ])
                    ->whereNotIn('status', self::TERMINAL_STATUSES)
                    ->orderByRaw('knn_score IS NULL, knn_score ASC')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (($candidate->status === ApplicationStatus::Pending || $candidate->status === ApplicationStatus::UnderReview) && $firstEligible?->id !== $candidate->id) {
                    throw ValidationException::withMessages([
                        'application_id' => 'The highest scoring eligible application must be scheduled first. Use an administrative override after the queue is active if required.',
                    ]);
                }
            }

            $existingPrimary = AdoptionApplication::where('pet_id', $pet->id)
                ->where('is_primary_candidate', true)
                ->where('id', '!=', $candidate->id)
                ->whereNotIn('status', self::TERMINAL_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($existingPrimary) {
                throw ValidationException::withMessages([
                    'application_id' => 'This pet already has a primary candidate. Use an administrative override to change candidates.',
                ]);
            }

            $previousInterviewDate = $candidate->interview_date?->copy();

            $candidate->update([
                'status' => ApplicationStatus::InterviewScheduled->value,
                'is_primary_candidate' => true,
                'interview_date' => $scheduledAt,
                'conducted_by' => $interviewer->full_name,
                'admin_review_flagged_at' => null,
            ]);

            $newlyWaitlisted = AdoptionApplication::where('pet_id', $pet->id)
                ->where('id', '!=', $candidate->id)
                ->whereIn('status', [ApplicationStatus::Pending->value, ApplicationStatus::UnderReview->value])
                ->where('document_verification_status', DocumentVerificationStatus::Verified->value)
                ->lockForUpdate()
                ->get();

            if ($newlyWaitlisted->isNotEmpty()) {
                AdoptionApplication::whereKey($newlyWaitlisted->modelKeys())
                    ->update(['status' => ApplicationStatus::Waitlisted->value]);
            }

            $pet->update(['availability_status' => AvailabilityStatus::SoftReserved->value]);

            AuditLogService::log(
                $actorId,
                'Interview Scheduled — Pet Soft-Reserved',
                'AdoptionApplication',
                $candidate->id,
                'Interview set for '.ManilaTime::format($scheduledAt, 'Y-m-d H:i')
                    ." Asia/Manila with {$interviewer->full_name}; pet ID {$pet->id} soft-reserved."
            );

            return [
                'event' => $isReschedule ? 'interview_rescheduled' : 'interview_scheduled',
                'previous_interview_date' => $previousInterviewDate
                    ? ManilaTime::format($previousInterviewDate, 'F j, Y \\a\\t g:i A')
                    : null,
                'waitlisted_ids' => $newlyWaitlisted->modelKeys(),
            ];
        });

        $application->refresh()->loadMissing(['user', 'pet']);
        $this->sendStatusEmail($application, $result['event'], $result['previous_interview_date']);
        $this->emailNotifications->user(
            $interviewer,
            $result['event'] === 'interview_rescheduled'
                ? "Interview rescheduled - application #{$application->id}"
                : "Interview assigned - application #{$application->id}",
            $result['event'] === 'interview_rescheduled' ? 'An assigned interview was rescheduled' : 'An interview was assigned to you',
            [
                "Application #{$application->id} for {$application->pet?->name} is scheduled for ".ManilaTime::format($scheduledAt, 'F j, Y \\a\\t g:i A').' (Asia/Manila).',
                'Sign in to the staff application queue for applicant details.',
            ],
            'Open Applications',
            route('admin.applications.index'),
            'interview_assignment_staff',
            $application->id,
        );

        AdoptionApplication::with(['user', 'pet'])
            ->whereKey($result['waitlisted_ids'])
            ->get()
            ->each(fn (AdoptionApplication $waitlisted) => $this->sendStatusEmail($waitlisted, 'application_waitlisted'));

        return $application;
    }

    public function resolvePrimary(
        AdoptionApplication $application,
        ApplicationStatus $outcome,
        string $reason,
        ?int $actorId
    ): ?AdoptionApplication {
        if (! in_array($outcome, [ApplicationStatus::Rejected, ApplicationStatus::Withdrawn, ApplicationStatus::NoShow], true)) {
            throw new \InvalidArgumentException('Invalid queue outcome.');
        }

        $promoted = DB::transaction(function () use ($application, $outcome, $reason, $actorId) {
            $candidate = AdoptionApplication::lockForUpdate()->findOrFail($application->id);
            $pet = Pet::withoutGlobalScope('notArchived')->lockForUpdate()->findOrFail($candidate->pet_id);

            if (! $candidate->is_primary_candidate) {
                throw ValidationException::withMessages([
                    'application_id' => 'Only the current primary candidate can be removed from the active queue.',
                ]);
            }

            if ($outcome === ApplicationStatus::Rejected && $candidate->status !== ApplicationStatus::UnderReview) {
                throw ValidationException::withMessages([
                    'decision' => 'A rejection decision can only be recorded after interview review.',
                ]);
            }
            if ($outcome === ApplicationStatus::NoShow && $candidate->status !== ApplicationStatus::InterviewScheduled) {
                throw ValidationException::withMessages([
                    'outcome' => 'Only a scheduled interview can be marked as a no-show.',
                ]);
            }

            $candidate->update([
                'status' => $outcome->value,
                'is_primary_candidate' => false,
                'decision_remarks' => $reason,
                'admin_review_flagged_at' => null,
            ]);

            AuditLogService::log(
                $actorId,
                "Primary Candidate {$outcome->value}",
                'AdoptionApplication',
                $candidate->id,
                $reason
            );

            return $this->promoteNextLocked($pet, $actorId, "Promoted after application {$candidate->id} became {$outcome->value}.");
        });

        $application->refresh()->loadMissing(['user', 'pet']);
        $this->sendStatusEmail(
            $application,
            $application->status === ApplicationStatus::Rejected ? 'application_rejected' : 'status_updated',
        );

        if ($promoted) {
            $this->notifyPromotion($promoted);
        }

        return $promoted;
    }

    /**
     * Reject an application during review or remove an active primary candidate.
     *
     * An application under review or awaiting a document update is terminated
     * without changing the pet or any other application. Rejecting the current
     * primary candidate advances (or exhausts) the existing reservation queue.
     */
    public function rejectApplication(
        AdoptionApplication $application,
        string $reason,
        ?int $actorId
    ): ?AdoptionApplication {
        $promoted = DB::transaction(function () use ($application, $reason, $actorId) {
            // This rejection path locks the pet before changing an application so
            // concurrent schedule/reject actions for this animal are serialized.
            $pet = Pet::withoutGlobalScope('notArchived')
                ->whereKey($application->pet_id)
                ->lockForUpdate()
                ->firstOrFail();
            $candidate = AdoptionApplication::whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isPreInterviewReview = ! $candidate->is_primary_candidate
                && in_array($candidate->status, [ApplicationStatus::Pending, ApplicationStatus::UnderReview], true);
            $isAwaitingDocumentUpdate = ! $candidate->is_primary_candidate
                && $candidate->status === ApplicationStatus::DocumentFlagged
                && $candidate->document_verification_status === DocumentVerificationStatus::NeedsResubmission;
            $isActivePrimary = $candidate->is_primary_candidate
                && in_array($candidate->status, [
                    ApplicationStatus::PrimaryCandidate,
                    ApplicationStatus::UnderReview,
                ], true);

            if (! $isPreInterviewReview && ! $isAwaitingDocumentUpdate && ! $isActivePrimary) {
                throw ValidationException::withMessages([
                    'decision' => 'Only an application requiring a document update, a verified application under review, or an active primary candidate can be rejected.',
                ]);
            }

            if ($isPreInterviewReview && ! in_array($candidate->document_verification_status, [
                DocumentVerificationStatus::Verified,
                DocumentVerificationStatus::LegacyReview,
            ], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'The supporting document must be verified before this application can be rejected during review.',
                ]);
            }

            $candidate->update([
                'status' => ApplicationStatus::Rejected->value,
                'is_primary_candidate' => false,
                'decision_remarks' => $reason,
                'admin_review_flagged_at' => null,
            ]);

            AuditLogService::log(
                $actorId,
                match (true) {
                    $isActivePrimary => 'Primary Candidate Rejected',
                    $isAwaitingDocumentUpdate => 'Application Rejected During Document Update',
                    default => 'Application Rejected Before Interview',
                },
                'AdoptionApplication',
                $candidate->id,
                $reason
            );

            if (! $isActivePrimary) {
                return null;
            }

            return $this->promoteNextLocked(
                $pet,
                $actorId,
                "Promoted after application {$candidate->id} was rejected."
            );
        });

        $application->refresh()->loadMissing(['user', 'pet']);
        $this->sendStatusEmail($application, 'application_rejected');

        if ($promoted) {
            $this->notifyPromotion($promoted);
        }

        return $promoted;
    }

    public function recordInterviewNotes(
        AdoptionApplication $application,
        string $notes,
        string $conductedBy,
        ?int $actorId
    ): AdoptionApplication {
        DB::transaction(function () use ($application, $notes, $conductedBy, $actorId) {
            $candidate = AdoptionApplication::lockForUpdate()->findOrFail($application->id);
            $pet = Pet::withoutGlobalScope('notArchived')->lockForUpdate()->findOrFail($candidate->pet_id);

            if ($candidate->status !== ApplicationStatus::InterviewScheduled || ! $candidate->is_primary_candidate) {
                throw ValidationException::withMessages([
                    'interview_notes' => 'Interview notes can only be added for the active primary candidate.',
                ]);
            }

            $candidate->update([
                'interview_notes' => $notes,
                'conducted_by' => $candidate->conducted_by ?: $conductedBy,
                'status' => ApplicationStatus::UnderReview->value,
                'admin_review_flagged_at' => null,
            ]);
            $pet->update(['availability_status' => AvailabilityStatus::SoftReserved->value]);

            AuditLogService::log(
                $actorId,
                'Interview Notes Added',
                'AdoptionApplication',
                $candidate->id,
                'Added notes and moved the primary candidate to Under Review.'
            );
        });

        $application->refresh()->loadMissing(['user', 'pet']);
        $this->sendStatusEmail($application);

        return $application;
    }

    public function approve(AdoptionApplication $application, ?string $remarks, ?int $actorId): Collection
    {
        $closed = DB::transaction(function () use ($application, $remarks, $actorId) {
            $candidate = AdoptionApplication::lockForUpdate()->findOrFail($application->id);
            $pet = Pet::withoutGlobalScope('notArchived')->lockForUpdate()->findOrFail($candidate->pet_id);

            if (! $candidate->is_primary_candidate || $candidate->status !== ApplicationStatus::UnderReview) {
                throw ValidationException::withMessages([
                    'decision' => 'Only the primary candidate under review can be approved.',
                ]);
            }

            $approvedAt = now();

            $candidate->update([
                'status' => ApplicationStatus::Approved->value,
                'is_primary_candidate' => false,
                'decision_remarks' => $remarks,
                'queue_closed_at' => $approvedAt,
                'adopted_at' => $approvedAt,
            ]);
            $pet->update(['availability_status' => AvailabilityStatus::Adopted->value]);

            $remaining = AdoptionApplication::with('user')
                ->where('pet_id', $pet->id)
                ->where('id', '!=', $candidate->id)
                ->whereNotIn('status', self::TERMINAL_STATUSES)
                ->lockForUpdate()
                ->get();

            if ($remaining->isNotEmpty()) {
                AdoptionApplication::whereKey($remaining->modelKeys())->update([
                    'status' => ApplicationStatus::Closed->value,
                    'is_primary_candidate' => false,
                    'queue_closed_at' => now(),
                ]);
            }

            $this->postAdoptionSchedule->ensureForApplication($candidate);
            $this->handovers->forApprovedApplication($candidate);

            AuditLogService::log(
                $actorId,
                'Reservation Queue Closed — Adoption Approved',
                'AdoptionApplication',
                $candidate->id,
                "Pet ID {$pet->id} adopted; {$remaining->count()} waitlisted application(s) closed."
            );

            return $remaining;
        });

        $application->refresh()->loadMissing(['user', 'pet']);
        $this->sendStatusEmail($application, 'application_approved');
        foreach ($closed as $waitlisted) {
            $this->emailNotifications->user(
                $waitlisted->user,
                'Adoption queue closed',
                'This adoption queue has closed',
                [
                    "The adoption queue for {$application->pet?->name} has closed because the pet has been adopted.",
                    'Thank you for your interest. You can continue browsing other available pets.',
                ],
                'Browse Pets',
                route('animal.index'),
                'application_queue_closed',
                $waitlisted->id,
            );
        }

        return $closed;
    }

    public function overridePrimary(
        AdoptionApplication $target,
        string $reason,
        int $actorId
    ): AdoptionApplication {
        $demotedApplicationId = DB::transaction(function () use ($target, $reason, $actorId): ?int {
            $candidate = AdoptionApplication::lockForUpdate()->findOrFail($target->id);
            $pet = Pet::withoutGlobalScope('notArchived')->lockForUpdate()->findOrFail($candidate->pet_id);

            if ($candidate->status !== ApplicationStatus::Waitlisted) {
                throw ValidationException::withMessages(['override_reason' => 'Only a waitlisted application can bypass the active queue.']);
            }
            if ($candidate->document_verification_status !== DocumentVerificationStatus::Verified) {
                throw ValidationException::withMessages(['override_reason' => 'Only a document-verified applicant can be promoted.']);
            }

            $current = AdoptionApplication::where('pet_id', $pet->id)
                ->where('is_primary_candidate', true)
                ->where('id', '!=', $candidate->id)
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw ValidationException::withMessages(['override_reason' => 'There is no active primary candidate to override.']);
            }

            if ($current) {
                $current->update([
                    'status' => ApplicationStatus::Waitlisted->value,
                    'is_primary_candidate' => false,
                    'interview_date' => null,
                    'admin_review_flagged_at' => null,
                ]);
            }

            $candidate->update([
                'status' => ApplicationStatus::PrimaryCandidate->value,
                'is_primary_candidate' => true,
                'queue_promoted_at' => now(),
                'interview_date' => null,
                'admin_review_flagged_at' => null,
                'override_reason' => $reason,
            ]);
            $pet->update(['availability_status' => AvailabilityStatus::SoftReserved->value]);

            AuditLogService::log(
                $actorId,
                'Reservation Queue Administrative Override',
                'AdoptionApplication',
                $candidate->id,
                "Override reason: {$reason}".($current ? " Previous primary: {$current->id}." : '')
            );

            return $current?->id;
        });

        $target->refresh()->loadMissing(['user', 'pet']);
        $this->notifyPromotion($target);

        if ($demotedApplicationId) {
            $demoted = AdoptionApplication::with(['user', 'pet'])->find($demotedApplicationId);
            if ($demoted) {
                $this->sendStatusEmail($demoted, 'application_waitlisted');
            }
        }

        return $target;
    }

    public function processTimeouts(): array
    {
        $flagged = 0;
        $promoted = 0;
        $timeout = now()->subHours(config('reservations.interview_timeout_hours', 72));
        $grace = now()->subHours(config('reservations.admin_review_grace_hours', 24));

        $applications = AdoptionApplication::where('is_primary_candidate', true)
            ->where('status', ApplicationStatus::InterviewScheduled->value)
            ->where('interview_date', '<=', $timeout)
            ->get();

        foreach ($applications as $application) {
            if (! $application->admin_review_flagged_at) {
                $application->update(['admin_review_flagged_at' => now()]);
                AuditLogService::log(null, 'Interview Timeout Flagged for Administrative Review', 'AdoptionApplication', $application->id);
                $this->notifyAdmins("Application {$application->id} requires review", 'An interview has been overdue without a decision for more than 72 hours.');
                $flagged++;

                continue;
            }

            if ($application->admin_review_flagged_at->lte($grace)) {
                $next = $this->resolvePrimary(
                    $application,
                    ApplicationStatus::NoShow,
                    'Automatically marked as no-show after the interview timeout and administrative review grace period.',
                    null
                );
                if ($next) {
                    $promoted++;
                }
            }
        }

        return compact('flagged', 'promoted');
    }

    private function promoteNextLocked(Pet $pet, ?int $actorId, string $notes): ?AdoptionApplication
    {
        $next = AdoptionApplication::where('pet_id', $pet->id)
            ->where('status', ApplicationStatus::Waitlisted->value)
            ->where('document_verification_status', DocumentVerificationStatus::Verified->value)
            ->orderByRaw('knn_score IS NULL, knn_score ASC')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $next) {
            $pet->update(['availability_status' => AvailabilityStatus::Available->value]);
            AuditLogService::log($actorId, 'Reservation Queue Exhausted — Pet Reopened', 'Pet', $pet->id);

            return null;
        }

        $next->update([
            'status' => ApplicationStatus::PrimaryCandidate->value,
            'is_primary_candidate' => true,
            'queue_promoted_at' => now(),
            'admin_review_flagged_at' => null,
        ]);
        $pet->update(['availability_status' => AvailabilityStatus::SoftReserved->value]);
        AuditLogService::log($actorId, 'Waitlist Candidate Promoted', 'AdoptionApplication', $next->id, $notes);

        return $next->fresh(['user', 'pet']);
    }

    private function notifyPromotion(AdoptionApplication $application): void
    {
        $this->sendStatusEmail($application, 'queue_promoted');
        $this->notifyAdmins(
            "Waitlist applicant {$application->id} promoted",
            "{$application->user?->full_name} is now the primary candidate for {$application->pet?->name}."
        );
    }

    private function notifyAdmins(string $subject, string $message): void
    {
        $this->emailNotifications->staff(
            $subject,
            $subject,
            [$message, 'Sign in to the protected application queue for details.'],
            'Review Applications',
            route('admin.applications.index'),
            true,
            'reservation_queue_staff',
        );
    }

    private function sendStatusEmail(
        AdoptionApplication $application,
        string $event = 'status_updated',
        ?string $previousInterviewDate = null,
    ): void {
        $application->loadMissing('user');
        if (! $application->user?->hasVerifiedEmail()) {
            return;
        }

        try {
            Mail::to($application->user)->queue(new StatusUpdateMail($application, $event, $previousInterviewDate));
        } catch (\Throwable $exception) {
            Log::error('Reservation notification could not be queued.', [
                'application_id' => $application->id,
                'event' => $event,
                'exception' => $exception::class,
            ]);
        }
    }
}
