<?php

namespace App\Services\Matching;

use App\Services\Matching\DTOs\MatchResult;

final class MatchPresenter
{
    public const LABELS = [
        'energy' => 'Energy', 'trainability' => 'Trainability', 'independence' => 'Independence',
        'temperament' => 'Fearfulness / Reactivity', 'physical_size' => 'Size', 'medical_needs' => 'Medical Needs',
    ];

    public function stored(MatchResult $result): array
    {
        $rows = [];
        foreach ($result->featureBreakdown as $key => $feature) {
            $rows[] = [
                'label' => self::LABELS[$key],
                // Per-feature closeness for display only; the final score comes exclusively from CompatibilityScorer.
                'percent' => round(100 * (1 - abs($feature['diff']) / 4), 1),
            ];
        }

        return [...$result->toArray(), 'overall' => $result->compatibilityScore === null ? null : round($result->compatibilityScore, 2), 'rows' => $rows];
    }

    public static function reason(?string $reason): string
    {
        return match ($reason) {
            'ADOPTER_PROFILE_INCOMPLETE' => 'Complete the personality and household profile before calculating compatibility.',
            'PET_PROFILE_INCOMPLETE' => 'The pet needs three distinct observers and complete behavioral scores.',
            'PET_SIZE_NOT_ASSESSED' => 'The pet needs a veterinary-assessed physical size before matching.',
            'MEDICAL_NEEDS_NOT_ASSESSED' => 'The pet needs a veterinary-assessed medical-needs level before matching.',
            'SAFETY_INFORMATION_INCOMPLETE' => 'The pet needs verified life stage and shelter safety information before matching.',
            'PET_UNAVAILABLE' => 'The pet is not currently available for matching.',
            'EXCLUDED_EXISTING_PETS_REACTIVE' => 'This reactive pet is not recommended for a home with existing pets.',
            'EXCLUDED_CHILD_SAFETY' => 'The pet’s aggression history requires a household without children.',
            'EXCLUDED_FINANCIAL_READINESS' => 'The pet’s life stage or medical needs exceed the recorded care capacity.',
            'UNSUPPORTED_SPECIES' => 'Matching supports cats and dogs.',
            default => 'Compatibility has not been calculated.',
        };
    }
}
