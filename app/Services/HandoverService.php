<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class HandoverService
{
    /**
     * Create the one adopter-owned handover record for an approved adoption.
     * The lookup is idempotent so it can also safely backfill adoptions that
     * were approved before the handover module was introduced.
     */
    public function forApprovedApplication(AdoptionApplication $application): Handover
    {
        if ($application->status !== ApplicationStatus::Approved) {
            throw new InvalidArgumentException('A handover can only be created for an approved adoption.');
        }

        $application->loadMissing(['pet', 'user']);

        $adopter = $application->user;
        $adopterName = trim(implode(' ', array_filter([
            $application->applicant_first_name ?: $adopter?->first_name,
            $application->applicant_last_name ?: $adopter?->last_name,
        ])));
        $approvedAt = $application->adopted_at ?? $application->queue_closed_at ?? Carbon::now();

        $handover = Handover::firstOrCreate(
            ['application_id' => $application->id],
            [
                'code' => 'HV-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
                'pet_id' => $application->pet_id,
                'user_id' => $application->user_id,
                'adopter_name' => $adopterName !== '' ? $adopterName : null,
                'adopter_phone' => $application->applicant_phone,
                'adopter_email' => $application->applicant_email ?: $adopter?->email,
                'adopter_address' => $application->address,
                'approved_at' => $approvedAt,
                'history' => [[
                    'at' => $approvedAt->toIso8601String(),
                    'label' => 'Application approved',
                    'actor' => 'System',
                ]],
                'reminders' => [],
            ],
        );

        if ($handover->wasRecentlyCreated) {
            $handover->loadMissing('pet');
            $handover->createNotification('prepared', [
                'title' => 'Your adoption has been approved',
                'body' => "Your adoption for {$handover->pet?->name} has been approved. Shelter staff will contact you when the handover is scheduled.",
            ]);
        }

        return $handover;
    }
}
