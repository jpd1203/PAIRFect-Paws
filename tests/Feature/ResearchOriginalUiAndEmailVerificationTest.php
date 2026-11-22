<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\ReservationQueueService;
use Database\Seeders\ResearchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\SubmitsResearchCheckIns;
use Tests\TestCase;

final class ResearchOriginalUiAndEmailVerificationTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase, SubmitsResearchCheckIns;

    public function test_new_research_user_must_verify_email_before_accessing_applications_or_research_endpoints(): void
    {
        Notification::fake();
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);
        $pet = Pet::where('availability_status', 'Available')->firstOrFail();

        $response = $this->post(route('register.store'), [
            'first_name' => 'Maya',
            'last_name' => 'Santos',
            'email' => 'maya@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms_accepted' => '1',
            'privacy_consent' => '1',
            ...$this->addressPayload(),
        ]);

        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'maya@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());

        Notification::assertSentTo($user, QueuedVerifyEmail::class);

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertViewIs('auth.verify-email')
            ->assertSee('Verify your email address')
            ->assertSee('maya@example.test');

        // Unverified research adopters can browse and view recommendations,
        // but see the verification banner and cannot open/submit applications.
        $this->get(route('animal.index'))
            ->assertOk()
            ->assertSee('Verify your email to continue.');

        $this->get(route('pets.modal', $pet))
            ->assertOk()
            ->assertSee('Verify your email address before you can apply to adopt a pet.')
            ->assertDontSee('href="'.route('application.apply', $pet).'"', false);

        $this->get(route('application.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('application.apply', $pet))->assertRedirect(route('verification.notice'));
        $this->get(route('research.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('research.preview', $pet))->assertRedirect(route('verification.notice'));
        $this->post(route('research.apply', $pet), $this->applicationPayload())->assertRedirect(route('verification.notice'));
        $this->get(route('adopter.handover.my'))->assertRedirect(route('verification.notice'));
        $this->get(route('monitoring.my-checkins'))->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('research_placements', 0);

        // Resend verification email still works normally.
        $this->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentToTimes($user, QueuedVerifyEmail::class, 2);

        // Clicking the signed verification link marks the user verified and unlocks adoption flows.
        $verifyUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($verifyUrl)->assertRedirect(route('recommendation.onboarding'));
        $this->assertTrue($user->refresh()->hasVerifiedEmail());

        $this->completeMatchingProfile($user);
        $this->actingAs($user->fresh());
        $this->get(route('pets.modal', $pet))
            ->assertOk()
            ->assertSee('href="'.route('application.apply', $pet).'"', false);

        $this->get(route('application.apply', $pet))
            ->assertRedirect(route('research.preview', $pet));
        $this->get(route('research.preview', $pet))
            ->assertOk()
            ->assertViewIs('application.apply');

        $this->post(route('research.apply', $pet), $this->applicationPayload())
            ->assertRedirect(route('application.index'));

        $this->assertDatabaseCount('research_placements', 1);
        $this->assertDatabaseCount('adoption_applications', 0);
    }

    public function test_grandfathering_migration_preserves_existing_research_adopters_only(): void
    {
        $migration = require database_path('migrations/2026_11_25_000001_grandfather_existing_research_adopter_emails.php');

        $productionAdopter = User::create([
            'first_name' => 'Prod',
            'last_name' => 'Adopter',
            'email' => 'prod-adopter@example.test',
            'password' => 'Password123!',
            'role' => Role::Adopter->value,
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        // In normal production mode, migration must be a no-op.
        config(['research.enabled' => false]);
        $migration->up();
        $this->assertNull($productionAdopter->fresh()->email_verified_at);

        // In Research Mode, existing active adopters are grandfathered,
        // while inactive accounts and staff accounts remain untouched.
        $inactiveObserver = User::create([
            'first_name' => 'Inactive',
            'last_name' => 'Observer',
            'email' => 'inactive-observer@example.test',
            'password' => 'Password123!',
            'role' => Role::Volunteer->value,
            'is_active' => false,
            'email_verified_at' => null,
        ]);
        $unverifiedStaff = User::create([
            'first_name' => 'Pending',
            'last_name' => 'Volunteer',
            'email' => 'pending-volunteer@example.test',
            'password' => 'Password123!',
            'role' => Role::Volunteer->value,
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        config(['research.enabled' => true]);
        $migration->up();

        $this->assertNotNull($productionAdopter->fresh()->email_verified_at);
        $this->assertNull($inactiveObserver->fresh()->email_verified_at);
        $this->assertNull($unverifiedStaff->fresh()->email_verified_at);

        // Existing grandfathered participant can access their applications without lockout.
        $this->actingAs($productionAdopter->fresh())
            ->get(route('application.index'))
            ->assertOk()
            ->assertViewIs('application.index');
    }

    public function test_three_original_pages_are_restored_in_research_mode_including_multi_handover_and_report_modal(): void
    {
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);

        $pets = Pet::where('availability_status', 'Available')->orderBy('id')->take(2)->get();
        $adopter = $this->matchingUser();
        $other = $this->matchingUser();
        $this->completeMatchingProfile($adopter);
        $this->completeMatchingProfile($other);

        $queue = app(ReservationQueueService::class);
        $this->actingAs($adopter);

        // Empty states use original pages and behavior.
        $this->get(route('application.index'))
            ->assertOk()
            ->assertViewIs('application.index')
            ->assertSee('No applications yet');

        $this->get(route('adopter.handover.my'))
            ->assertRedirect(route('application.index'))
            ->assertSessionHas('toast.type', 'error');

        $this->get(route('monitoring.my-checkins'))
            ->assertOk()
            ->assertViewIs('monitoring.my-checkins')
            ->assertSee('No check-ins scheduled yet');

        // Legacy research.index bookmarks redirect to the restored original pages.
        $this->get(route('research.index'))->assertRedirect(route('application.index'));
        $this->get(route('research.index', ['section' => 'handover']))->assertRedirect(route('adopter.handover.my'));
        $this->get(route('research.index', ['section' => 'check-ins']))->assertRedirect(route('monitoring.my-checkins'));
        $this->get(route('monitoring.submit-report'))->assertRedirect(route('monitoring.my-checkins'));

        // Submit and inspect original My Applications card with progress bar (without fabricated interview steps).
        $this->post(route('research.apply', $pets[0]), $this->applicationPayload())
            ->assertRedirect(route('application.index'));
        $first = ResearchPlacement::where('pet_id', $pets[0]->id)->sole();

        $this->get(route('application.index'))
            ->assertOk()
            ->assertViewIs('application.index')
            ->assertSee('Application #'.str_pad((string) $first->id, 4, '0', STR_PAD_LEFT))
            ->assertSee($pets[0]->name)
            ->assertSee('Application Progress')
            ->assertSee('Pending / Queue')
            ->assertSee('Continue Application')
            ->assertSee('Withdraw Application')
            ->assertDontSee('Interview Scheduled')
            ->assertDontSee('Under Review');

        // Approve two pets to verify multi-pet Handover Status uses handover.status with switcher (never research.handover).
        $queue->approveResearch($adopter, $first);
        $this->post(route('research.apply', $pets[1]), $this->applicationPayload())
            ->assertRedirect(route('application.index'));
        $second = ResearchPlacement::where('pet_id', $pets[1]->id)->sole();
        $queue->approveResearch($adopter, $second);

        $this->get(route('application.index'))
            ->assertOk()
            ->assertViewIs('application.index')
            ->assertSee('Open Handover')
            ->assertSee(route('research.handover', $first))
            ->assertSee(route('research.handover', $second));

        // Handover Status redirects straight to handover.status with pet switcher tabs.
        $handoverRedirect = $this->get(route('adopter.handover.my'))->assertRedirect();
        $this->get($handoverRedirect->headers->get('Location'))
            ->assertOk()
            ->assertViewIs('handover.status')
            ->assertSee('Handover Method Preference')
            ->assertSee($pets[0]->name)
            ->assertSee($pets[1]->name);

        // Complete handover for the first pet (pickup -> confirm -> receive).
        $this->post(route('research.handover.method', $first), ['method' => 'pickup', 'schedule_version' => 0])->assertRedirect();
        $this->post(route('research.handover.confirm', $first), ['schedule_version' => 1])->assertRedirect();
        $this->post(route('research.receive', $first), ['outcome' => 'received', 'schedule_version' => 2])->assertRedirect();

        // My Check-ins uses original monitoring.my-checkins with sequential milestones and no fabricated due dates.
        $this->get(route('monitoring.my-checkins'))
            ->assertOk()
            ->assertViewIs('monitoring.my-checkins')
            ->assertSee('Check-in Schedule')
            ->assertSee($pets[0]->name)
            ->assertSee('3-Day')
            ->assertSee('Ready now')
            ->assertSee('Submit Now')
            ->assertSee('Unlocks after previous check-in')
            ->assertDontSee('Due:');

        // Submit the 3-day video check-in and verify Submitted Reports table + View Report modal.
        $this->probeResearchVideo();
        Storage::fake('research-private');
        $capture = $this->researchCapture($first, '3-day', 'check-in-1');
        $this->post(route('research.checkin', $first), [
            'milestone' => '3-day',
            'pet_current_status' => 'Excellent',
            'living_conditions' => 'Indoor Only',
            'eating_habits' => 'Normal',
            'behavioral_observations' => 'Well-adjusted',
            'vet_visit_details' => 'Routine checkup done',
            'concerns' => 'Settling in happily.',
        ] + $capture)->assertRedirect(route('monitoring.my-checkins'));

        $reportId = (int) DB::table('research_checkins')->where('placement_id', $first->id)->value('id');

        $this->get(route('monitoring.my-checkins'))
            ->assertOk()
            ->assertViewIs('monitoring.my-checkins')
            ->assertSee('Submitted Reports')
            ->assertSee('data-modal-url="'.route('research.checkin.modal', $reportId).'"', false);

        // Owner can open the original report view modal; another user gets 404.
        $this->get(route('research.checkin.modal', $reportId))
            ->assertOk()
            ->assertViewIs('monitoring._report-view-modal-content')
            ->assertSee('Post-Adoption Report')
            ->assertSee('Excellent')
            ->assertSee('Routine checkup done')
            ->assertSee('Settling in happily.');

        $this->actingAs($other)->get(route('research.checkin.modal', $reportId))->assertNotFound();

        // Production application, handover, and monitoring routes stay blocked in Research Mode.
        $this->actingAs($adopter)->post(route('application.submit'), ['pet_id' => $pets[0]->id])->assertForbidden();
        $this->get('/adopter/'.$first->id)->assertNotFound();
        $this->get('/monitoring/reports/'.$reportId.'/modal')->assertNotFound();

        // Verify zero writes leaked into production workflow tables.
        $this->assertDatabaseCount('adoption_applications', 0);
        $this->assertDatabaseCount('handovers', 0);
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    /** @return array<string, string> */
    private function addressPayload(): array
    {
        return [
            'region_code' => '1300000000',
            'province_code' => '__direct__',
            'city_municipality_code' => '1380600000',
            'barangay_code' => '1380606197',
            'street_address' => '123 Rizal Street',
            'zip_code' => '1016',
        ];
    }

    /** @return array<string, mixed> */
    private function applicationPayload(): array
    {
        return [
            'first_name' => 'Maya',
            'last_name' => 'Santos',
            'email' => 'maya@example.test',
            'phone_number' => '5550001234',
            'motivation_statement' => 'Ready to provide a loving home.',
            'physical_activity_level' => 'Moderate',
            'time_availability' => 'Daily',
            'prior_pet_experience' => 'Some',
            'household_composition' => 'Adults',
            'agreed_to_terms' => 1,
            ...$this->addressPayload(),
        ];
    }
}
