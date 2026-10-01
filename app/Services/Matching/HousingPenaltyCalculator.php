<?php

namespace App\Services\Matching;

use App\Services\Matching\DTOs\AdopterData;
use App\Services\Matching\DTOs\PetData;

final class HousingPenaltyCalculator
{
    public function __construct(private MatchingConfiguration $config) {}

    public function calculate(AdopterData $adopter, PetData $pet): float
    {
        return in_array($adopter->housing, ['apartment', 'condo'], true)
            && ($pet->features['energy'] >= $this->config->values['filters']['high_energy_threshold'] || $pet->highVocalization)
            ? (float) $this->config->values['spatial_penalty'] : 0.0;
    }
}
