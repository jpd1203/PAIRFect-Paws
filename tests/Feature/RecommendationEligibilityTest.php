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

    public function test_repeated_assessments_by_the_same_observer_satisfy_the_requirement(): void
    {
        $pet = $this->matchingPet([], 1);
        $record = $pet->assessmentRecords->first();
        foreach ([1, 2] as $unused) {
            AssessmentRecord::create($record->only(['pet_id', 'assessor_id', 'responses', 'scoring_version']));
        }
        $this->assertCount(1, app(KnnRecommendationService::class)->recommendPets($this->matchingAdopter()));
    }

    public function test_recommendations_allow_guests_and_unverified_adopters_but_block_staff(): void
    {
        $this->get(route('recommendation.intake'))->assertOk();
        $this->get(route('recommendation.results'))->assertRedirect(route('recommendation.intake'));
        $user = $this->matchingUser();
        $this->actingAs($user)->get(route('recommendation.results'))->assertRedirect(route('recommendation.intake'));
        $this->actingAs($user)->postJson(route('recommendation.recompute'))->assertUnprocessable();
        $user->update(['email_verified_at' => null]);
        $this->actingAs($user)->get(route('recommendation.intake'))
            ->assertOk()
            ->assertSee('Verify your email address before you can apply to adopt a pet.');
        $this->actingAs($this->matchingUser(Role::Volunteer))->get(route('recommendation.intake'))->assertRedirect(route('access-denied'));
    }

    public function test_guest_matching_profile_is_saved_for_the_session_and_transferred_on_login(): void
    {
        $pet = $this->matchingPet(['name' => 'Guest Match']);
        $user = $this->matchingUser();

        $this->post(route('recommendation.start'), [
            'housing_type' => 'Single Family Home (Fenced Yard)',
            'monthly_income_range' => '₱30,000 - ₱50,000',
            'has_existing_pets' => false,
            'has_children' => false,
            'bfi_responses' => $this->bfiResponses(),
        ])->assertRedirect(route('recommendation.results'));

        $this->assertNotNull(session('guest_adopter_profile'));
        $this->get(route('recommendation.results'))
            ->assertOk()
            ->assertSee('Guest Match')
            ->assertSee('id="petModal"', false)
            ->assertSee('openPetModal('.$pet->id.')', false)
            ->assertSee(route('pets.show', ['pet' => $pet, 'from' => 'matching']), false);
        $this->get(route('pets.modal', $pet))->assertOk()->assertSee('Guest Match');
        $this->get(route('application.apply', $pet))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Testing123!',
        ])->assertRedirect();

        $this->assertNotNull($user->fresh()->adopterProfile?->bfi_completed_at);
        $this->assertNull(session('guest_adopter_profile'));
    }

    public function test_unverified_adopter_can_save_a_profile_and_view_matches_but_cannot_adopt(): void
    {
        $user = $this->matchingUser();
        $user->forceFill(['email_verified_at' => null])->save();
        $pet = $this->matchingPet(['name' => 'Preview Match']);

        $this->actingAs($user)
            ->get(route('recommendation.onboarding', ['return_pet' => $pet->id]))
            ->assertOk();

        $this->post(route('recommendation.start'), [
            'housing_type' => 'Single Family Home (Fenced Yard)',
            'monthly_income_range' => '₱30,000 - ₱50,000',
            'has_existing_pets' => false,
            'has_children' => false,
            'bfi_responses' => $this->bfiResponses(),
        ])->assertRedirect(route('recommendation.results'));

        $user = $user->fresh();
        $this->actingAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNotNull($user->adopterProfile?->bfi_completed_at);
        $this->get(route('recommendation.results'))
            ->assertOk()
            ->assertSee('Preview Match')
            ->assertSee('id="petModal"', false)
            ->assertSee('Verify your email address before you can apply to adopt a pet.')
            ->assertDontSee('href="'.route('application.apply', $pet).'"', false);
        $this->postJson(route('recommendation.recompute'))
            ->assertOk()
            ->assertJsonPath('matches.0.id', $pet->id);
        $this->get(route('application.apply', $pet))->assertRedirect(route('verification.notice'));
        $this->post(route('application.submit'), ['pet_id' => $pet->id])
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('adoption_applications', 0);

        $user->markEmailAsVerified();
        $this->actingAs($user->fresh());
        $this->get(route('recommendation.results'))
            ->assertOk()
            ->assertSee('href="'.route('application.apply', $pet).'"', false);
    }
}
