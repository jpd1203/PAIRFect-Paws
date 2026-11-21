<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Models\User;
use App\Services\KnnRecommendationService;
use Database\Seeders\ResearchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

final class ResearchScenarioTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_catalog_distribution_and_species_matching(): void
    {
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);
        $this->assertDatabaseCount('research_catalog', 70);
        $this->assertDatabaseCount('assessment_records', 210);
        $this->assertDatabaseCount('research_placements', 0);
        $this->assertDatabaseCount('adoption_applications', 0);
        $this->assertSame(70, Pet::fullyAssessed()->count());
        $this->assertSame(70, Pet::distinct()->count('name'));
        $this->assertSame(0, Pet::whereNotNull('photo_path')->count());
        $this->assertTrue(Pet::all()->every(fn (Pet $pet) => $pet->hasClassifiedPhysicalSize()));
        foreach (['Dog', 'Cat'] as $species) {
            $this->assertCount(35, Pet::where('species', $species)->get());
        }
        foreach (['young' => 22, 'adult' => 34, 'senior' => 14] as $stage => $count) {
            $this->assertSame($count, Pet::where('life_stage', $stage)->count());
        }
        foreach ([1 => 28, 2 => 16, 3 => 13, 4 => 8, 5 => 5] as $need => $count) {
            $this->assertSame($count, Pet::where('medical_needs', $need)->count());
        }
        $this->assertSame(70, DB::table('assessment_records')->select('pet_id')->groupBy('pet_id')->havingRaw('COUNT(DISTINCT assessor_id) = 3')->get()->count());
        $this->assertSame(3, User::where('is_active', false)->count());
        $this->assertSame(5, Pet::where('medical_needs', 5)->where('availability_status', 'On Hold')->count());
        $this->assertEqualsCanonicalizing(['Complete', 'Incomplete', 'Unknown'], Pet::distinct()->pluck('vaccination_record_status')->all());
        $matcher = app(KnnRecommendationService::class);
        $profile = $this->matchingAdopter();
        $this->assertCount(57, $matcher->recommendPets($profile));
        $this->assertCount(29, $matcher->recommendPets($profile, preferences: ['species' => 'Dog']));
        $this->assertCount(28, $matcher->recommendPets($profile, preferences: ['species' => 'Cat']));
        $this->assertGreaterThan(10, $matcher->recommendPets($profile)->pluck('result.compatibility_score')->unique()->count());
        $this->assertSame('EXCLUDED_FINANCIAL_READINESS', $matcher->calculateMatch($this->matchingAdopter(['financial_readiness' => 1]), Pet::first())->exclusionReason);
        $this->assertSame('EXCLUDED_EXISTING_PETS_REACTIVE', $matcher->calculateMatch($this->matchingAdopter(['has_existing_pets' => true]), Pet::where('name', 'Isla')->firstOrFail())->exclusionReason);
        $this->assertCount(30, $matcher->recommendPets($this->matchingAdopter(['financial_readiness' => 1, 'has_existing_pets' => true])));
        $this->expectExceptionMessage('fresh research database');
        $this->seed(ResearchCatalogSeeder::class);
    }

    public function test_registration_suppresses_verification_in_research_only(): void
    {
        Notification::fake();
        Queue::fake();
        config(['research.enabled' => true]);
        $payload = ['first_name' => 'Survey', 'last_name' => 'Respondent', 'email' => 'survey@example.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'region_code' => '1300000000', 'province_code' => '__direct__',
            'city_municipality_code' => '1380600000', 'barangay_code' => '1380606197',
            'street_address' => 'Fictional Street', 'zip_code' => '1016', 'terms_accepted' => '1', 'privacy_consent' => '1'];
        $this->post(route('register.store'), array_replace($payload, ['privacy_consent' => '0']))->assertSessionHasErrors('privacy_consent');
        $this->post(route('register.store'), $payload)->assertRedirect(route('recommendation.onboarding'));
        $user = User::where('email', 'survey@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->privacy_consent_at);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->get(route('research.index'))->assertOk()->assertDontSee('Verify your email');
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
        config(['research.enabled' => false]);
        $this->get(route('application.index'))->assertRedirect(route('verification.notice'));

        config(['research.enabled' => true]);
        $staff = $this->matchingUser(Role::Volunteer);
        $staff->update(['email_verified_at' => null]);
        $this->actingAs($staff)->get(route('admin.applications.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('research.index'))->assertRedirect(route('access-denied'));
    }

    public function test_queue_uses_current_compatibility_and_enforces_owner_and_current_primary(): void
    {
        config(['research.enabled' => true]);
        Mail::fake();
        Queue::fake();
        Notification::fake();
        $this->seed(ResearchCatalogSeeder::class);
        $pet = Pet::firstOrFail();
        $a = $this->matchingUser();
        $b = $this->matchingUser();
        $c = $this->matchingUser();
        foreach ([$a, $b, $c] as $u) {
            $u->update(['email_verified_at' => null]);
        }
        $apply = route('research.apply', $pet);
        $this->actingAs($a)->post($apply, ['eligible' => true])->assertUnprocessable();
        $profiles = collect(range(1, 5))->map(function ($rating) use ($a, $pet) {
            $profile = $this->completeMatchingProfile($a, ['bfi_responses' => $this->variedAnswers($rating)]);

            return ['rating' => $rating, 'score' => app(KnnRecommendationService::class)->calculateMatch($profile, $pet)->compatibilityScore];
        })->sortBy('score')->values();
        $this->assertLessThan($profiles->last()['score'], $profiles->first()['score']);
        foreach ([[$a, 3], [$b, $profiles->first()['rating']], [$c, $profiles->last()['rating']]] as [$u,$rating]) {
            $this->completeMatchingProfile($u, ['bfi_responses' => $this->variedAnswers($rating)]);
            $this->actingAs($u->fresh())->post($apply)->assertRedirect(route('research.index'));
            $this->post($apply)->assertRedirect(route('research.index'));
        }
        $primary = ResearchPlacement::where('user_id', $a->id)->sole();
        $earlier = ResearchPlacement::where('user_id', $b->id)->sole();
        $later = ResearchPlacement::where('user_id', $c->id)->sole();
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $primary->status);
        $this->assertSame(ApplicationStatus::Waitlisted, $earlier->status);
        $this->assertSame('Soft-Reserved', $pet->fresh()->availability_status->value);
        $this->get(route('research.index'))->assertSee('Current waitlist position: 1.')->assertDontSee('Continue Simulated Adoption');
        $this->post(route('research.continue', $later))->assertConflict();
        $this->post(route('research.continue', $primary))->assertNotFound();
        $this->post(route('research.receive', $later))->assertNotFound();
        $this->actingAs($a)->post(route('research.withdraw', $primary))->assertRedirect();
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $later->fresh()->status);
        $this->assertSame(ApplicationStatus::Waitlisted, $earlier->fresh()->status);
        $this->actingAs($c)->post(route('research.continue', $later))->assertRedirect();
        $this->post(route('research.continue', $later))->assertRedirect();
        $this->assertSame('Adopted', $pet->fresh()->availability_status->value);
        $this->assertSame(ApplicationStatus::Closed, $earlier->fresh()->status);
        $this->assertSame(1, ResearchPlacement::where('status', 'Approved')->count());
        $this->actingAs($this->matchingUser(Role::Administrator))->put(route('admin.animals.update', $pet), [
            'name' => $pet->name, 'species' => $pet->species->value, 'status' => 'Available', 'version' => $pet->version,
        ])->assertRedirect(route('admin.animals.index'));
        $this->assertSame('Adopted', $pet->fresh()->availability_status->value);
        $other = $this->matchingUser();
        $this->completeMatchingProfile($other);
        $this->actingAs($other)->post($apply)->assertUnprocessable();
        $this->post(route('research.receive', $later))->assertNotFound();
        $this->post(route('research.checkin', $later), ['milestone' => '3-day', 'appetite' => 'normal', 'adjustment' => 'settling'])->assertNotFound();
        $this->actingAs($c)->post(route('research.checkin', $later), ['milestone' => '3-day', 'appetite' => 'normal', 'adjustment' => 'settling'])->assertConflict();
        $this->post(route('research.receive', $later))->assertRedirect();
        $this->post(route('research.receive', $later))->assertRedirect();
        $this->post(route('research.checkin', $later), ['milestone' => '3-month', 'appetite' => 'normal', 'adjustment' => 'settling'])->assertConflict();
        foreach (['3-day', '3-week', '3-month'] as $milestone) {
            $body = ['milestone' => $milestone, 'appetite' => 'normal', 'adjustment' => 'settling'];
            $this->post(route('research.checkin', $later), $body)->assertRedirect();
            $this->post(route('research.checkin', $later), $body)->assertRedirect();
        }
        $this->get(route('research.index'))->assertSee('3-month simulated report filed.');
        $this->assertDatabaseCount('research_checkins', 3);
        $this->assertDatabaseCount('adoption_applications', 0);
        $this->assertDatabaseCount('handovers', 0);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->assertDatabaseCount('identity_verifications', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    private function variedAnswers(int $rating): array
    {
        return array_replace($this->bfiResponses(), ['EX1' => $rating, 'EX4' => $rating, 'EX5' => $rating]);
    }

    public function test_queue_exhaustion_and_disabled_mode(): void
    {
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);
        $user = $this->matchingUser();
        $this->completeMatchingProfile($user);
        $pet = Pet::firstOrFail();
        $this->actingAs($user)->post(route('research.apply', $pet))->assertRedirect();
        $this->post(route('research.withdraw', ResearchPlacement::sole()))->assertRedirect();
        $this->assertSame('Available', $pet->fresh()->availability_status->value);
        $this->post(route('application.submit'), ['pet_id' => $pet->id])->assertForbidden();
        config(['research.enabled' => false]);
        $this->get(route('research.index'))->assertNotFound();
        $this->get(route('application.apply', $pet))->assertOk()->assertViewIs('application.apply');
    }
}
