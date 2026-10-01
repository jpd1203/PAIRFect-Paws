<?php

namespace App\Services\Matching;

use InvalidArgumentException;

final class PetFeatureBuilder
{
    public function build(array $features): array
    {
        $vector = [];
        foreach (MatchingConfiguration::FEATURES as $feature) {
            if (! MatchingConfiguration::validValue($features[$feature] ?? null)) {
                throw new InvalidArgumentException('PET_PROFILE_INCOMPLETE');
            }
            $vector[$feature] = (float) $features[$feature];
        }

        return $vector;
    }
}
