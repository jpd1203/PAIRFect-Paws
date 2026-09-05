<?php

namespace App\ValueObjects;

use DateTimeImmutable;

final readonly class MediaVerificationResult
{
    private const INVALID_PHOTO_MESSAGE = 'We could not verify this photo\'s live-capture credentials. Please take a new photo with the live camera tool.';

    private const TOOL_UNAVAILABLE_MESSAGE = 'Photo verification is temporarily unavailable. Please try again later or contact the shelter.';

    private const VERIFIED_MESSAGE = 'The photo\'s Content Credentials were verified.';

    private function __construct(
        public bool $valid,
        public bool $toolUnavailable,
        public string $reasonCode,
        public string $userMessage,
        public ?string $manifestId = null,
        public ?DateTimeImmutable $signingTime = null,
    ) {}

    public static function valid(string $manifestId, DateTimeImmutable $signingTime): self
    {
        return new self(
            valid: true,
            toolUnavailable: false,
            reasonCode: 'verified',
            userMessage: self::VERIFIED_MESSAGE,
            manifestId: $manifestId,
            signingTime: $signingTime,
        );
    }

    public static function invalid(
        string $reasonCode,
        ?string $manifestId = null,
        ?DateTimeImmutable $signingTime = null,
    ): self {
        return new self(
            valid: false,
            toolUnavailable: false,
            reasonCode: $reasonCode,
            userMessage: self::INVALID_PHOTO_MESSAGE,
            manifestId: $manifestId,
            signingTime: $signingTime,
        );
    }

    public static function unavailable(string $reasonCode): self
    {
        return new self(
            valid: false,
            toolUnavailable: true,
            reasonCode: $reasonCode,
            userMessage: self::TOOL_UNAVAILABLE_MESSAGE,
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isInvalid(): bool
    {
        return ! $this->valid && ! $this->toolUnavailable;
    }

    public function isToolUnavailable(): bool
    {
        return $this->toolUnavailable;
    }

    public function hasNoManifest(): bool
    {
        return $this->isInvalid() && $this->reasonCode === 'missing_active_manifest';
    }
}
