<?php

namespace App\Mail;

use App\Models\AdoptionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StatusUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AdoptionApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Adoption Application Status Has Been Updated');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.status-update');
    }
}
