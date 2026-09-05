<?php

namespace App\Services;

use App\Models\Pet;
use Illuminate\Support\Collection;

/**
 * Six-dimensional K-nearest-neighbours pet recommendation engine.
 *
 * Every adopter and pet dimension uses the same 1-5 scale. Pet temperament
 * is also directional: 1 means fearful/reactive and 5 means calm/safe.
 */
class KnnRecommendationService
{
    private const ACTIVITY_MAP = [
        'Low (Sedentary, short walks)' => 1,
        'Moderate (Daily walks, occasional play)' => 3,
        'High (Active, jogging, hiking)' => 5,
    ];

    private const TIME_MAP = [
        'Less than 2 hours/day' => 5,
        '2-4 hours/day' => 4,
        '4-8 hours/day' => 2,
        'More than 8 hours/day (Work from home / Retired)' => 1,
    ];

    private const EXPERIENCE_MAP = [
        'First-time owner' => 5,
        'Have owned pets in the past' => 4,
        'Currently own pets' => 3,
        'Experienced with rescue/special needs animals' => 1,
    ];

    private const HOUSING_MAP = [
        'Apartment / Condo' => 1,
        'Townhouse' => 2,
        'Single Family Home (No Yard)' => 3,
        'Single Family Home (Fenced Yard)' => 5,
    ];

    private const COMPOSITION_MAP = [
        'Living with children (under 12)' => 5,
        'Living with teenagers' => 4,
        'Living with adults only' => 3,
        'Living alone' => 2,
    ];

    private const INCOME_MAP = [
        'Below ₱15,000' => 1,
        '₱15,000 - ₱30,000' => 2,
        '₱30,000 - ₱50,000' => 3,
        '₱50,000 - ₱80,000' => 4,
        'Above ₱80,000' => 5,
    ];

    /**
     * @param  array<string, mixed>  $inputs
     * @return Collection<int, array{pet: Pet, distance: float, result: array<string, mixed>}>
     */
    public function run(array $inputs): Collection
    {
        $adopter = $this->encodeAdopter($inputs);
        $pets = $this->fetchAndFilter($adopter);

        return $this->rankAndFormat($pets, $adopter);
    }

    /**
     * Calculate the same six-dimensional distance used by recommendation
     * ranking for one pet, for storage on an adoption application.
     *
     * @param  array<string, mixed>  $inputs
     */
    public function distanceFor(Pet $pet, array $inputs): float
    {
        return $this->distance($pet, $this->encodeAdopter($inputs));
    }

    /**
     * @param  array<string, mixed>  $sliders
     * @return Collection<int, array{pet: Pet, distance: float, result: array<string, mixed>}>
     */
    public function recompute(array $sliders): Collection
    {
        $pets = Pet::recommendationEligible()->get();
        $adopter = (object) [
            'activity' => $this->normalizedSlider($sliders['energy'] ?? 3),
            'time' => $this->normalizedSlider($sliders['independence'] ?? 3),
            'experience' => $this->normalizedSlider($sliders['trainability'] ?? 3),
            'housing' => 3,
            'composition' => $this->normalizedSlider($sliders['temperament'] ?? 3),
            'income' => $this->normalizedSlider($sliders['medical'] ?? 3),
            'housing_type' => null,
            'has_existing_pets' => false,
            'has_young_children' => false,
            'is_low_income' => false,
            'is_apartment' => false,
        ];

        return $this->rankAndFormat($pets, $adopter);
    }

    /** @param array<string, mixed> $inputs */
    private function encodeAdopter(array $inputs): object
    {
        $housingType = (string) ($inputs['housing_type'] ?? '');
        $householdComposition = (string) ($inputs['household_composition'] ?? '');
        $incomeRange = (string) ($inputs['monthly_income_range'] ?? '');

        return (object) [
            'activity' => self::ACTIVITY_MAP[$inputs['physical_activity_level'] ?? ''] ?? 3,
            'time' => self::TIME_MAP[$inputs['time_availability'] ?? ''] ?? 3,
            'experience' => self::EXPERIENCE_MAP[$inputs['prior_pet_experience'] ?? ''] ?? 3,
            'housing' => self::HOUSING_MAP[$housingType] ?? 3,
            'composition' => self::COMPOSITION_MAP[$householdComposition] ?? 3,
            'income' => self::INCOME_MAP[$incomeRange] ?? 3,
            'housing_type' => $housingType,
            'has_existing_pets' => ($inputs['has_existing_pets'] ?? 'no') === 'yes',
            'has_young_children' => $householdComposition === 'Living with children (under 12)',
            'is_low_income' => $incomeRange === 'Below ₱15,000',
            'is_apartment' => $housingType === 'Apartment / Condo',
        ];
    }

    private function fetchAndFilter(object $adopter): Collection
    {
        return Pet::recommendationEligible()->get()->filter(function (Pet $pet) use ($adopter) {
            if ($adopter->has_existing_pets && $pet->is_reactive_to_pets) {
                return false;
            }

            if ($adopter->has_young_children && $pet->has_aggression_history) {
                return false;
            }

            if ($adopter->is_low_income) {
                $isSenior = $pet->age && $pet->age >= 84;
                $isHighMedical = $pet->medical_needs && $pet->medical_needs >= 4;
                if ($isSenior || $isHighMedical) {
                    return false;
                }
            }

            return true;
        });
    }

    private function rankAndFormat(Collection $pets, object $adopter): Collection
    {
        $maxDistance = sqrt(6 * (4 ** 2));

        return $pets
            ->map(function (Pet $pet) use ($adopter, $maxDistance) {
                $petEnergy = (float) $pet->energy_level;
                $petIndependence = (float) $pet->independence;
                $petTrainability = (float) $pet->trainability;
                $petSize = $this->encodeSize($pet->physical_size);
                $petTemperament = (float) $pet->temperament;
                $petMedical = (float) $pet->medical_needs;
                $distance = $this->distance($pet, $adopter);
                $overall = max(0, (int) round((1 - $distance / ($maxDistance + 2.0)) * 100));

                return [
                    'pet' => $pet,
                    'distance' => $distance,
                    'result' => [
                        'overall' => $overall,
                        'rows' => [
                            ['label' => 'Energy Match', 'percent' => $this->dimensionScore($adopter->activity, $petEnergy)],
                            ['label' => 'Time Fit', 'percent' => $this->dimensionScore($adopter->time, $petIndependence)],
                            ['label' => 'Experience Fit', 'percent' => $this->dimensionScore($adopter->experience, $petTrainability)],
                            ['label' => 'Space Fit', 'percent' => $this->dimensionScore($adopter->housing, $petSize)],
                            ['label' => 'Temperament Fit', 'percent' => $this->dimensionScore($adopter->composition, $petTemperament)],
                            ['label' => 'Care Capacity', 'percent' => $this->dimensionScore($adopter->income, $petMedical)],
                        ],
                    ],
                ];
            })
            ->sortBy('distance')
            ->values();
    }

    private function distance(Pet $pet, object $adopter): float
    {
        $petEnergy = (float) $pet->energy_level;
        $distance = sqrt(
            ($adopter->activity - $petEnergy) ** 2
            + ($adopter->time - (float) $pet->independence) ** 2
            + ($adopter->experience - (float) $pet->trainability) ** 2
            + ($adopter->housing - $this->encodeSize($pet->physical_size)) ** 2
            + ($adopter->composition - (float) $pet->temperament) ** 2
            + ($adopter->income - (float) $pet->medical_needs) ** 2
        );

        if ($adopter->is_apartment && $petEnergy >= 4) {
            $distance += 2.0;
        }

        return $distance;
    }

    private function dimensionScore(float $adopterValue, float $petValue): int
    {
        return max(0, (int) round((1 - abs($adopterValue - $petValue) / 4) * 100));
    }

    private function encodeSize(?string $size): float
    {
        return match (strtolower((string) $size)) {
            'extra small', 'xs' => 1,
            'small', 's' => 2,
            'medium', 'm' => 3,
            'large', 'l' => 4,
            'extra large', 'xl' => 5,
            default => throw new \UnexpectedValueException('Recommendation pet has an invalid physical size.'),
        };
    }

    private function normalizedSlider(mixed $value): int
    {
        return max(1, min(5, (int) $value));
    }

    public function matchLabel(int $score): string
    {
        if ($score >= 80) {
            return 'High Match';
        }
        if ($score >= 60) {
            return 'Good Match';
        }
        if ($score >= 40) {
            return 'Fair Match';
        }

        return 'Low Match';
    }
}
