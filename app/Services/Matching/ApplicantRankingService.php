<?php

namespace App\Services\Matching;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Services\KnnRecommendationService;
use Illuminate\Support\Collection;

final class ApplicantRankingService
{
    public function __construct(private ApplicationMatchService $scores) {}

    public function rankApplicants(?Pet $pet = null): Collection
    {
        $query = AdoptionApplication::with(['user.adopterProfile', 'pet.assessmentRecords']);
        if ($pet) {
            $query->where('pet_id', $pet->id);
        }
        $applications = $this->scores->refreshMany($query->get());

        return $this->sort($applications);
    }

    /** Call inside the pet row lock when selecting or promoting a primary candidate. */
    public function eligibleForPet(Pet $pet, array $statuses): Collection
    {
        $applications = AdoptionApplication::where('pet_id', $pet->id)
            ->whereIn('status', array_map(fn (ApplicationStatus $status) => $status->value, $statuses))
            ->whereIn('document_verification_status', [
                DocumentVerificationStatus::Verified->value,
                DocumentVerificationStatus::LegacyReview->value,
            ])
            ->lockForUpdate()->get();

        return $this->sort($this->scores->refreshMany($applications)->filter(fn ($application) => $this->isEligible($application)));
    }

    public function isEligible(AdoptionApplication $application): bool
    {
        $score = $application->compatibility_result['compatibility_score'] ?? null;

        return ($application->compatibility_result['eligible'] ?? false) === true
            && is_numeric($score) && is_finite((float) $score)
            && ($application instanceof ResearchPlacement ? $application->user?->is_active && $application->user->isAdopter() : in_array($application->document_verification_status, [
                DocumentVerificationStatus::Verified, DocumentVerificationStatus::LegacyReview,
            ], true))
            && ! in_array($application->status, [
                ApplicationStatus::DocumentFlagged, ApplicationStatus::Approved, ApplicationStatus::Rejected,
                ApplicationStatus::Withdrawn, ApplicationStatus::NoShow, ApplicationStatus::Closed,
            ], true);
    }

    /** Only used inside the research pet lock. Refresh every candidate before ranking. */
    public function eligibleResearchForPet(Pet $pet, bool $locked = true): Collection
    {
        $candidates = ResearchPlacement::where('pet_id', $pet->id)->where('status', ApplicationStatus::Waitlisted->value)
            ->when($locked, fn ($query) => $query->lockForUpdate())->get()->load('user.adopterProfile');
        foreach ($candidates as $candidate) {
            $this->refreshResearch($candidate, $pet, $locked);
        }

        return $this->sort($candidates->filter(fn ($candidate) => $this->isEligible($candidate)));
    }

    public function refreshResearch(ResearchPlacement $candidate, Pet $pet, bool $persist = true): void
    {
        $match = app(KnnRecommendationService::class)->calculateMatch($candidate->user?->adopterProfile, $pet, existingApplication: true);
        $candidate->fill([
            'compatibility_result' => $match->toArray(),
            'compatibility_score' => $match->compatibilityScore,
            'algorithm_version' => $match->algorithmVersion,
        ]);
        if ($persist) {
            $candidate->save();
        }
    }

    public function sort(Collection $applications): Collection
    {
        return $applications->sort(function ($a, $b) {
            $aRankable = $this->isEligible($a);
            $bRankable = $this->isEligible($b);

            return ($bRankable <=> $aRankable)
                ?: ($aRankable && $bRankable
                    ? (($b->compatibility_result['compatibility_score'] <=> $a->compatibility_result['compatibility_score']))
                    : 0)
                ?: ($a->created_at <=> $b->created_at)
                ?: ($a->id <=> $b->id);
        })->values();
    }
}
