<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdentityVerificationService
{
    public function fingerprint(AdoptionApplication $application, ?Handover $handover = null): string
    {
        return hash('sha256', json_encode([
            $application->document_disk, $application->document_path,
            $application->document_uploaded_at?->toIso8601String(),
            $application->first_name, $application->last_name,
            $handover?->id, $handover?->scheduled_method,
            $handover?->scheduled_start_at?->toIso8601String(),
            $handover?->scheduled_end_at?->toIso8601String(),
        ]));
    }

    public function isVerified(AdoptionApplication $application, string $stage = 'interview', ?int $attempt = null): bool
    {
        // An adverse later event supersedes an earlier positive event.
        $event = $application->identityVerifications()->where('stage', $stage)->latest('id')->first();

        return $event?->status === 'verified'
            && $event->document_fingerprint === $this->fingerprint($application, $stage === 'pickup_handover' ? $application->handover()->first() : null)
            && ($stage !== 'pickup_handover' || $event->release_attempt === $attempt)
            && collect(IdentityVerification::CHECKS)->every(fn ($field) => $event->$field);
    }

    public function requireInterview(AdoptionApplication $application): void
    {
        if (! $this->isVerified($application)) {
            throw ValidationException::withMessages(['identity' => 'Applicant identity verification must be completed before this application can be approved.']);
        }
    }

    public function record(AdoptionApplication $application, User $actor, array $data): IdentityVerification
    {
        abort_unless($actor->is_active && $actor->isStaff() && $actor->hasVerifiedEmail(), 403);

        return DB::transaction(function () use ($application, $actor, $data): IdentityVerification {
            $current = AdoptionApplication::query()->lockForUpdate()->findOrFail($application->id);
            $stage = $data['stage'];
            $handover = $current->handover()->lockForUpdate()->first();
            $legacyPendingRelease = $current->status === ApplicationStatus::Approved && $handover && ! $handover->released_at && ! $handover->adopter_outcome;
            if ($stage === 'interview' && ! $legacyPendingRelease && ! in_array($current->status, [ApplicationStatus::InterviewScheduled, ApplicationStatus::UnderReview], true)) {
                throw ValidationException::withMessages(['identity' => 'Record interview identity verification during the active interview or review stage.']);
            }
            if ($stage === 'pickup_handover') {
                $this->requireInterview($current);
                if (! $handover || $handover->scheduled_method !== 'pickup' || $handover->schedule_status !== 'confirmed' || $handover->released_at || $handover->adopter_outcome) {
                    throw ValidationException::withMessages(['identity' => 'A final pickup check requires a confirmed pickup schedule before release.']);
                }
                if ($data['verification_method'] !== 'in_person') {
                    throw ValidationException::withMessages(['verification_method' => 'The final pickup identity check must be in person.']);
                }
            }
            $checks = [];
            foreach (IdentityVerification::CHECKS as $field) {
                $checks[$field] = (bool) ($data[$field] ?? false);
            }
            if ($data['status'] === 'verified' && in_array(false, $checks, true)) {
                throw ValidationException::withMessages(['identity' => 'Every identity confirmation is required for a Verified result.']);
            }
            if ($data['status'] !== 'verified' && ! filled($data['discrepancy_note'] ?? null)) {
                throw ValidationException::withMessages(['discrepancy_note' => 'Explain why identity needs review or failed.']);
            }
            $event = $current->identityVerifications()->create([
                ...$checks, 'stage' => $stage, 'verification_method' => $data['verification_method'],
                'status' => $data['status'], 'discrepancy_note' => $data['discrepancy_note'] ?? null,
                'document_fingerprint' => $this->fingerprint($current, $stage === 'pickup_handover' ? $handover : null),
                'release_attempt' => $stage === 'pickup_handover' ? $handover->reopen_count : null,
                'verified_by_user_id' => $actor->id, 'verified_at' => now(),
            ]);
            AuditLogService::log($actor->id, "identity.{$stage}_{$event->status}", 'AdoptionApplication', $current->id, "Verification event {$event->id}; method {$event->verification_method}.");

            return $event;
        });
    }
}
