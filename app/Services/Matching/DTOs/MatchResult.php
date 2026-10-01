<?php

namespace App\Services\Matching\DTOs;

final readonly class MatchResult
{
    public function __construct(
        public int $adopterId,
        public int $petId,
        public bool $eligible,
        public ?string $exclusionReason,
        public ?float $baseDistance,
        public ?float $penalty,
        public ?float $adjustedDistance,
        public ?float $compatibilityScore,
        public array $adopterVector,
        public array $petVector,
        public array $featureBreakdown,
        public string $algorithmVersion,
    ) {}

    public static function excluded(int $adopterId, int $petId, string $reason, string $version): self
    {
        return new self($adopterId, $petId, false, $reason, null, null, null, null, [], [], [], $version);
    }

    public function toArray(): array
    {
        return [
            'adopter_id' => $this->adopterId, 'pet_id' => $this->petId,
            'eligible' => $this->eligible, 'exclusion_reason' => $this->exclusionReason,
            'base_distance' => $this->baseDistance, 'penalty' => $this->penalty,
            'adjusted_distance' => $this->adjustedDistance,
            'compatibility_score' => $this->compatibilityScore,
            'adopter_vector' => $this->adopterVector, 'pet_vector' => $this->petVector,
            'feature_breakdown' => $this->featureBreakdown, 'algorithm_version' => $this->algorithmVersion,
        ];
    }
}
