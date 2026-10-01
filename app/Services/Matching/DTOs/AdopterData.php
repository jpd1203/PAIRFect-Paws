<?php

namespace App\Services\Matching\DTOs;

final readonly class AdopterData
{
    public function __construct(
        public int $id,
        public array $personality,
        public ?string $housing,
        public ?bool $existingPets,
        public ?bool $hasChildren,
        public ?float $financialReadiness,
        public bool $bfiCompleted = true,
    ) {}
}
