<?php

namespace Tests\Feature;

use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Services\DocumentVerificationService;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicantRankingService;
use App\Services\Matching\ApplicationMatchService;
use App\ValueObjects\DocumentVerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class MatchingWorkflowTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    private function applicationPayload($user, $pet): array
    {
        return [
            'pet_id' => $pet->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => $user->email, 'phone_number' => '09171234567',
            'region_code' => '1300000000', 'province_code' => '__direct__',
            'city_municipality_code' => '1380600000', 'barangay_code' => '1380606197',
            'street_address' => '4489 V. Francisco St. Sta. Mesa', 'zip_code' => '1016',
            'motivation_statement' => 'I can provide a safe and permanent home.',
            'housing_type' => 'Single Family Home (Fenced Yard)',
            'physical_activity_level' => 'Moderate (Daily walks, occasional play)',
            'time_availability' => '2-4 hours/day',
            'prior_pet_experience' => 'First-time owner',
            'household_composition' => 'Living with adults only',
            'monthly_income_range' => '₱30,000 - ₱50,000',
            'has_existing_pets' => false, 'has_children' => false,
            'agreed_to_terms' => '1',
            'document' => UploadedFile::fake()->image('identity.jpg'),
            'knn_score' => 1.23, 'compatibility_result' => ['overall' => 1.23],
        ];
    }

    public function test_application_recomputes_score_from_saved_bfi_and_ignores_browser_score(): void
    {
        Mail::fake();
        Storage::fake('local');
        $profile = $this->matchingAdopter();
        $pet = $this->matchingPet();
        $verifier = \Mockery::mock(DocumentVerificationService::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new DocumentVerificationResult(
            DocumentVerificationStatus::Verified, 'TEST ADOPTER ID', null, 1.0, [], 'Government ID',
        ));
        $this->app->instance(DocumentVerificationService::class, $verifier);

        $this->actingAs($profile->user)->post(route('application.submit'), $this->applicationPayload($profile->user, $pet))
            ->assertRedirect(route('application.index'));
        $application = AdoptionApplication::sole();
        $recommendation = app(KnnRecommendationService::class)->recommendPets($profile);
        $this->assertSame(100.0, $recommendation->first()['match']->compatibilityScore);
        $this->assertSame(100.0, $application->knn_score);
        $this->assertSame(0.0, $application->knn_distance);
        $this->assertSame(0.0, $application->knn_penalty);
        $this->assertSame(config('matching.algorithm_version'), $application->knn_algorithm_version);
        $this->assertEqualsWithDelta(100.0, $application->compatibility_result['compatibility_score'], 1e-12);
        $this->assertCount(6, $application->compatibility_result['feature_breakdown']);
        $this->assertNotEmpty($application->knn_source_fingerprint);
        $this->assertNull($application->compatibility_result['exclusion_reason']);
    }

    public function test_profile_and_pet_changes_recompute_pending_applications_and_rank_by_score_then_time(): void
    {
        $pet = $this->matchingPet();
        $first = $this->matchingAdopter();
        $second = $this->matchingAdopter();
        $a = AdoptionApplication::create([
            'user_id' => $first->user_id, 'pet_id' => $pet->id, 'status' => 'Pending',
        ]);
        $b = AdoptionApplication::create([
            'user_id' => $second->user_id, 'pet_id' => $pet->id, 'status' => 'Pending',
        ]);
        $scorer = app(ApplicationMatchService::class);
        $scorer->refreshMany(AdoptionApplication::all());
        $a->timestamps = false;
        $b->timestamps = false;
        $a->created_at = '2026-08-02 09:00:00';
        $b->created_at = '2026-08-01 09:00:00';
        $a->saveQuietly();
        $b->saveQuietly();
        $ranking = app(ApplicantRankingService::class)->rankApplicants($pet);
        $this->assertSame([$b->id, $a->id], $ranking->pluck('id')->all());

        $first->update(['housing_type' => 'Apartment / Condo']);
        $this->assertSame(0.0, $a->fresh()->knn_penalty); // Energy 3 needs no penalty.
        $pet->update(['high_vocalization' => true]);
        $this->assertSame(1.0, $a->fresh()->knn_penalty);
        $this->assertSame(0.0, $b->fresh()->knn_penalty);
        $this->assertSame([$b->id, $a->id], app(ApplicantRankingService::class)->rankApplicants($pet)->pluck('id')->all());

        $pet->update(['availability_status' => 'Soft-Reserved']);
        $this->assertNotNull($a->fresh()->knn_score);
        $this->assertTrue($a->fresh()->compatibility_result['eligible']);
        $this->assertDatabaseCount('adoption_applications', 2);
    }

    public function test_child_safety_exclusion_persists_application_without_a_fake_score(): void
    {
        $pet = $this->matchingPet(['has_aggression_history' => true]);
        $profile = $this->matchingAdopter(['has_children' => true]);
        $application = AdoptionApplication::create([
            'user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => 'Pending',
        ]);
        $result = app(ApplicationMatchService::class)->refresh($application);
        $this->assertNull($result->knn_score);
        $this->assertSame('EXCLUDED_CHILD_SAFETY', $result->compatibility_result['exclusion_reason']);
        $this->assertDatabaseCount('adoption_applications', 1);
    }

    public function test_archiving_a_pet_invalidates_pending_scores_but_preserves_final_application_history(): void
    {
        $pet = $this->matchingPet();
        $profile = $this->matchingAdopter();
        $application = AdoptionApplication::create([
            'user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => 'Pending',
        ]);
        app(ApplicationMatchService::class)->refresh($application);
        $this->assertSame(100.0, $application->knn_score);
        $historical = $application->replicate();
        $historical->status = 'Rejected';
        $historical->save();
        $pet->update(['is_archived' => true]);
        $this->assertNull($application->fresh()->knn_score);
        $this->assertSame('PET_UNAVAILABLE', $application->fresh()->compatibility_result['exclusion_reason']);
        $this->assertSame(100.0, $historical->fresh()->knn_score);
        $this->assertSame('Pending', $application->fresh()->status->value);
    }

    public function test_saved_questionnaire_is_rendered_and_staff_can_inspect_the_shared_breakdown(): void
    {
        $profile = $this->matchingAdopter();
        $pet = $this->matchingPet();
        AdoptionApplication::create(['user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => 'Pending']);
        $this->actingAs($profile->user)->get(route('recommendation.intake'))
            ->assertOk()->assertSee('bfi_responses[EX1]', false)->assertSee('bfi_responses[O4]', false);
        $this->get(route('application.apply', $pet))->assertOk()
            ->assertSee('Personality questionnaire completed')
            ->assertSee('Review / Update Assessment')
            ->assertDontSee('bfi_responses[EX1]', false);
        $this->actingAs($this->matchingUser(Role::Volunteer))
            ->get(route('admin.pets.ranked-applicants', $pet))->assertOk()
            ->assertSee('100.00%')->assertSee('pet-card-compat')->assertSee('View Breakdown')
            ->assertDontSee('Algorithm:')->assertDontSee('Base distance:')
            ->assertDontSee('Housing penalty:')->assertDontSee('Adjusted distance:');
        $this->get(route('admin.assessments.create', $pet))->assertOk()
            ->assertSee('responses[trainability][T5]', false)
            ->assertDontSee('name="physical_size"', false)
            ->assertDontSee('name="medical_needs"', false)
            ->assertDontSee('name="life_stage"', false)
            ->assertDontSee('name="has_aggression_history"', false)
            ->assertDontSee('name="high_vocalization"', false)
            ->assertDontSee('KNN matching flags')
            ->assertSee('id="desktopProgressBar"', false);
        $animalPage = $this->get(route('admin.animals.index'))->assertOk();
        foreach (['physical_size', 'medical_needs', 'life_stage', 'has_aggression_history', 'high_vocalization'] as $field) {
            $animalPage->assertSee('name="'.$field.'"', false);
        }
        $unassessedPet = $this->matchingPet(['name' => 'Awaiting assessment'], 0);
        $this->get(route('admin.assessments.record'))->assertOk()
            ->assertSee('assessmentTableBody')->assertSee('assessmentSummaryModal')
            ->assertSee('aria-label="Assessment complete"', false)
            ->assertDontSee('Reassess')
            ->assertDontSee(route('admin.assessments.create', $pet))
            ->assertSee(route('admin.assessments.create', $unassessedPet));
        $this->get(route('admin.assessments.summary', $pet))->assertOk()
            ->assertSee('custom-modal-header')->assertSee('Fearfulness / Reactivity');
    }

    public function test_adopter_cannot_inspect_staff_only_ranking_or_raw_bfi_data(): void
    {
        $profile = $this->matchingAdopter();
        $this->actingAs($profile->user)->get(route('admin.compatibility.index'))->assertRedirect(route('access-denied'));
        $this->actingAs($profile->user)->get(route('recommendation.results'))
            ->assertOk()->assertDontSee('bfi_responses');
    }
}
