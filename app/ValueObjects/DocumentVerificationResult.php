<?php

namespace App\ValueObjects;

use App\Enums\DocumentVerificationStatus;

final readonly class DocumentVerificationResult
{
    public function __construct(
        public DocumentVerificationStatus $status,
        public ?string $extractedText,
        public ?float $ocrConfidence,
        public float $matchScore,
        public array $reasons,
        public ?string $documentType = null,
    ) {}

    public function isVerified(): bool
    {
        return $this->status === DocumentVerificationStatus::Verified;
    }
}
