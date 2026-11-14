<?php

namespace Tests\Concerns;

use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\HandoverDeliveryClearanceService;
use App\Services\HandoverMailOutboxService;
use App\Services\IdentityVerificationService;

trait PreparesVerifiedHandovers
{
    private function verifiedIdentity(AdoptionApplication $application, User $staff, string $stage = 'interview'): void
    {
        app(IdentityVerificationService::class)->record($application, $staff, [
            'stage' => $stage, 'verification_method' => 'in_person', 'status' => 'verified',
            ...array_fill_keys(IdentityVerification::CHECKS, true),
        ]);
    }

    private function prepareHandover(Handover $handover, User $staff, string $method = 'delivery'): void
    {
        $handover->update([
            'scheduled_method' => $method, 'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2), 'schedule_status' => 'confirmed',
            'schedule_version' => $handover->schedule_version + 1,
            'schedule_confirmed_at' => now(), 'schedule_confirmed_by_user_id' => $handover->user_id,
        ]);
        $handover->refresh();
        $this->verifiedIdentity($handover->application, $staff);
        if ($method === 'pickup') {
            $this->verifiedIdentity($handover->application, $staff, 'pickup_handover');
        } else {
            $this->prepareDeliveryClearance($handover, $staff);
        }
    }

    /** An already-cleared synthetic placement; dedicated tests exercise the actual staff actions. */
    private function prepareDeliveryClearance(Handover $handover, User $staff): void
    {
        $handover->refresh();
        if (! filled($handover->adopter_address)) {
            $handover->update(['adopter_address' => '123 Synthetic Street, Test Barangay, Manila']);
        }
        $clearance = app(HandoverDeliveryClearanceService::class);
        $handover->forceFill([
            'delivery_clearance_version' => $handover->delivery_clearance_version + 2,
            'delivery_address_status' => 'verified', 'delivery_address_verified_by_user_id' => $staff->id,
            'delivery_address_verified_at' => now(), 'delivery_address_fingerprint' => $clearance->destinationFingerprint($handover),
            'delivery_contact_method' => 'phone', 'delivery_contacted_by_user_id' => $staff->id,
            'delivery_contacted_at' => now(), 'delivery_recipient_available' => true,
            'delivery_readiness_schedule_version' => $handover->schedule_version,
            'delivery_readiness_schedule_fingerprint' => app(HandoverMailOutboxService::class)->fingerprint($handover),
            'delivery_readiness_destination_fingerprint' => $clearance->destinationFingerprint($handover),
            'delivery_readiness_method' => 'delivery', 'delivery_readiness_release_attempt' => $handover->reopen_count,
        ])->save();
    }
}
