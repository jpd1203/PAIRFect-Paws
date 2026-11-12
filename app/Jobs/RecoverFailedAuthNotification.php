<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Throwable;

final class RecoverFailedAuthNotification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $userId, public string $address, public Notification $notification)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user?->is_active || $user->email !== $this->address
            || ($this->notification instanceof QueuedVerifyEmail && $user->hasVerifiedEmail())
            || ($this->notification instanceof QueuedResetPassword && ! Password::broker()->tokenExists($user, $this->notification->token))) {
            return;
        }
        try {
            Notifications::sendNow($user, $this->notification, ['mail']);
        } catch (Throwable $exception) {
            throw new RuntimeException('Recovered authentication email transport failed: '.$exception::class);
        }
    }
}
