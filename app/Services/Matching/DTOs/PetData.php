<?php

namespace App\Services\Matching\DTOs;

final readonly class PetData
{
    public function __construct(
        public int $id,
        public array $features,
        public string $species,
        public string $availability,
        public int $observerCount,
        public ?string $lifeStage,
        public ?bool $aggressionHistory,
        public ?bool $highVocalization,
        public bool $archived = false,
    ) {}
}
