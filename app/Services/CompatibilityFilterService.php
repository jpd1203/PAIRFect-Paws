<?php

namespace App\Services;

class CompatibilityFilterService
{
    /**
     * Applies safety and compatibility filtering before running KNN.
     *
     * @param object $pet
     * @param object $adopterProfile
     * @return bool True if compatible, false if excluded by safety rules.
     */
    public function isCompatible(object $pet, object $adopterProfile): bool
    {
        // Rule: Highly reactive pets cannot go to homes with other pets
        if (isset($pet->is_reactive) && $pet->is_reactive && isset($adopterProfile->has_other_pets) && $adopterProfile->has_other_pets) {
            return false;
        }

        // Rule: High energy dogs should ideally have a yard or very active adopter
        if (isset($pet->is_high_energy) && $pet->is_high_energy && 
            isset($adopterProfile->housing_type) && $adopterProfile->housing_type === 'Apartment' && 
            isset($adopterProfile->activity_level) && $adopterProfile->activity_level < 4) {
            return false;
        }

        return true;
    }
}
