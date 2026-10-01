<?php

namespace App\Services\Matching;

use App\Models\AdopterProfile;
use App\Models\Pet;
use App\Services\Matching\DTOs\AdopterData;
use App\Services\Matching\DTOs\PetData;
use InvalidArgumentException;
use LogicException;

final class MatchingProfileMapper
{
    public function __construct(
        private MatchingConfiguration $config,
        private BfiScorer $bfi,
        private PetProfileAggregator $aggregator,
    ) {}

    public function adopter(AdopterProfile $profile): AdopterData
    {
        $personality = [];
        if ($profile->bfi_completed_at && is_array($profile->bfi_responses)) {
            try {
                $personality = $this->bfi->score($profile->bfi_responses);
            } catch (InvalidArgumentException) {
                // An incomplete/old questionnaire must be completed again.
            }
        }
        $housing = $this->config->values['housing'][$profile->housing_type] ?? $profile->housing_type;

        return new AdopterData(
            (int) $profile->id, $personality, $housing,
            $profile->has_existing_pets, $profile->has_children,
            $profile->financial_readiness === null ? null : (float) $profile->financial_readiness,
            $personality !== [],
        );
    }

    public function pet(Pet $pet): PetData
    {
        $aggregation = $this->aggregate($pet);

        return new PetData(
            (int) $pet->id, [
                ...$aggregation['features'],
                'physical_size' => $this->config->values['size_levels'][$pet->physical_size] ?? null,
                'medical_needs' => $pet->medical_needs,
            ],
            $pet->species->value, $pet->availability_status->value,
            $aggregation['observer_count'], $pet->life_stage,
            $pet->aggression_history_verified_at === null ? null : $pet->has_aggression_history,
            $pet->high_vocalization, $pet->is_archived,
        );
    }

    public function aggregate(Pet $pet): array
    {
        if (! $pet->relationLoaded('assessmentRecords')) {
            throw new LogicException('Load assessmentRecords before mapping pets for matching.');
        }
        $records = [];
        foreach ($pet->assessmentRecords as $record) {
            if (($record->responses['species'] ?? null) === strtolower($pet->species->value)) {
                $records[] = [
                    'id' => $record->id, 'observer_id' => $record->assessor_id,
                    'recorded_at' => $record->created_at?->toISOString() ?? '',
                    'responses' => $record->responses['answers'] ?? [],
                ];
            }
        }
        try {
            return $this->aggregator->aggregate($pet->species->value, $records);
        } catch (InvalidArgumentException) {
            return ['observer_count' => 0, 'features' => array_fill_keys(['energy', 'trainability', 'independence', 'temperament'], null), 'complete' => false];
        }
    }

    public function adopterIsComplete(AdopterProfile $profile): bool
    {
        $data = $this->adopter($profile);

        return $data->bfiCompleted && $data->existingPets !== null && $data->hasChildren !== null
            && MatchingConfiguration::validValue($data->financialReadiness)
            && in_array($data->housing, ['apartment', 'condo', 'house_with_yard', 'house_no_yard'], true);
    }
}
