<?php

namespace App\Mail\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

trait UsesResendIdempotency
{
    public string $deliveryIdempotencyKey;

    protected function initializeDeliveryIdempotency(?string $key = null): void
    {
        $this->deliveryIdempotencyKey = $key ?: 'pairfectpaws-'.Str::uuid()->toString();
    }

    /** @return list<\Closure(Email): void> */
    protected function resendEnvelopeCallbacks(): array
    {
        $usesResend = config('mail.default') === 'resend'
            || strtolower((string) config('mail.mailers.smtp.host')) === 'smtp.resend.com';

        if (! $usesResend) {
            return [];
        }

        return [function (Email $message): void {
            $message->getHeaders()->addTextHeader(
                'Resend-Idempotency-Key',
                $this->deliveryIdempotencyKey,
            );
        }];
    }
}
