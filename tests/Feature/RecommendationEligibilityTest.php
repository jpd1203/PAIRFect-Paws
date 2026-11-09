<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AssessmentRecord;
use App\Services\KnnRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function test_all_candidates_are_ranked_before_an_optional_limit_and_ties_use_pet_id(): void
    {
        $profile = $this->matchingAdopter();
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->matchingPet(['medical_needs' => 5])->id;
        }
        $best = $this->matchingPet();
        $matches = app(KnnRecommendationService::class)->recommendPets($profile);
        $this->assertCount(6, $matches);
        $this->assertSame([$best->id, ...$ids], $matches->pluck('pet.id')->all());
        $this->assertSame(100.0, $matches->first()['match']->compatibilityScore);
        $this->assertCount(1, app(KnnRecommendationService::class)->recommendPets($profile, 1));
    }

    public function test_results_paginate_only_eligible_pets_and_exclude_ineligible_pets(): void
    {
        $user = $this->matchingUser();
        $this->completeMatchingProfile($user);
        $ids = [];
        for ($i = 1; $i <= 7; $i++) {
            $ids[] = $this->matchingPet(['name' => "Ranked Dog {$i}"])->id;
        }
        $this->matchingPet(['name' => 'Filtered Cat', 'species' => 'Cat']);
        $reserved = $this->matchingPet(['name' => 'Reserved Dog', 'availability_status' => 'Soft-Reserved']);

        $results = $this->actingAs($user)->get(route('recommendation.results', ['species' => 'Dog']))
            ->assertOk()
            ->assertSeeInOrder(['id="matchResults"', 'Showing 1 to 5 of 7 pets', 'page=2'], false)
            ->assertDontSee('Filtered Cat')
            ->assertDontSee('Reserved Dog')
            ->assertViewHas('matches', fn ($page): bool => $page instanceof LengthAwarePaginator
                && $page->total() === 7
                && $page->getCollection()->pluck('pet.id')->all() === array_slice($ids, 0, 5)
                && str_contains($page->url(2), 'species=Dog'));

        $this->assertSame(1, substr_count($results->getContent(), 'Showing 1 to 5 of 7 pets'));
        $this->assertSame(1, substr_count($results->getContent(), 'id="petModal"'));

        $this->get(route('recommendation.results', ['species' => 'Dog', 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 6 to 7 of 7 pets')
            ->assertDontSee('Reserved Dog')
            ->assertDontSee('Ineligible')
            ->assertViewHas('matches', fn ($page): bool => $page->total() === 7
                && $page->getCollection()->pluck('pet.id')->all() === array_slice($ids, 5));

        $this->postJson(route('recommendation.recompute'), ['species' => 'Dog'])
            ->assertOk()
            ->assertJsonCount(7, 'matches');

        $this->postJson(route('recommendation.recompute', ['include_ineligible' => 1]), ['species' => 'Dog'])
            ->assertOk()
            ->assertJsonCount(8, 'matches')
            ->assertJsonPath('matches.7.eligible', false)
            ->assertJsonPath('matches.7.compatibility_score', null)
            ->assertJsonPath('matches.7.match_label', 'Ineligible');
    }

    public function test_ineligible_only_results_display_empty_state_without_showing_ineligible_pets(): void
    {
        $user = $this->matchingUser();
        $this->completeMatchingProfile($user);
        for ($i = 1; $i <= 6; $i++) {
            $this->matchingPet(['name' => "Awaiting Assessment {$i}"], 0);
        }
        $this->matchingPet(['name' => 'Archived Pet', 'is_archived' => true]);

        $this->actingAs($user)->get(route('recommendation.results'))
            ->assertOk()
            ->assertSee('No pets found for this page or the selected filters.')
            ->assertDontSee('Awaiting Assessment')
            ->assertDontSee('Ineligible to adopt')
            ->assertDontSee('Archived Pet');
    }

    public function test_safety_excluded_pet_has_reason_on_modal_and_cannot_be_adopted(): void
    {
        $user = $this->matchingUser();
        $this->completeMatchingProfile($user, ['has_children' => true]);
        $pet = $this->matchingPet(['name' => 'Safety Review', 'has_aggression_history' => true]);

        // Ineligible pets do not appear in recommendations
        $this->actingAs($user)->get(route('recommendation.results'))->assertOk()
            ->assertDontSee('Safety Review');

        // Modal shows safety reason and blocks application
        $this->actingAs($user)->get(route('pets.modal', $pet))->assertOk()
            ->assertSee('Ineligible')
            ->assertSee(\App\Services\Matching\MatchPresenter::reason('EXCLUDED_CHILD_SAFETY'))
            ->assertDontSee('href="'.route('application.apply', $pet).'"', false);

        $this->postJson(route('recommendation.recompute', ['include_ineligible' => 1]))->assertOk()
            ->assertJsonPath('matches.0.eligible', false)
            ->assertJsonPath('matches.0.exclusion_reason', 'EXCLUDED_CHILD_SAFETY')
            ->assertJsonPath('matches.0.compatibility_score', null);
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
        $results = $this->get(route('recommendation.results'))
            ->assertOk()
            ->assertSee('Guest Match')
            ->assertSee('id="petModal"', false)
            ->assertSee('role="dialog" aria-modal="true" aria-label="Pet profile"', false)
            ->assertSee('openPetModal('.$pet->id.')', false)
            ->assertSee(route('pets.show', ['pet' => $pet, 'from' => 'matching']), false);
        $this->assertSame(1, substr_count($results->getContent(), 'id="petModal"'));
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
