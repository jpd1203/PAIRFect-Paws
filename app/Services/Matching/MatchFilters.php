<?php

namespace App\Services\Matching;

use App\Services\Matching\DTOs\AdopterData;
use App\Services\Matching\DTOs\PetData;
use InvalidArgumentException;

final class MatchFilters
{
    public function __construct(
        private MatchingConfiguration $config,
        private IdealPetProfileBuilder $adopters,
        private PetFeatureBuilder $pets,
    ) {}

    public function exclusionReason(AdopterData $adopter, PetData $pet): ?string
    {
        if (! $adopter->bfiCompleted || $adopter->existingPets === null || $adopter->hasChildren === null
            || ! in_array($adopter->housing, ['apartment', 'condo', 'house_with_yard', 'house_no_yard'], true)
            || ! MatchingConfiguration::validValue($adopter->financialReadiness)) {
            return 'ADOPTER_PROFILE_INCOMPLETE';
        }
        try {
            $this->adopters->build($adopter->personality);
        } catch (InvalidArgumentException) {
            return 'ADOPTER_PROFILE_INCOMPLETE';
        }
        if ($pet->archived || $pet->availability !== 'Available') {
            return 'PET_UNAVAILABLE';
        }
        if (! in_array($pet->species, ['Dog', 'Cat'], true)) {
            return 'UNSUPPORTED_SPECIES';
        }
        if ($pet->observerCount < $this->config->values['min_observers']) {
            return 'PET_PROFILE_INCOMPLETE';
        }
        if (! MatchingConfiguration::validValue($pet->features['physical_size'] ?? null)) {
            return 'PET_SIZE_NOT_ASSESSED';
        }
        if (! MatchingConfiguration::validValue($pet->features['medical_needs'] ?? null)) {
            return 'MEDICAL_NEEDS_NOT_ASSESSED';
        }
        if ($pet->aggressionHistory === null || $pet->highVocalization === null
            || ! in_array($pet->lifeStage, ['young', 'adult', 'senior'], true)) {
            return 'SAFETY_INFORMATION_INCOMPLETE';
        }
        try {
            $features = $this->pets->build($pet->features);
        } catch (InvalidArgumentException) {
            return 'PET_PROFILE_INCOMPLETE';
        }
        $filters = $this->config->values['filters'];
        if ($adopter->existingPets && $features['temperament'] >= $filters['reactive_threshold']) {
            return 'EXCLUDED_EXISTING_PETS_REACTIVE';
        }
        if ($adopter->hasChildren && $pet->aggressionHistory) {
            return 'EXCLUDED_CHILD_SAFETY';
        }
        if ($adopter->financialReadiness <= $filters['low_financial_level']
            && ($pet->lifeStage !== 'adult' || $features['medical_needs'] > $filters['healthy_max_medical'])) {
            return 'EXCLUDED_FINANCIAL_READINESS';
        }

        return null;
    }
}
