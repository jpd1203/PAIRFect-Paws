<?php

namespace App\Services;

use App\Models\Handover;
use App\Models\HandoverNotification;

class HandoverNotificationService
{
    public function __construct(
        private EmailNotificationService $emails,
        private InAppNotificationService $inApp,
    ) {}

    public function staffEvent(Handover $handover, string $kind, string $title, string $body): void
    {
        $url = route('admin.handover.show', $handover);
        $key = "handover:{$kind}:{$handover->id}:{$handover->schedule_version}";
        $this->inApp->administrators('handover_'.$kind, $title, $body, $url, $key, 'Handover', $handover->id);
        $this->emails->staff($title, $title, [$body, "Handover {$handover->code}; open the protected record for details."], 'Review handover', $url, false, 'handover_'.$kind, $handover->id);
    }

    /** Create the in-app notice and deliver only the channels it advertises. */
    public function create(Handover $handover, string $kind, array $attributes = []): HandoverNotification
    {
        $notification = $handover->createNotification($kind, $attributes);

        if (in_array('Email', $notification->channels ?? [], true)) {
            $this->emails->user(
                $handover->user,
                $notification->title,
                $notification->title,
                [$notification->body],
                $notification->action_label,
                $notification->action_url,
                'handover_'.$kind,
                $handover->id,
            );
        }

        if ($kind === 'issue_logged') {
            $this->emails->staff(
                "Handover issue reported - {$handover->code}",
                'An adopter reported a handover issue',
                ["Handover {$handover->code} requires staff review.", 'Open the protected handover record for details.'],
                'Review Handover',
                route('admin.handover.show', $handover),
                false,
                'handover_issue_staff',
                $handover->id,
            );
        }

        if ($kind === 'completed') {
            $petName = $handover->pet?->name ?? 'the pet';
            $adopterName = $handover->adopter_name ?: ($handover->user?->full_name ?? 'The adopter');

            $this->inApp->administrators(
                'handover_received_staff',
                "Handover completed: {$petName}",
                "{$adopterName} confirmed receipt of {$petName} ({$handover->code}).",
                route('admin.handover.show', $handover),
                "handover_received_staff:{$handover->id}",
                'Handover',
                $handover->id,
            );
        }

        return $notification;
    }
}
