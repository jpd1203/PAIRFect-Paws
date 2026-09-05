<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class QueuedVerifyEmail extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct()
    {
        // Verification is required for protected workflows, so deliver this
        // auth-critical message without depending on a background worker.
        $this->onConnection('sync')->onQueue('emails')->afterCommit();
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your PAIRfect Paws email address')
            ->greeting('Welcome to PAIRfect Paws!')
            ->line('Please verify your email address to submit adoption applications and use protected account features.')
            ->action('Verify Email Address', $url)
            ->line('This verification link expires in 60 minutes.')
            ->line('If you did not create this account, you can ignore this email.');
    }
}
