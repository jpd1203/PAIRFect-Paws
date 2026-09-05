<?php

namespace App\Mail;

use App\Mail\Concerns\UsesResendIdempotency;
use App\Models\PostAdoptionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CheckInReminderMail extends Mailable
{
    use Queueable, SerializesModels, UsesResendIdempotency;

    public function __construct(
        public PostAdoptionLog $log,
        public ?string $customMessage = null,
    ) {
        $this->initializeDeliveryIdempotency(
            "pairfectpaws-checkin-{$log->id}-".($log->reminders_sent + 1)
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: Post-Adoption Check-in Due',
            using: $this->resendEnvelopeCallbacks(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.checkin-reminder');
    }
}
