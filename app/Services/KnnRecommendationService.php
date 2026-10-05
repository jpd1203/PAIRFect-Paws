<?php

namespace App\Services;

use App\Models\AdopterProfile;
use App\Models\Pet;
use App\Services\Matching\DTOs\MatchResult;
use App\Services\Matching\DTOs\PetData;
use App\Services\Matching\KnnMatcher;
use App\Services\Matching\MatchingConfiguration;
use App\Services\Matching\MatchingProfileMapper;
use App\Services\Matching\MatchPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class KnnRecommendationService
{
    public function __construct(
        private KnnMatcher $matcher,
        private MatchingProfileMapper $profiles,
        private MatchingConfiguration $config,
        private MatchPresenter $presenter,
    ) {}

    public function calculateMatch(?AdopterProfile $adopter, Pet $pet, bool $existingApplication = false): MatchResult
    {
        if (! $adopter) {
            $result = MatchResult::excluded(0, $pet->id, 'ADOPTER_PROFILE_INCOMPLETE', $this->config->version());
        } else {
            $pet->loadMissing('assessmentRecords');
            $petData = $this->profiles->pet($pet);
            // Existing applications retain their safety and compatibility ranking while
            // the pet is reserved. New recommendations and applications still require Available.
            if ($existingApplication && $petData->availability === 'Soft-Reserved') {
                $petData = new PetData(
                    $petData->id, $petData->features, $petData->species, 'Available',
                    $petData->observerCount, $petData->lifeStage, $petData->aggressionHistory,
                    $petData->highVocalization, $petData->archived,
                );
            }
            $result = $this->matcher->calculateMatch($this->profiles->adopter($adopter), $petData);
        }
        if (config('app.debug')) {
            // Deliberately omit raw answers, vectors, addresses, and financial information.
            Log::debug('Matching result', array_intersect_key($result->toArray(), array_flip([
                'algorithm_version', 'adopter_id', 'pet_id', 'eligible', 'exclusion_reason',
                'base_distance', 'penalty', 'adjusted_distance', 'compatibility_score',
            ])));
        }

        return $result;
    }

    public function recommendPets(AdopterProfile $adopter, ?int $limit = null, array $preferences = [], bool $includeIneligible = false): Collection
    {
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('Recommendation limit must be at least 1.');
        }
        if (! $this->profiles->adopterIsComplete($adopter)) {
            throw ValidationException::withMessages(['profile' => MatchPresenter::reason('ADOPTER_PROFILE_INCOMPLETE')]);
        }
        $query = ($includeIneligible ? Pet::query() : Pet::recommendationEligible())->with('assessmentRecords');
        if (! empty($preferences['species'])) {
            $query->where('species', $preferences['species']);
        }
        if (! empty($preferences['size'])) {
            $query->where('physical_size', $preferences['size']);
        }

        $matches = $query->get()->map(function (Pet $pet) use ($adopter) {
            $match = $this->calculateMatch($adopter, $pet);

            return ['pet' => $pet, 'match' => $match, 'distance' => $match->adjustedDistance, 'result' => $this->presenter->stored($match)];
        })->filter(fn ($item) => $includeIneligible || $item['match']->eligible)
            ->sort(fn ($a, $b) => ($b['match']->eligible <=> $a['match']->eligible)
                ?: ($b['match']->compatibilityScore <=> $a['match']->compatibilityScore)
                ?: ($a['match']->adjustedDistance <=> $b['match']->adjustedDistance)
                ?: ($a['pet']->id <=> $b['pet']->id))
            ->values();

        return $limit === null ? $matches : $matches->take($limit);
    }

    public function matchLabel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'High Match', $score >= 60 => 'Good Match',
            $score >= 40 => 'Fair Match', default => 'Low Match',
        };
    }
}
