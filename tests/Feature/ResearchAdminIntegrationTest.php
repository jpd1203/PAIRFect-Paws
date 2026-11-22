<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Models\User;
use Database\Seeders\ResearchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\SubmitsResearchCheckIns;
use Tests\TestCase;

final class ResearchAdminIntegrationTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase, SubmitsResearchCheckIns;

    public function test_respondent_workflow_appears_in_original_staff_pages_without_real_world_actions(): void
    {
        config(['research.enabled' => true]);
        Mail::fake();
        Queue::fake();
        Notification::fake();
        $this->seed(ResearchCatalogSeeder::class);
        $staff = $this->matchingUser(Role::Administrator);
        $pet = Pet::firstOrFail();
        $a = $this->applyAsRespondent('Avery', $pet);
        $primary = ResearchPlacement::where('user_id', $a->id)->sole();
        $this->get(route('admin.applications.index'))->assertRedirect(route('access-denied'));
        $this->get(route('admin.handover.index'))->assertRedirect(route('access-denied'));
        $this->get(route('admin.monitoring.index'))->assertRedirect(route('access-denied'));

        $this->actingAs($staff)->get(route('admin.applications.index'))->assertOk()->assertViewIs('admin.application.index')
            ->assertSee('Avery Example')->assertSee('Primary Candidate')->assertSee('Submitted motivation from Avery')
            ->assertSee('987 Test Lane');
        $b = $this->applyAsRespondent('Blair', $pet);
        $waiting = ResearchPlacement::where('user_id', $b->id)->sole();
        $this->assertSame(ApplicationStatus::Waitlisted, $waiting->status);
        $this->assertSame('Soft-Reserved', $pet->fresh()->availability_status->value);

        $this->actingAs($staff)->get(route('admin.applications.index', ['status' => 'Waitlisted']))->assertOk()
            ->assertViewHas('applications', fn ($list) => $list->pluck('id')->all() === [$waiting->id] && $list->first()->queue_position === 1)
            ->assertSee('#1')->assertSee('Blair Example')->assertDontSee('Avery Example');
        $csv = $this->get(route('admin.applications.export', ['status' => 'Waitlisted']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Blair Example', $csv);
        $this->assertStringNotContainsString('Avery Example', $csv);
        $this->get(route('admin.compatibility.index'))->assertOk()->assertSee('Rank #1 for this pet');
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('pendingApplications', 2)->assertViewHas('totalPets', 70)
            ->assertDontSee('Fictional Observer');
        $this->get(route('admin.handover.index'))->assertOk()->assertSee('No handover records found');
        $this->get(route('admin.monitoring.index'))->assertOk()->assertViewHas('checkIns', fn ($logs) => $logs->isEmpty());

        $this->actingAs($a)->post(route('research.withdraw', $primary))->assertRedirect();
        $this->actingAs($staff)->get(route('admin.applications.index'))->assertOk()
            ->assertSee('Withdrawn')->assertSee('Primary Candidate')->assertSee('Blair Example')
            ->assertViewHas('applications', fn ($list) => $list->firstWhere('id', $waiting->id)->is_primary_candidate);

        $this->actingAs($b)->post(route('research.continue', $waiting))->assertRedirect();
        $this->post(route('research.continue', $waiting))->assertRedirect();
        $this->assertSame('Adopted', $pet->fresh()->availability_status->value);
        $this->assertDatabaseHas('audit_logs', ['entity_name' => 'ResearchPlacement', 'entity_id' => $waiting->id, 'user_id' => null]);
        $this->assertSame(1, AuditLog::where('entity_name', 'ResearchPlacement')->count());
        $this->actingAs($staff)->get(route('admin.applications.index'))->assertOk()->assertSee('Approved automatically by the system.');
        $this->get(route('admin.handover.index', ['tab' => 'needs_handover']))->assertOk()->assertSee('Blair Example');
        $this->actingAs($b)->post(route('research.handover.method', $waiting), ['method' => 'delivery', 'schedule_version' => 0])->assertRedirect();
        $this->post(route('research.handover.confirm', $waiting), ['schedule_version' => 1])->assertRedirect();
        $this->actingAs($staff);
        $this->get(route('admin.handover.index', ['tab' => 'awaiting', 'search' => 'Blair']))->assertOk()
            ->assertSee('Blair Example')->assertSee('Awaiting adopter')->assertDontSee('Picked up at shelter');
        $this->get(route('admin.handover.show', $waiting->id))->assertOk()->assertViewIs('admin.handover.show')
            ->assertSee('No physical transfer was recorded.')->assertSee('Waiting on adopter receipt confirmation')
            ->assertDontSee('Send Email Reminder')->assertDontSee('Official physical custody');
        $this->get(route('admin.monitoring.index'))->assertOk()->assertViewHas('checkIns', fn ($logs) => $logs->isEmpty());

        $this->actingAs($b)->post(route('research.receive', $waiting), ['outcome' => 'received', 'schedule_version' => 2])->assertRedirect();
        $this->actingAs($staff)->get(route('admin.handover.show', $waiting->id))->assertOk()->assertSee('Receipt confirmation recorded.');
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('activeMonitoring', 1)
            ->assertViewHas('adoptedThisMonth', 1)->assertViewHas('pendingApplications', 0)->assertViewHas('flaggedMonitoring', 0);
        $this->get(route('admin.monitoring.index'))->assertOk()->assertSee('Available now')
            ->assertViewHas('statusCounts', ['pending' => 1, 'upcoming' => 2]);

        $this->actingAs($b);
        $this->probeResearchVideo();
        Storage::fake('research-private');
        foreach (['3-day', '3-week', '3-month'] as $milestone) {
            $this->post(route('research.checkin', $waiting), [
                'milestone' => $milestone, 'pet_current_status' => 'Poor',
                'living_conditions' => 'Indoor Only', 'eating_habits' => 'Reduced Appetite',
                'behavioral_observations' => 'Still adjusting', 'concerns' => 'Filed '.$milestone.' report.',
            ] + $this->researchCapture($waiting, $milestone, $milestone))->assertRedirect();
        }
        $this->actingAs($staff)->get(route('admin.monitoring.index'))->assertOk()->assertViewIs('admin.monitoring.index')
            ->assertSee('Filed 3-day report.')->assertSee('Filed 3-week report.')->assertSee('Filed 3-month report.')
            ->assertSee('Not scheduled')->assertViewHas('statusCounts', ['completed' => 3]);
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('activeMonitoring', 0)
            ->assertViewHas('pipeline', ['scheduled' => 0, 'underreview' => 0, 'approved' => 1, 'rejected' => 0]);

        foreach (['adoption_applications', 'handovers', 'post_adoption_logs', 'identity_verifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('research_checkins', 3);
        $this->assertNull($waiting->fresh()->document_verification_status);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_staff_actions_cannot_resolve_colliding_normal_ids_in_research_mode(): void
    {
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);
        $pet = Pet::firstOrFail();
        $adopter = $this->applyAsRespondent('Casey', $pet);
        $placement = ResearchPlacement::sole();
        $normal = new AdoptionApplication(['user_id' => $adopter->id, 'pet_id' => $pet->id, 'status' => ApplicationStatus::Pending]);
        $normal->id = $placement->id;
        $normal->save();

        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->post(route('admin.applications.decide', $placement->id), ['decision' => 'Approved'])->assertNotFound();
        $this->post(route('admin.applications.schedule'), ['application_id' => $placement->id])->assertNotFound();
        $this->get(route('admin.applications.document-verification', $placement->id))->assertNotFound();
        $this->post(route('admin.handover.release', $placement->id))->assertNotFound();
        $this->post(route('admin.monitoring.reminder', $placement->id))->assertNotFound();
        $this->assertSame(ApplicationStatus::Pending, $normal->fresh()->status);

        config(['research.enabled' => false]);
        $this->get(route('admin.applications.index'))->assertOk()->assertViewHas('workflowReadOnly', false)
            ->assertViewHas('applications', fn ($rows) => $rows->first()::class === AdoptionApplication::class);
    }

    private function applyAsRespondent(string $firstName, Pet $pet): User
    {
        $email = strtolower($firstName).'@example.test';
        $address = [
            'region_code' => '1300000000', 'province_code' => '__direct__',
            'city_municipality_code' => '1380600000', 'barangay_code' => '1380606197',
            'street_address' => '987 Test Lane', 'zip_code' => '1016',
        ];
        $this->post(route('register.store'), [
            'first_name' => $firstName, 'last_name' => 'Example', 'email' => $email,
            'password' => 'Testing123!', 'password_confirmation' => 'Testing123!',
            'terms_accepted' => 1, 'privacy_consent' => 1, ...$address,
        ])->assertRedirect(route('animal.index'));
        $user = User::where('email', $email)->sole();
        $this->assertAuthenticatedAs($user);
        $this->post(route('recommendation.start'), [
            'housing_type' => 'Single Family Home (Fenced Yard)', 'monthly_income_range' => '₱30,000 - ₱50,000',
            'has_existing_pets' => false, 'has_children' => false, 'bfi_responses' => $this->bfiResponses(),
        ])->assertRedirect(route('recommendation.results'));
        $this->post(route('research.apply', $pet), [
            'first_name' => $firstName, 'last_name' => 'Example', 'email' => $email, 'phone_number' => '5550001234',
            'motivation_statement' => 'Submitted motivation from '.$firstName, 'physical_activity_level' => 'Moderate',
            'time_availability' => 'Daily', 'prior_pet_experience' => 'Some', 'household_composition' => 'Adults',
            'agreed_to_terms' => 1, ...$address,
        ])->assertRedirect(route('research.index'));

        return $user;
    }
}
