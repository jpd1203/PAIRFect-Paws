<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AssessmentRecord;
use App\Services\KnnRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class RecommendationEligibilityTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_only_available_complete_distinct_observer_profiles_are_recommended(): void
    {
        $profile = $this->matchingAdopter();
        $this->matchingPet(['name' => 'Phantom', 'assessment_count' => 3, 'energy_level' => 3], 0);
        $this->matchingPet(['name' => 'Only two observers'], 2);
        $this->matchingPet(['physical_size' => null]);
        $this->matchingPet(['availability_status' => 'Soft-Reserved']);
        $this->matchingPet(['availability_status' => 'Adopted']);
        $eligible = $this->matchingPet(['name' => 'Eligible']);
        $matches = app(KnnRecommendationService::class)->recommendPets($profile);
        $this->assertSame([$eligible->id], $matches->pluck('pet.id')->all());
    }

    public function test_all_candidates_are_scored_before_top_k_and_ties_use_pet_id(): void
    {
        $profile = $this->matchingAdopter();
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->matchingPet(['medical_needs' => 5])->id;
        }
        $best = $this->matchingPet();
        $matches = app(KnnRecommendationService::class)->recommendPets($profile);
        $this->assertCount(5, $matches);
        $this->assertSame([$best->id, ...array_slice($ids, 0, 4)], $matches->pluck('pet.id')->all());
        $this->assertSame(100.0, $matches->first()['match']->compatibilityScore);
        $this->assertCount(1, app(KnnRecommendationService::class)->recommendPets($profile, 1));
    }

    public function test_repeated_assessments_by_the_same_observer_do_not_satisfy_the_requirement(): void
    {
        $pet = $this->matchingPet([], 1);
        $record = $pet->assessmentRecords->first();
        foreach ([1, 2] as $unused) {
            AssessmentRecord::create($record->only(['pet_id', 'assessor_id', 'responses', 'scoring_version']));
        }
        $this->assertCount(0, app(KnnRecommendationService::class)->recommendPets($this->matchingAdopter()));
    }

    public function test_recommendations_require_verified_adopter_and_ignore_no_profile_defaults(): void
    {
        $this->get(route('recommendation.intake'))->assertRedirect(route('login'));
        $user = $this->matchingUser();
        $this->actingAs($user)->get(route('recommendation.results'))->assertRedirect(route('recommendation.intake'));
        $this->actingAs($user)->postJson(route('recommendation.recompute'))->assertUnprocessable();
        $user->update(['email_verified_at' => null]);
        $this->actingAs($user)->get(route('recommendation.intake'))->assertRedirect(route('verification.notice'));
        $this->actingAs($this->matchingUser(Role::Volunteer))->get(route('recommendation.intake'))->assertRedirect(route('access-denied'));
    }
}
