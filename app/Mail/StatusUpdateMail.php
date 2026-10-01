<?php

namespace App\Mail;

use App\Mail\Concerns\UsesResendIdempotency;
use App\Models\AdoptionApplication;
use App\Support\ManilaTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StatusUpdateMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels, UsesResendIdempotency;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public string $adopterName;

    public string $petName;

    public string $status;

    public string $statusDisplay;

    public ?string $interviewDate;

    public string $actionUrl;

    public string $rescheduleUrl;

    public function __construct(
        AdoptionApplication $application,
        public string $event = 'status_updated',
        public ?string $previousInterviewDate = null,
    ) {
        $application->loadMissing(['user', 'pet']);
        $this->adopterName = $application->user?->first_name ?: 'Adopter';
        $this->petName = $application->pet?->name ?: 'the pet';
        $this->status = $application->status->value;
        $this->statusDisplay = $application->status_display;
        $this->interviewDate = $application->interview_date
            ? ManilaTime::format($application->interview_date, 'F j, Y \\a\\t g:i A')
            : null;
        $this->actionUrl = route('application.index');
        $this->rescheduleUrl = route('application.index', ['reschedule' => $application->id]).'#reschedule-'.$application->id;
        $this->initializeDeliveryIdempotency();
        $this->onQueue('emails')->afterCommit();
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            'interview_scheduled' => "Interview scheduled for {$this->petName}",
            'interview_rescheduled' => "Interview rescheduled for {$this->petName}",
            'application_approved' => "Application approved for {$this->petName}",
            'application_rejected' => "Application update for {$this->petName}",
            'application_waitlisted' => "Waitlist update for {$this->petName}",
            'queue_promoted' => "Your application for {$this->petName} was promoted",
            default => 'Your adoption application status was updated',
        };

        return new Envelope(
            subject: $subject,
            using: $this->resendEnvelopeCallbacks(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.status-update');
    }
}
