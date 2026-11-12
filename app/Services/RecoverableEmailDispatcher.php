<?php

namespace App\Services;

use App\Jobs\RecoverFailedAuthNotification;
use App\Jobs\RecoverFailedMail;
use App\Models\User;
use App\Support\RecoveryQueuePayload;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/** Bounded fallback to Laravel's existing manual failed-job recovery, not a new outbox. */
final class RecoverableEmailDispatcher
{
    public function queue(User|string $recipient, Mailable $mail, string $event, ?int $entityId = null): void
    {
        $address = $recipient instanceof User ? $recipient->email : $recipient;
        $recovery = new RecoverFailedMail($mail, $address, $recipient instanceof User ? $recipient->id : null,
            $recipient instanceof User ? $recipient->role?->value : null);
        DB::afterCommit(function () use ($recipient, $mail, $recovery, $event, $entityId): void {
            try {
                Mail::to($recipient)->queue($mail);
            } catch (Throwable $exception) {
                $this->retain($recovery, $event, $entityId, $exception);
            }
        });
    }

    public function auth(User $user, Notification $notification): void
    {
        $recovery = new RecoverFailedAuthNotification($user->id, $user->email, $notification);
        DB::afterCommit(function () use ($user, $notification, $recovery): void {
            try {
                $user->notify($notification);
            } catch (Throwable $exception) {
                $this->retain($recovery, 'authentication_email', $user->id, $exception);
            }
        });
    }

    private function retain(object $job, string $event, ?int $entityId, Throwable $exception): void
    {
        try {
            $factory = new RecoveryQueuePayload;
            $factory->setContainer(app());
            // Failed-job providers stringify exceptions. Do not persist stack arguments
            // (recipient addresses or reset tokens) even when PHP includes them in traces.
            $failure = new class('Email dispatch failed: '.$exception::class) extends RuntimeException
            {
                public function __toString(): string
                {
                    return $this->getMessage();
                }
            };
            $id = app('queue.failer')->log(config('queue.default'), 'emails', $factory->forJob($job),
                $failure);
            if ($id === null) {
                throw new RuntimeException('Failed-job storage did not retain a recovery record.');
            }
        } catch (Throwable $storageFailure) {
            Log::critical('Email dispatch and recovery storage both failed.', [
                'event' => $event, 'entity_id' => $entityId, 'exception' => $storageFailure::class,
            ]);
            throw new RuntimeException('Email recovery storage is unavailable.');
        }
        Log::error('Email dispatch retained for manual failed-job recovery.', [
            'event' => $event, 'entity_id' => $entityId, 'failed_job_id' => $id, 'exception' => $exception::class,
        ]);
    }
}
