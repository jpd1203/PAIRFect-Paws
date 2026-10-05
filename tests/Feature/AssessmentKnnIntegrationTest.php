<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Services\KnnRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class AssessmentKnnIntegrationTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    private function assessmentPayload(): array
    {
        return [
            'responses' => $this->behaviorResponses('dog', 0),
        ];
    }

    public function test_add_animal_can_start_with_unknown_profile_fields_in_the_existing_wizard(): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator))
            ->post(route('admin.animals.store'), [
                'name' => 'New Intake', 'species' => 'Dog', 'breed' => 'Aspin',
                'status' => 'Assessing', 'health_status' => 'Needs Vet',
            ])->assertRedirect(route('admin.animals.index'));

        $pet = Pet::where('name', 'New Intake')->sole();
        $this->assertSame('Assessing', $pet->availability_status->value);
        foreach (['physical_size', 'medical_needs', 'life_stage', 'has_aggression_history', 'high_vocalization'] as $field) {
            $this->assertNull($pet->{$field});
        }
        $this->assertNull($pet->aggression_history_verified_at);

        $page = $this->get(route('admin.animals.index'))->assertOk()->getContent();
        $basicStep = strpos($page, 'id="addTabPane-1"');
        $healthStep = strpos($page, 'id="addTabPane-2"');
        $storyStep = strpos($page, 'id="addTabPane-3"');
        $this->assertGreaterThan($healthStep, strpos($page, 'name="physical_size"'));
        $this->assertGreaterThan($basicStep, $healthStep);
        $this->assertLessThan($storyStep, strpos($page, 'name="high_vocalization"'));
        foreach (array_keys(config('matching.size_levels')) as $size) {
            $this->assertStringContainsString('<option value="'.$size.'">'.$size.'</option>', $page);
        }
    }

    public function test_add_animal_saves_explicit_five_level_size_and_verified_no_answers(): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator))
            ->post(route('admin.animals.store'), [
                'name' => 'Assessed Intake', 'species' => 'Cat', 'status' => 'Assessing',
                'physical_size' => 'Extra Small', 'medical_needs' => '1',
                'life_stage' => 'young', 'has_aggression_history' => '0', 'high_vocalization' => '0',
            ])->assertRedirect(route('admin.animals.index'));

        $pet = Pet::where('name', 'Assessed Intake')->sole();
        $this->assertSame('Extra Small', $pet->physical_size);
        $this->assertSame(1.0, $pet->medical_needs);
        $this->assertSame('young', $pet->life_stage);
        $this->assertFalse($pet->has_aggression_history);
        $this->assertFalse($pet->high_vocalization);
        $this->assertNotNull($pet->aggression_history_verified_at);
    }

    public function test_profile_fields_are_saved_by_animal_management_and_read_by_matching(): void
    {
        $pet = $this->matchingPet();
        $this->actingAs($this->matchingUser(Role::Administrator))
            ->put(route('admin.pets.update', $pet), [
                'name' => $pet->name, 'species' => $pet->species->value,
                'status' => 'Available', 'version' => $pet->version,
                'physical_size' => 'Extra Large', 'medical_needs' => 5,
                'life_stage' => 'senior', 'has_aggression_history' => '1', 'high_vocalization' => '1',
            ])->assertRedirect(route('admin.animals.index'));

        $pet = $pet->fresh();
        $this->assertSame('Extra Large', $pet->physical_size);
        $this->assertSame(5.0, $pet->medical_needs);
        $this->assertSame('senior', $pet->life_stage);
        $this->assertTrue($pet->has_aggression_history);
        $this->assertTrue($pet->high_vocalization);
        $this->assertNotNull($pet->aggression_history_verified_at);

        $adopter = $this->matchingAdopter(['housing_type' => 'Apartment / Condo']);
        $match = app(KnnRecommendationService::class)->calculateMatch($adopter, $pet);
        $this->assertTrue($match->eligible);
        $this->assertSame(5.0, $match->petVector['physical_size']);
        $this->assertSame(5.0, $match->petVector['medical_needs']);
        $this->assertCount(6, $match->petVector);
        $this->assertSame(1.0, $match->penalty);

        $adopter->update(['has_children' => true]);
        $this->assertSame('EXCLUDED_CHILD_SAFETY', app(KnnRecommendationService::class)
            ->calculateMatch($adopter->fresh(), $pet)->exclusionReason);
    }

    public function test_behavior_assessment_cannot_overwrite_pet_profile_even_with_forged_fields(): void
    {
        $pet = $this->matchingPet([], 0);
        $payload = $this->assessmentPayload() + [
            'physical_size' => 'Extra Large', 'medical_needs' => 5,
            'life_stage' => 'senior', 'has_aggression_history' => '1', 'high_vocalization' => '1',
        ];
        $this->actingAs($this->matchingUser(Role::Volunteer))
            ->post(route('admin.assessments.store', $pet), $payload)
            ->assertRedirect(route('admin.assessments.record'));

        $pet = $pet->fresh();
        $this->assertSame('Medium', $pet->physical_size);
        $this->assertSame(3.0, $pet->medical_needs);
        $this->assertSame('adult', $pet->life_stage);
        $this->assertFalse($pet->has_aggression_history);
        $this->assertFalse($pet->high_vocalization);
        $this->assertDatabaseCount('assessment_records', 1);
    }

    public function test_each_staff_account_can_assess_a_pet_only_once_until_three_distinct_observers_complete_it(): void
    {
        $pet = $this->matchingPet([], 0);
        $admin = $this->matchingUser(Role::Administrator);
        $payload = $this->assessmentPayload();

        $this->actingAs($admin)->get(route('admin.assessments.create', $pet))->assertOk();
        $this->post(route('admin.assessments.store', $pet), $payload)
            ->assertRedirect(route('admin.assessments.record'));
        $this->assertSame(1, $pet->assessmentRecords()->count());
        $this->assertSame(1, $pet->fresh()->assessment_count);
        $this->assertSame('pending', $pet->fresh()->assessment_status);

        $this->get(route('admin.assessments.record'))
            ->assertOk()
            ->assertSee('Your assessment complete')
            ->assertDontSee(route('admin.assessments.create', $pet));
        $this->get(route('admin.assessments.create', $pet))
            ->assertRedirect(route('admin.assessments.record'))
            ->assertSessionHasErrors('assessment');
        $this->post(route('admin.assessments.store', $pet), $payload)
            ->assertRedirect(route('admin.assessments.record'))
            ->assertSessionHasErrors('assessment');
        $this->postJson(route('admin.assessments.store', $pet), $payload)
            ->assertStatus(409);
        $this->assertSame(1, $pet->assessmentRecords()->count());

        $volunteer = $this->matchingUser(Role::Volunteer);
        $this->actingAs($volunteer)->get(route('admin.assessments.record'))
            ->assertOk()
            ->assertSee(route('admin.assessments.create', $pet))
            ->assertSee('#2');
        $this->post(route('admin.assessments.store', $pet), $payload)
            ->assertRedirect(route('admin.assessments.record'));
        $this->assertSame(2, $pet->assessmentRecords()->count());
        $this->assertSame(2, $pet->fresh()->assessment_count);

        $third = $this->matchingUser(Role::Volunteer);
        $this->actingAs($third)->get(route('admin.assessments.record'))
            ->assertOk()->assertSee('#3');
        $this->post(route('admin.assessments.store', $pet), $payload)
            ->assertRedirect(route('admin.assessments.record'));
        $this->assertSame(3, $pet->assessmentRecords()->count());
        $this->assertSame(3, $pet->fresh()->assessment_count);
        $this->assertSame('complete', $pet->fresh()->assessment_status);

        $this->get(route('admin.assessments.record'))
            ->assertOk()
            ->assertSee('Complete')
            ->assertDontSee(route('admin.assessments.create', $pet));

        $this->actingAs($this->matchingUser(Role::Volunteer))->post(route('admin.assessments.store', $pet), $payload)
            ->assertRedirect(route('admin.assessments.record'))
            ->assertSessionHasErrors('assessment');

        $this->postJson(route('admin.assessments.store', $pet), $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', "You have already assessed {$pet->name}, or this pet already has enough distinct observers.");
        $this->assertSame(3, $pet->assessmentRecords()->count());
    }

    public function test_cbarq_zero_to_four_is_normalized_without_reversing_fearfulness(): void
    {
        $pet = $this->matchingPet([], 0);
        $payload = $this->assessmentPayload();
        $payload['responses']['energy']['E1'] = 1;
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->matchingUser(Role::Volunteer))
                ->post(route('admin.assessments.store', $pet), $payload)
                ->assertRedirect(route('admin.assessments.record'));
        }
        $record = $pet->assessmentRecords()->first();
        $this->assertSame(1.0, $record->temperament);
        $this->assertSame(5.0, $record->independence);
        $this->assertSame(2.5, $record->trainability);
        $this->assertEqualsWithDelta(1 + 1 / 3, $pet->fresh()->energy_level, 1e-12);
        $this->assertSame(3, $pet->fresh()->assessment_count);
        $this->assertSame(1, app(KnnRecommendationService::class)->recommendPets($this->matchingAdopter())->count());
    }

    public function test_out_of_range_and_fractional_behavior_answers_are_rejected(): void
    {
        $pet = $this->matchingPet([], 0);
        foreach ([-1, 5, 1.5] as $invalid) {
            $payload = $this->assessmentPayload();
            $payload['responses']['trainability']['T5'] = $invalid;
            $this->actingAs($this->matchingUser(Role::Administrator))
                ->postJson(route('admin.assessments.store', $pet), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('responses.trainability.T5');
        }
        $this->assertDatabaseCount('assessment_records', 0);
    }

    public function test_incomplete_subscales_are_saved_as_null_and_do_not_create_a_match(): void
    {
        $pet = $this->matchingPet([], 0);
        $payload = $this->assessmentPayload();
        $payload['responses']['energy'] = [];
        $this->actingAs($this->matchingUser(Role::Volunteer))
            ->post(route('admin.assessments.store', $pet), $payload)
            ->assertRedirect(route('admin.assessments.record'));
        $this->assertNull(AssessmentRecord::sole()->energy_level);
        $this->assertNull($pet->fresh()->energy_level);
    }

    public function test_new_pet_safety_data_starts_unknown_and_can_remain_unknown_after_behavior_assessment(): void
    {
        $pet = Pet::create(['name' => 'Unverified Test Pet', 'species' => 'Dog']);
        $this->assertNull($pet->fresh()->has_aggression_history);
        $this->assertNull($pet->fresh()->high_vocalization);
        $this->assertNull($pet->fresh()->life_stage);
        $this->assertNull($pet->fresh()->physical_size);
        $this->assertNull($pet->fresh()->medical_needs);

        $this->actingAs($this->matchingUser(Role::Volunteer))
            ->post(route('admin.assessments.store', $pet), $this->assessmentPayload())
            ->assertRedirect(route('admin.assessments.record'));

        $this->assertNull($pet->fresh()->has_aggression_history);
        $this->assertNull($pet->fresh()->high_vocalization);
        $this->assertNull($pet->fresh()->life_stage);
        $this->assertNull($pet->fresh()->physical_size);
        $this->assertNull($pet->fresh()->medical_needs);
        $this->assertDatabaseCount('assessment_records', 1);

        $verified = Pet::create(['name' => 'Verified Test Pet', 'species' => 'Dog', 'has_aggression_history' => false]);
        $this->assertFalse($verified->fresh()->has_aggression_history);
        $this->assertNotNull($verified->fresh()->aggression_history_verified_at);
    }

    public function test_animal_profile_can_clear_safety_answers_and_exclude_matching(): void
    {
        $pet = $this->matchingPet();
        $this->actingAs($this->matchingUser(Role::Volunteer))
            ->put(route('admin.pets.update', $pet), [
                'name' => $pet->name, 'species' => $pet->species->value,
                'status' => 'Available', 'version' => $pet->version,
                'life_stage' => '', 'has_aggression_history' => '', 'high_vocalization' => '',
            ])->assertRedirect(route('admin.animals.index'));

        $this->assertNull($pet->fresh()->life_stage);
        $this->assertNull($pet->fresh()->has_aggression_history);
        $this->assertNull($pet->fresh()->aggression_history_verified_at);
        $this->assertNull($pet->fresh()->high_vocalization);
        $this->assertFalse(Pet::recommendationEligible()->whereKey($pet->id)->exists());
        $this->assertSame('SAFETY_INFORMATION_INCOMPLETE', app(KnnRecommendationService::class)
            ->calculateMatch($this->matchingAdopter(), $pet->fresh())->exclusionReason);
    }

    public function test_legacy_no_without_verification_is_incomplete_until_staff_confirms_it(): void
    {
        $pet = $this->matchingPet();
        $pet->forceFill(['aggression_history_verified_at' => null])->saveQuietly();

        $this->assertFalse($pet->fresh()->has_aggression_history);
        $this->assertFalse(Pet::recommendationEligible()->whereKey($pet->id)->exists());
        $this->assertSame('SAFETY_INFORMATION_INCOMPLETE', app(KnnRecommendationService::class)
            ->calculateMatch($this->matchingAdopter(), $pet->fresh())->exclusionReason);

        $this->actingAs($this->matchingUser(Role::Volunteer));
        $this->get(route('admin.assessments.summary', $pet))->assertOk()
            ->assertSee('verified aggression history')->assertSee('Not verified');
        $form = $this->get(route('admin.animals.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="vAggression" name="has_aggression_history"', $form);
        $this->assertStringContainsString('"has_aggression_history":null', $form);

        $this->put(route('admin.pets.update', $pet), [
            'name' => $pet->name, 'species' => $pet->species->value,
            'status' => 'Available', 'version' => $pet->version,
            'has_aggression_history' => '',
        ])->assertRedirect(route('admin.animals.index'));
        $this->assertFalse($pet->fresh()->has_aggression_history);
        $this->assertNull($pet->fresh()->aggression_history_verified_at);

        $this->put(route('admin.pets.update', $pet), [
            'name' => $pet->name, 'species' => $pet->species->value,
            'status' => 'Available', 'version' => $pet->fresh()->version,
            'has_aggression_history' => '0',
        ])->assertRedirect(route('admin.animals.index'));

        $this->assertNotNull($pet->fresh()->aggression_history_verified_at);
        $this->assertTrue(Pet::recommendationEligible()->whereKey($pet->id)->exists());
    }

    public function test_staff_can_clear_unverified_veterinary_values_without_inventing_a_match(): void
    {
        $pet = $this->matchingPet();
        $this->actingAs($this->matchingUser(Role::Administrator))
            ->put(route('admin.pets.update', $pet), [
                'name' => $pet->name, 'species' => $pet->species->value,
                'status' => 'Available', 'version' => $pet->version,
                'physical_size' => '', 'medical_needs' => '',
            ])->assertRedirect(route('admin.animals.index'));

        $this->assertNull($pet->fresh()->physical_size);
        $this->assertNull($pet->fresh()->medical_needs);
        $this->assertSame('PET_SIZE_NOT_ASSESSED', app(KnnRecommendationService::class)
            ->calculateMatch($this->matchingAdopter(), $pet->fresh())->exclusionReason);
    }

    public function test_recommendation_profile_stores_all_lifestyle_fields_and_twenty_bfi_answers(): void
    {
        $user = $this->matchingUser();
        $fields = [
            'physical_activity_level' => 'Moderate (Daily walks, occasional play)',
            'time_availability' => '2-4 hours/day',
            'prior_pet_experience' => 'Experienced with rescue/special needs animals',
            'housing_type' => 'Single Family Home (Fenced Yard)',
            'household_composition' => 'Living with adults only',
            'monthly_income_range' => '₱30,000 - ₱50,000',
            'has_existing_pets' => false, 'has_children' => false,
            'bfi_responses' => $this->bfiResponses(),
        ];
        $fields['bfi_responses']['EX1'] = 4;
        $this->actingAs($user)->post(route('recommendation.start'), $fields)->assertRedirect(route('recommendation.results'));
        $profile = $user->fresh()->adopterProfile;
        $this->assertFalse($profile->has_existing_pets); // Experience is not current ownership.
        $this->assertEqualsWithDelta(19 / 6, $profile->extraversion, 1e-12);
        $this->assertSame(3, $profile->financial_readiness);
        $this->assertCount(20, $profile->bfi_responses);
        foreach (['physical_activity_level', 'time_availability', 'prior_pet_experience', 'housing_type', 'household_composition', 'monthly_income_range'] as $key) {
            $this->assertSame($fields[$key], $profile->{$key});
        }
        $this->get(route('recommendation.results'))->assertOk();
        $this->postJson(route('recommendation.recompute'), ['energy' => 5, 'knn_score' => 99.99])
            ->assertUnprocessable()->assertJsonValidationErrors(['energy', 'knn_score']);
    }
}
