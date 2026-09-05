<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class QueuedResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);

        // Password recovery must not wait for a separately managed worker.
        // The sync connection still uses Laravel's encrypted queued-notification
        // job, but executes it during this request after any transaction commits.
        $this->onConnection('sync')->onQueue('emails')->afterCommit();
    }

    protected function buildMailMessage($url): MailMessage
    {
        $expiresIn = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        return (new MailMessage)
            ->subject('Reset your PAIRfect Paws password')
            ->greeting('Password reset requested')
            ->line('We received a request to reset the password for your PAIRfect Paws account.')
            ->action('Reset Password', $url)
            ->line("This secure link expires in {$expiresIn} minutes and can only be used once.")
            ->line('If you did not request this reset, you can ignore this email. Your password will remain unchanged.');
    }
}
