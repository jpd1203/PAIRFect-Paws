<?php

namespace App\Contracts;

use App\ValueObjects\MediaVerificationResult;

interface MediaVerifier
{
    public function verify(string $absolutePath): MediaVerificationResult;
}
