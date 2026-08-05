<?php

namespace App\Services;

use App\Models\Pet;

/**
 * Computes the live "Pet Characteristics" compatibility scores shown on the
 * Pet Recommendation results screen.
 *
 * The adopter drags 5 sliders describing the kind of pet they're looking
 * for (Energy Level, Independence Level, Trainability, Medical Needs,
 * Temperament tolerance) — each 1-5. Every slider is compared directly
 * against that same behavioral attribute on each candidate pet to produce
 * a per-dimension compatibility percentage. A 6th, non-slider row (Housing
 * vs Size) is derived from the adopter's saved intake answers (housing
 * type vs household composition) rather than a live slider, since housing
 * doesn't change while browsing matches.
 *
 * These six feature-distance rows are the same style of comparison fed
 * into the capstone's trained KNN adopter-pet matching model; this service
 * gives the instant client-facing approximation used to drive the sliders.
 */
class PetMatchService
{
    private const MAP_HOUSING_SIZE = ['House with yard' => 3, 'Condominium' => 2, 'Apartment' => 1];
    private const MAP_PET_SIZE = ['Large' => 3, 'Medium' => 2, 'Small' => 1];
    private const MAP_HOUSEHOLD = [
        'Household with toddlers/infants' => 5, 'Household with young children' => 4,
        'Household with teenager' => 3, 'Couple Only / Roommates' => 2, 'Lives Alone' => 1,
    ];

    /**
     * @param  array{energy:int,independence:int,trainability:int,medical:int,temperament:int}  $sliders  1-5 each
     * @param  array{housing_type:string,household_composition:string}  $adopter  saved intake answers
     * @return array{overall:int,rows:array<int,array{label:string,percent:int}>}
     */
    public function score(array $sliders, array $adopter, Pet $pet): array
    {
        $housingSize = self::MAP_HOUSING_SIZE[$adopter['housing_type']] ?? 2;
        $petSize = self::MAP_PET_SIZE[$pet->physical_size] ?? 2;
        $householdNeed = self::MAP_HOUSEHOLD[$adopter['household_composition']] ?? 1;

        $rows = [
            ['label' => 'Activity vs energy', 'percent' => $this->closeness($sliders['energy'], $pet->energy_level)],
            ['label' => 'Time vs Independence', 'percent' => $this->closeness($sliders['independence'], $pet->independence_level)],
            ['label' => 'Experience vs Trainability', 'percent' => $this->closeness($sliders['trainability'], $pet->trainability)],
            ['label' => 'Housing vs Size', 'percent' => $this->closeness($housingSize, $petSize, 50)],
            ['label' => 'Housing vs Temperament', 'percent' => $this->atLeast($sliders['temperament'], min(5, $householdNeed))],
            ['label' => 'Finance vs Medical needs', 'percent' => $this->closeness($sliders['medical'], $pet->medical_needs)],
        ];

        $overall = (int) round(collect($rows)->avg('percent'));

        return ['overall' => $overall, 'rows' => $rows];
    }

    /** 100% when both values match exactly, decreasing with distance. */
    private function closeness(int $a, int $b, int $stepPenalty = 25): int
    {
        return max(0, min(100, 100 - abs($a - $b) * $stepPenalty));
    }

    /** 100% once $have meets/exceeds $need; penalised per point of shortfall. */
    private function atLeast(int $have, int $need, int $stepPenalty = 25): int
    {
        $shortfall = max(0, $need - $have);
        return max(0, min(100, 100 - $shortfall * $stepPenalty));
    }

    public function matchLabel(int $overall): string
    {
        return match (true) {
            $overall >= 80 => 'High Match',
            $overall >= 60 => 'Good Match',
            $overall >= 40 => 'Fair Match',
            default => 'Low Match',
        };
    }
}
