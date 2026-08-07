<?php

namespace App\Mail;

use App\Models\PostAdoptionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CheckInReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PostAdoptionLog $log) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reminder: Post-Adoption Check-in Due');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.checkin-reminder');
    }
}
