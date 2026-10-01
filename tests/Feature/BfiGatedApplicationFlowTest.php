<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Services\DocumentVerificationService;
use App\Services\Matching\ApplicantRankingService;
use App\Services\Matching\ApplicationMatchService;
use App\Services\ReservationQueueService;
use App\ValueObjects\DocumentVerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class BfiGatedApplicationFlowTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    private function applicationPayload($user, $pet): array
    {
        return [
            'pet_id' => $pet->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => '09171234567',
            'region_code' => '1300000000',
            'province_code' => '__direct__',
            'city_municipality_code' => '1380600000',
            'barangay_code' => '1380606197',
            'street_address' => '4489 V. Francisco St. Sta. Mesa',
            'zip_code' => '1016',
            'motivation_statement' => 'A safe permanent home for this pet.',
            'physical_activity_level' => 'Moderate (Daily walks, occasional play)',
            'time_availability' => '2-4 hours/day',
            'prior_pet_experience' => 'First-time owner',
            'household_composition' => 'Living with adults only',
            'agreed_to_terms' => '1',
            'document' => UploadedFile::fake()->image('identity.jpg'),
            'knn_score' => 98.50,
            'knn_distance' => 0,
        ];
    }

    public function test_browsing_works_without_bfi_and_incomplete_application_returns_to_the_same_pet_with_draft(): void
    {
        $user = $this->matchingUser();
        $pet = $this->matchingPet();

        $this->assertSame(200, $this->actingAs($user)->get(route('animal.index'))->status(), 'Animal browsing');
        $petPage = $this->get(route('pets.show', $pet));
        $this->assertSame(200, $petPage->status(), 'Pet detail redirects to '.$petPage->headers->get('Location'));
        $this->get(route('application.apply', $pet))
            ->assertRedirect(route('recommendation.intake', ['return_pet' => $pet->id]));

        $payload = $this->applicationPayload($user, $pet);
        $payload['motivation_statement'] = 'Please remember my application details.';
        $this->post(route('application.submit'), $payload)
            ->assertRedirect(route('recommendation.intake', ['return_pet' => $pet->id]));
        $this->assertDatabaseCount('adoption_applications', 0);
        $this->assertSame([], Storage::disk('local')->files('adoption-documents'));

        $profilePage = $this->get(route('recommendation.intake', ['return_pet' => $pet->id]));
        $this->assertSame(200, $profilePage->status(), 'Profile redirects to '.$profilePage->headers->get('Location'));
        $profilePage->assertSee('Personality Assessment Required');
        $this->post(route('recommendation.start'), [
            'housing_type' => 'Single Family Home (Fenced Yard)',
            'monthly_income_range' => '₱30,000 - ₱50,000',
            'has_existing_pets' => false,
            'has_children' => false,
            'bfi_responses' => $this->bfiResponses(),
        ])->assertRedirect(route('application.apply', $pet));

        $returnPage = $this->get(route('application.apply', $pet));
        $this->assertSame(200, $returnPage->status(), 'Application redirects to '.$returnPage->headers->get('Location'));
        $returnPage
            ->assertSee('Please remember my application details.')
            ->assertSee('Please reattach your supporting document')
            ->assertSee('Review / Update Assessment')
            ->assertDontSee('bfi_responses[EX1]', false);
        $this->assertCount(20, $user->fresh()->adopterProfile->bfi_responses);
        $this->assertDatabaseCount('adoption_applications', 0);
    }

    public function test_one_profile_scores_two_applications_and_ignores_browser_supplied_matching_data(): void
    {
        $user = $this->matchingUser();
        $profile = $this->completeMatchingProfile($user);
        $firstPet = $this->matchingPet();
        $secondPet = $this->matchingPet(['medical_needs' => 5]);
        $this->app->instance(DocumentVerificationService::class, \Mockery::mock(DocumentVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->twice()->andReturn(new DocumentVerificationResult(
                DocumentVerificationStatus::Verified, 'TEST ID', null, 0.95, [], 'Government ID',
            ));
        }));

        foreach ([$firstPet, $secondPet] as $pet) {
            $this->actingAs($user)->get(route('application.apply', $pet))
                ->assertOk()->assertDontSee('bfi_responses[EX1]', false);
            $this->post(route('application.submit'), $this->applicationPayload($user, $pet))
                ->assertRedirect(route('application.index'));
            $this->assertSame('Available', $pet->fresh()->availability_status->value);
        }

        $applications = AdoptionApplication::orderBy('id')->get();
        $this->assertCount(2, $applications);
        $this->assertSame(100.0, $applications[0]->knn_score);
        $this->assertLessThan($applications[0]->knn_score, $applications[1]->knn_score);
        foreach ($applications as $application) {
            $this->assertTrue($application->compatibility_result['eligible']);
            $this->assertCount(6, $application->compatibility_result['feature_breakdown']);
            $this->assertNotNull($application->knn_computed_at);
            $this->assertNotEmpty($application->knn_source_fingerprint);
            $this->assertSame(config('matching.algorithm_version'), $application->knn_algorithm_version);
        }
        $this->assertDatabaseCount('adopter_profiles', 1);
        $this->assertSame($profile->bfi_responses, $user->fresh()->adopterProfile->bfi_responses);
    }

    public function test_safety_exclusion_and_incomplete_pet_prevent_a_formal_application_before_document_processing(): void
    {
        $user = $this->matchingUser();
        $this->completeMatchingProfile($user, ['has_children' => true]);
        $unsafePet = $this->matchingPet(['has_aggression_history' => true]);
        $incompletePet = $this->matchingPet(['name' => 'No observations'], 0);
        $verifier = \Mockery::mock(DocumentVerificationService::class);
        $verifier->shouldNotReceive('verify');
        $this->app->instance(DocumentVerificationService::class, $verifier);

        foreach ([$unsafePet, $incompletePet] as $pet) {
            $this->actingAs($user)->post(route('application.submit'), $this->applicationPayload($user, $pet))
                ->assertSessionHasErrors('pet_id');
        }
        $this->assertDatabaseCount('adoption_applications', 0);
    }

    public function test_higher_score_becomes_primary_and_promotion_uses_score_after_soft_reservation(): void
    {
        $pet = $this->matchingPet();
        $staff = $this->matchingUser(Role::Administrator);
        $users = [$this->matchingUser(), $this->matchingUser(), $this->matchingUser()];
        $applications = [];

        foreach ($users as $i => $user) {
            $answers = $this->bfiResponses();
            $answers['EX1'] = [1, 3, 4][$i];
            $this->completeMatchingProfile($user, ['bfi_responses' => $answers]);
            $applications[$i] = AdoptionApplication::create([
                'user_id' => $user->id, 'pet_id' => $pet->id,
                'status' => ApplicationStatus::UnderReview->value,
                'document_verification_status' => DocumentVerificationStatus::Verified->value,
            ]);
            $applications[$i]->timestamps = false;
            $applications[$i]->created_at = now()->subHours(3 - $i);
            $applications[$i]->saveQuietly();
        }

        $ranked = app(ApplicantRankingService::class)->eligibleForPet($pet, [ApplicationStatus::UnderReview]);
        $this->assertSame([$applications[1]->id, $applications[2]->id, $applications[0]->id], $ranked->pluck('id')->all());
        $this->assertNotNull($ranked->last()->knn_score);

        $queue = app(ReservationQueueService::class);
        $this->expectSchedulingError($queue, $applications[0], $staff);
        $queue->schedule($applications[1], now()->addDay(), $staff, $staff->id);
        $this->assertSame('Soft-Reserved', $pet->fresh()->availability_status->value);
        $this->assertSame(1, AdoptionApplication::where('pet_id', $pet->id)->where('is_primary_candidate', true)->count());
        $this->assertSame(ApplicationStatus::Waitlisted, $applications[0]->fresh()->status);
        $this->assertNotNull($applications[2]->fresh()->knn_score);
        $this->actingAs($users[0])->get(route('application.apply', $pet))->assertStatus(422);

        $promoted = $queue->resolvePrimary($applications[1], ApplicationStatus::NoShow, 'Missed the scheduled interview.', $staff->id);
        $this->assertSame($applications[2]->id, $promoted?->id);
        $this->assertSame(1, AdoptionApplication::where('pet_id', $pet->id)->where('is_primary_candidate', true)->count());
        $this->assertSame(ApplicationStatus::Waitlisted, $applications[0]->fresh()->status);
    }

    public function test_equal_full_precision_scores_use_submission_time_then_application_id(): void
    {
        $pet = $this->matchingPet();
        $users = [$this->matchingUser(), $this->matchingUser(), $this->matchingUser()];
        $apps = [];
        foreach ($users as $user) {
            $this->completeMatchingProfile($user);
            $apps[] = AdoptionApplication::create([
                'user_id' => $user->id, 'pet_id' => $pet->id,
                'status' => ApplicationStatus::UnderReview->value,
                'document_verification_status' => DocumentVerificationStatus::Verified->value,
            ]);
        }
        app(ApplicationMatchService::class)->refreshMany(AdoptionApplication::all());
        foreach ($apps as $i => $app) {
            $app->timestamps = false;
            $app->created_at = $i === 1 ? '2026-08-01 09:00:00' : '2026-08-02 09:00:00';
            $app->saveQuietly();
        }
        $ranked = app(ApplicantRankingService::class)->eligibleForPet($pet, [ApplicationStatus::UnderReview]);
        $this->assertSame([$apps[1]->id, $apps[0]->id, $apps[2]->id], $ranked->pluck('id')->all());
    }

    public function test_ranking_uses_full_precision_when_display_scores_are_equal(): void
    {
        $pet = $this->matchingPet();
        $firstUser = $this->matchingUser();
        $secondUser = $this->matchingUser();
        $this->completeMatchingProfile($firstUser);
        $this->completeMatchingProfile($secondUser);

        $older = AdoptionApplication::create([
            'user_id' => $firstUser->id, 'pet_id' => $pet->id,
            'status' => ApplicationStatus::UnderReview->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);
        $newer = AdoptionApplication::create([
            'user_id' => $secondUser->id, 'pet_id' => $pet->id,
            'status' => ApplicationStatus::UnderReview->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);

        foreach ([[$older, 90.0041], [$newer, 90.0042]] as [$application, $score]) {
            $application->knn_score = 90.00;
            $application->created_at = $application->id === $older->id
                ? now()->subDay() : now();
            $application->compatibility_result = [
                'eligible' => true,
                'compatibility_score' => $score,
            ];
        }

        $ranked = app(ApplicantRankingService::class)->sort(collect([$older, $newer]));
        $this->assertSame([$newer->id, $older->id], $ranked->pluck('id')->all());
    }

    public function test_updating_reusable_bfi_responses_refreshes_pending_scores_and_order(): void
    {
        $pet = $this->matchingPet();
        $first = $this->matchingUser();
        $second = $this->matchingUser();
        $this->completeMatchingProfile($first);
        $secondAnswers = $this->bfiResponses();
        $secondAnswers['EX1'] = 4;
        $this->completeMatchingProfile($second, ['bfi_responses' => $secondAnswers]);
        $firstApp = AdoptionApplication::create([
            'user_id' => $first->id, 'pet_id' => $pet->id, 'status' => 'UnderReview',
            'document_verification_status' => 'Verified',
        ]);
        $secondApp = AdoptionApplication::create([
            'user_id' => $second->id, 'pet_id' => $pet->id, 'status' => 'UnderReview',
            'document_verification_status' => 'Verified',
        ]);
        $ranking = app(ApplicantRankingService::class);
        $this->assertSame([$firstApp->id, $secondApp->id], $ranking->eligibleForPet($pet, [ApplicationStatus::UnderReview])->pluck('id')->all());
        $before = $firstApp->fresh()->knn_source_fingerprint;

        $updatedAnswers = $this->bfiResponses();
        $updatedAnswers['EX1'] = 1;
        $this->actingAs($first)->post(route('recommendation.start'), [
            'housing_type' => 'Single Family Home (Fenced Yard)',
            'monthly_income_range' => '₱30,000 - ₱50,000',
            'has_existing_pets' => false, 'has_children' => false,
            'bfi_responses' => $updatedAnswers,
        ])->assertRedirect(route('recommendation.results'));

        $this->assertNotSame($before, $firstApp->fresh()->knn_source_fingerprint);
        $this->assertSame([$secondApp->id, $firstApp->id], $ranking->eligibleForPet($pet, [ApplicationStatus::UnderReview])->pluck('id')->all());
        $this->assertCount(20, $first->fresh()->adopterProfile->bfi_responses);
    }

    private function expectSchedulingError(ReservationQueueService $queue, AdoptionApplication $application, $staff): void
    {
        try {
            $queue->schedule($application, now()->addDay(), $staff, $staff->id);
            $this->fail('A lower-ranked applicant was scheduled before the best match.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('application_id', $exception->errors());
        }
    }
}
