<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\User;
use App\Support\ManilaTime;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandoverDeliveryClearanceService
{
    public const CONTACT_METHODS = ['phone' => 'Phone call', 'email' => 'Email', 'other' => 'Other documented direct contact'];

    public function authorizeStaff(User $actor): User
    {
        $staff = User::query()->lockForUpdate()->find($actor->id);
        abort_unless($staff?->is_active && $staff->isStaff() && $staff->hasVerifiedEmail(), 403);

        return $staff;
    }

    public function destinationFingerprint(Handover $handover): string
    {
        // Exact handover snapshot, never the current user/profile address.
        return hash('sha256', json_encode([$handover->id, $handover->user_id, $handover->adopter_address], JSON_THROW_ON_ERROR));
    }

    public function addressIsVerified(Handover $handover): bool
    {
        return filled($handover->adopter_address)
            && $handover->delivery_address_status === 'verified'
            && $handover->delivery_address_verified_by_user_id !== null
            && $handover->delivery_address_verified_at !== null
            && hash_equals($this->destinationFingerprint($handover), $handover->delivery_address_fingerprint ?? '');
    }

    public function scheduleIsConfirmed(Handover $handover): bool
    {
        return $handover->scheduled_method === 'delivery'
            && $handover->schedule_status === 'confirmed'
            && $handover->scheduled_start_at !== null && $handover->scheduled_end_at !== null
            && $handover->schedule_confirmed_at !== null
            && $handover->user_id !== null
            && (int) $handover->schedule_confirmed_by_user_id === (int) $handover->user_id;
    }

    public function readinessIsCurrent(Handover $handover): bool
    {
        return $this->addressIsVerified($handover) && $this->scheduleIsConfirmed($handover)
            && $handover->delivery_recipient_available === true
            && isset(self::CONTACT_METHODS[$handover->delivery_contact_method])
            && $handover->delivery_contacted_by_user_id !== null && $handover->delivery_contacted_at !== null
            && $handover->delivery_readiness_schedule_version === $handover->schedule_version
            && $handover->delivery_readiness_release_attempt === (int) $handover->reopen_count
            && $handover->delivery_readiness_method === 'delivery'
            && hash_equals($this->destinationFingerprint($handover), $handover->delivery_readiness_destination_fingerprint ?? '')
            && hash_equals(app(HandoverMailOutboxService::class)->fingerprint($handover), $handover->delivery_readiness_schedule_fingerprint ?? '');
    }

    public function notificationFingerprint(Handover $handover): string
    {
        return hash('sha256', json_encode([
            $this->destinationFingerprint($handover), app(HandoverMailOutboxService::class)->fingerprint($handover),
            $handover->schedule_version, $handover->reopen_count, $handover->delivery_clearance_version,
            $handover->delivery_address_status, $handover->delivery_address_fingerprint,
            $handover->delivery_address_verified_by_user_id, $handover->delivery_address_verified_at?->toIso8601String(),
            $handover->delivery_recipient_available, $handover->delivery_contact_method,
            $handover->delivery_contacted_by_user_id, $handover->delivery_contacted_at?->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    public function requireReleaseClearance(Handover $handover): void
    {
        if (! $this->addressIsVerified($handover)) {
            throw ValidationException::withMessages(['delivery_address' => 'Verify the current handover delivery destination before release.']);
        }
        if (! $this->readinessIsCurrent($handover)) {
            throw ValidationException::withMessages(['delivery_readiness' => 'Contact the adopter and explicitly confirm availability for the current delivery schedule and destination before release.']);
        }
    }

    public function verifyAddress(Handover $handover, User $actor, array $data): void
    {
        $this->locked($handover, $actor, $data, function (Handover $current, User $staff) use ($data): void {
            if (! filled($current->adopter_address)) {
                throw ValidationException::withMessages(['delivery_address' => 'The handover has no delivery destination. Ask shelter staff to resolve the approved application record; do not substitute a profile address.']);
            }
            $fingerprint = $this->destinationFingerprint($current);
            if ($current->delivery_address_status === $data['status'] && $current->delivery_address_fingerprint === $fingerprint) {
                return;
            }
            $current->forceFill([
                'delivery_address_status' => $data['status'], 'delivery_address_fingerprint' => $fingerprint,
                'delivery_address_verified_by_user_id' => $staff->id, 'delivery_address_verified_at' => now(),
                'delivery_clearance_version' => $current->delivery_clearance_version + 1,
                // Reverification/revocation must not resurrect earlier availability.
                'delivery_recipient_available' => null,
            ])->save();
            $this->history($current, $staff, 'delivery.address_'.$data['status']);
        });
    }

    public function recordReadiness(Handover $handover, User $actor, array $data): void
    {
        $this->locked($handover, $actor, $data, function (Handover $current, User $staff) use ($data): void {
            if (! $this->scheduleIsConfirmed($current) || ManilaTime::now()->gt(ManilaTime::at($current->scheduled_end_at))) {
                throw ValidationException::withMessages(['schedule' => 'Establish a confirmed, nonexpired delivery schedule before recording recipient readiness.']);
            }
            if (! $this->addressIsVerified($current)) {
                throw ValidationException::withMessages(['delivery_address' => 'Verify the current handover delivery destination before recording readiness.']);
            }
            // No delegation policy exists. This operation documents contact with the adopter only.
            if (($data['recipient'] ?? null) !== 'adopter' || ! $current->user?->isAdopter()) {
                throw ValidationException::withMessages(['recipient' => 'Confirm availability with the adopter. Third-party recipient authorization is not implemented.']);
            }
            $available = (bool) $data['available'];
            $notes = filled($data['notes'] ?? null) ? trim($data['notes']) : null;
            if ($current->delivery_recipient_available === $available
                && $current->delivery_contact_method === $data['contact_method']
                && $current->delivery_readiness_notes === $notes
                && $current->delivery_readiness_schedule_version === $current->schedule_version
                && $current->delivery_readiness_release_attempt === (int) $current->reopen_count
                && $current->delivery_readiness_method === 'delivery'
                && $current->delivery_readiness_destination_fingerprint === $this->destinationFingerprint($current)
                && $current->delivery_readiness_schedule_fingerprint === app(HandoverMailOutboxService::class)->fingerprint($current)) {
                return;
            }
            $current->forceFill([
                'delivery_contact_method' => $data['contact_method'], 'delivery_contacted_by_user_id' => $staff->id,
                'delivery_contacted_at' => now(), 'delivery_recipient_available' => $available,
                'delivery_readiness_schedule_version' => $current->schedule_version,
                'delivery_readiness_schedule_fingerprint' => app(HandoverMailOutboxService::class)->fingerprint($current),
                'delivery_readiness_destination_fingerprint' => $this->destinationFingerprint($current),
                'delivery_readiness_method' => $current->scheduled_method,
                'delivery_readiness_release_attempt' => (int) $current->reopen_count,
                'delivery_readiness_notes' => $notes, 'delivery_clearance_version' => $current->delivery_clearance_version + 1,
            ])->save();
            $this->history($current, $staff, $available ? 'delivery.readiness_confirmed' : 'delivery.recipient_unavailable');
            if ($available) {
                app(HandoverNotificationService::class)->create($current, 'delivery_pre_dispatch', [
                    'title' => 'Delivery preparation: availability confirmed',
                    'body' => 'Shelter staff recorded your availability for the agreed delivery. Your pet has not yet been marked as released. Check your protected Handover Status page for the latest arrangements.',
                    'channels' => ['In-app', 'Email'], 'action_label' => 'View handover status',
                    'action_url' => route('adopter.handover.status', $current),
                ]);
            }
        });
    }

    private function locked(Handover $handover, User $actor, array $data, Closure $operation): void
    {
        DB::transaction(function () use ($handover, $actor, $data, $operation): void {
            $application = AdoptionApplication::query()->lockForUpdate()->findOrFail($handover->application_id);
            $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            $staff = $this->authorizeStaff($actor);
            $current->ensureCanRelease();
            if ($application->status !== ApplicationStatus::Approved || $current->received_at || $current->scheduled_method !== 'delivery') {
                throw ValidationException::withMessages(['delivery_readiness' => 'Only an approved, unreleased delivery handover can receive clearance.']);
            }
            if ((int) $data['schedule_version'] !== $current->schedule_version
                || (int) $data['clearance_version'] !== $current->delivery_clearance_version
                || ! hash_equals($this->destinationFingerprint($current), $data['destination_fingerprint'])) {
                throw ValidationException::withMessages(['delivery_readiness' => 'The delivery destination, schedule or clearance changed. Refresh and review the current details before continuing.']);
            }
            $operation($current, $staff);
        });
    }

    private function history(Handover $handover, User $staff, string $action): void
    {
        $handover->recordHistory(str_replace(['delivery.', '_'], ['', ' '], $action).' (clearance version '.$handover->delivery_clearance_version.')', $staff->full_name);
        $handover->save();
        AuditLogService::log($staff->id, $action, 'Handover', $handover->id,
            'Clearance version '.$handover->delivery_clearance_version.'; schedule version '.$handover->schedule_version.'; destination reference '.$this->destinationFingerprint($handover).'.');
    }
}
