<?php

namespace App\Services\Matching;

use App\Services\Matching\DTOs\AdopterData;
use App\Services\Matching\DTOs\MatchResult;
use App\Services\Matching\DTOs\PetData;

final class KnnMatcher
{
    public function __construct(
        private MatchingConfiguration $config,
        private MatchFilters $filters,
        private IdealPetProfileBuilder $adopters,
        private PetFeatureBuilder $pets,
        private EuclideanDistanceCalculator $distance,
        private HousingPenaltyCalculator $housing,
        private CompatibilityScorer $scorer,
    ) {}

    public function calculateMatch(AdopterData $adopter, PetData $pet): MatchResult
    {
        if ($reason = $this->filters->exclusionReason($adopter, $pet)) {
            return MatchResult::excluded($adopter->id, $pet->id, $reason, $this->config->version());
        }
        $adopterVector = $this->adopters->build($adopter->personality);
        $petVector = $this->pets->build($pet->features);
        $breakdown = $this->distance->breakdown($adopterVector, $petVector);
        $base = $this->distance->calculate($adopterVector, $petVector);
        $penalty = $this->housing->calculate($adopter, $pet);
        $adjusted = $base + $penalty;

        return new MatchResult(
            $adopter->id, $pet->id, true, null, $base, $penalty, $adjusted,
            $this->scorer->fromDistance($adjusted), $adopterVector, $petVector, $breakdown, $this->config->version(),
        );
    }
}
