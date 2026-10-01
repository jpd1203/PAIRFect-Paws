<?php

namespace App\Services\Matching;

use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Services\KnnRecommendationService;
use Illuminate\Support\Collection;

final class ApplicationMatchService
{
    public const TERMINAL = ['Approved', 'Rejected', 'Withdrawn', 'NoShow', 'Closed'];

    public function __construct(
        private KnnRecommendationService $matcher,
        private MatchingConfiguration $config,
        private MatchingProfileMapper $profiles,
        private MatchPresenter $presenter,
    ) {}

    public function refresh(AdoptionApplication $application): AdoptionApplication
    {
        if (in_array($application->status->value, self::TERMINAL, true)) {
            return $application;
        }
        $application->loadMissing([
            'user.adopterProfile',
            'pet' => fn ($query) => $query->withoutGlobalScope('notArchived'),
            'pet.assessmentRecords',
        ]);
        if (! $application->pet) {
            return $application;
        }
        $profile = $application->user?->adopterProfile;
        $fingerprint = hash('sha256', json_encode([
            $profile ? $this->profiles->adopter($profile) : null,
            $this->profiles->pet($application->pet), $this->config->values,
        ], JSON_THROW_ON_ERROR));
        if ($application->knn_source_fingerprint === $fingerprint) {
            return $application;
        }
        $match = $this->matcher->calculateMatch($profile, $application->pet, existingApplication: true);
        $application->forceFill([
            'knn_score' => $match->compatibilityScore,
            'knn_distance' => $match->adjustedDistance,
            'knn_penalty' => $match->penalty,
            'compatibility_result' => $this->presenter->stored($match),
            'knn_computed_at' => now(),
            'knn_algorithm_version' => $match->algorithmVersion,
            'knn_source_fingerprint' => $fingerprint,
        ])->save();

        return $application;
    }

    public function refreshMany(Collection $applications): Collection
    {
        $applications->loadMissing('user.adopterProfile');
        // A normal pet relation hides archived pets. Load them explicitly so an existing
        // score is invalidated as PET_UNAVAILABLE rather than silently kept as current.
        $applications->load([
            'pet' => fn ($query) => $query->withoutGlobalScope('notArchived'),
            'pet.assessmentRecords',
        ]);

        return $applications->each(fn ($application) => $this->refresh($application));
    }

    public function forUser(int $userId): void
    {
        $this->refreshMany(AdoptionApplication::where('user_id', $userId)->whereNotIn('status', self::TERMINAL)->get());
    }

    public function forPet(int $petId): void
    {
        $this->refreshMany(AdoptionApplication::where('pet_id', $petId)->whereNotIn('status', self::TERMINAL)->get());
    }

    public function refreshPetSummary(Pet $pet): void
    {
        $pet->load('assessmentRecords');
        $summary = $this->profiles->aggregate($pet);
        // Legacy rounded summaries remain history until a new raw assessment is recorded.
        if ($pet->assessment_scoring_version !== null || $pet->assessmentRecords->contains(fn ($record) => $record->responses !== null)) {
            $pet->forceFill([
                'energy_level' => $summary['features']['energy'],
                'trainability' => $summary['features']['trainability'],
                'independence' => $summary['features']['independence'],
                'temperament' => $summary['features']['temperament'],
                'assessment_count' => $summary['observer_count'],
                'assessment_scoring_version' => $this->config->version(),
            ])->saveQuietly();
        }
        $this->forPet($pet->id);
    }
}
