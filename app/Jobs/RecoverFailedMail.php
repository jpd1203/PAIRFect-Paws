<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class RecoverFailedMail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [60, 300, 900];

    public function __construct(public Mailable $mail, public string $address, public ?int $userId, public ?string $role)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        if ($this->userId !== null) {
            $user = User::find($this->userId);
            if (! $user?->is_active || ! $user->hasVerifiedEmail() || $user->email !== $this->address
                || ($this->role !== null && $user->role?->value !== $this->role)) {
                return;
            }
        } elseif (strtolower(trim((string) config('mail.staff_alert_address'))) !== strtolower(trim($this->address))) {
            return;
        }
        try {
            Mail::to($this->address)->sendNow($this->mail);
        } catch (Throwable $exception) {
            throw new RuntimeException('Recovered email transport failed: '.$exception::class);
        }
    }
}
