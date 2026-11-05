<?php

namespace App\Services;

use App\Models\Handover;
use App\Models\HandoverNotification;
use Illuminate\Support\Facades\DB;

class HandoverNotificationService
{
    public function __construct(
        private EmailNotificationService $emails,
        private InAppNotificationService $inApp,
        private HandoverMailOutboxService $outbox,
    ) {}

    public function staffEvent(Handover $handover, string $kind, string $title, string $body): void
    {
        DB::transaction(function () use ($handover, $kind, $title, $body): void {
            $url = route('admin.handover.show', $handover);
            $key = "handover:{$kind}:{$handover->id}:{$handover->schedule_version}:{$handover->reopen_count}";
            $this->inApp->administrators('handover_'.$kind, $title, $body, $url, $key, 'Handover', $handover->id);
            $this->queueStaff($handover, $kind, $key, $title, [$body, "Handover {$handover->code}; open the protected record for details."]);
        });
    }

    /** Create the in-app notice and deliver only the channels it advertises. */
    public function create(Handover $handover, string $kind, array $attributes = []): HandoverNotification
    {
        return DB::transaction(fn () => $this->createWithinTransaction($handover, $kind, $attributes));
    }

    private function createWithinTransaction(Handover $handover, string $kind, array $attributes): HandoverNotification
    {
        $notification = $handover->createNotification($kind, $attributes);
        // No SMS/push adapter exists. Channels describe intent, never verified delivery.
        $notification->update(['channels' => array_values(array_intersect($notification->channels ?? [], ['In-app', 'Email']))]);

        if (in_array('Email', $notification->channels ?? [], true) && $handover->user?->email) {
            $this->outbox->stage($handover, $kind, 'handover-notice:'.$notification->id, $handover->user, [
                'subject' => $notification->title, 'heading' => $notification->title, 'lines' => [$notification->body],
                'action_text' => $notification->action_label, 'action_url' => $notification->action_url,
            ]);
        }

        if ($kind === 'issue_logged') {
            $this->queueStaff($handover, $kind, 'handover-issue:'.$notification->id,
                "Handover issue reported - {$handover->code}",
                ["Handover {$handover->code} requires staff review.", 'Open the protected handover record for details.']);
            $this->inApp->administrators('handover_issue_staff', 'An adopter reported a handover issue',
                "Handover {$handover->code} requires staff review.", route('admin.handover.show', $handover),
                'handover-issue:'.$notification->id, 'Handover', $handover->id);
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

    private function queueStaff(Handover $handover, string $kind, string $event, string $subject, array $lines): void
    {
        foreach ($this->emails->staffRecipients() as $recipient) {
            $this->outbox->stage($handover, $kind, $event, $recipient, [
                'subject' => $subject, 'heading' => $subject, 'lines' => $lines,
                'action_text' => 'Review handover', 'action_url' => route('admin.handover.show', $handover),
            ]);
        }
    }
}
