<?php

namespace Tests\Concerns;

use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\IdentityVerification;
use App\Models\User;
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
        }
    }
}
