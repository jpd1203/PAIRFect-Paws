<?php

namespace App\Mail;

use App\Models\PostAdoptionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelfareReportReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PostAdoptionLog $log) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welfare Report Received — Thank You!');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welfare-receipt');
    }
}
