<?php

namespace App\Services;

use App\Models\Handover;
use App\Models\HandoverNotification;

class HandoverNotificationService
{
    public function __construct(private EmailNotificationService $emails) {}

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

        return $notification;
    }
}
