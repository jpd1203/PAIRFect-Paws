<?php

namespace App\Mail;

use App\Mail\Concerns\UsesResendIdempotency;
use App\Models\PostAdoptionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelfareReportReceiptMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels, UsesResendIdempotency;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public PostAdoptionLog $log)
    {
        $this->initializeDeliveryIdempotency();
        $this->onQueue('emails')->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welfare Report Received - Thank You!',
            using: $this->resendEnvelopeCallbacks(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welfare-receipt');
    }
}
