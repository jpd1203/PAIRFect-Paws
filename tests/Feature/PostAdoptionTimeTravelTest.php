<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\User;
use App\Services\PostAdoptionClock;
use App\Services\PostAdoptionScheduleService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostAdoptionTimeTravelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['post_adoption.time_travel.enabled' => true]);
        $this->freezeManilaTime('2026-08-21 09:00:00');
        app(PostAdoptionClock::class)->reset();
    }

    protected function tearDown(): void
    {
        app(PostAdoptionClock::class)->reset();
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_the_temporary_route_fails_closed_when_the_feature_is_disabled(): void
    {
        config(['post_adoption.time_travel.enabled' => false]);

        $this->actingAs($this->administrator())
            ->get('/timetravel')
            ->assertNotFound();
    }

    public function test_only_an_active_administrator_can_manage_the_testing_clock(): void
    {
        $this->get('/timetravel')->assertRedirect(route('login'));

        $this->actingAs($this->adopter())
            ->get('/timetravel')
            ->assertRedirect(route('access-denied'));

        $this->actingAs($this->administrator())
            ->get('/timetravel')
            ->assertOk()
            ->assertSee('Post-Adoption Time Travel')
            ->assertSee('Choose a test date');
    }

    public function test_an_admin_can_unlock_a_future_check_in_and_reset_to_real_time(): void
    {
        [$adopter, $application] = $this->approvedApplication();
        $log = app(PostAdoptionScheduleService::class)
            ->ensureForApplication($application)
            ->firstWhere('milestone', Milestone::ThreeDays);

        $this->actingAs($adopter)
            ->get(route('monitoring.create', $log))
            ->assertUnprocessable();

        $administrator = $this->administrator();
        $this->actingAs($administrator)
            ->post(route('time-travel.store'), [
                'mode' => 'date',
                'target_date' => '2026-08-24',
            ])
            ->assertRedirect(route('time-travel.index'));

        $this->assertSame('2026-08-24', app(PostAdoptionClock::class)->today()->toDateString());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'Post-Adoption Test Clock Enabled',
        ]);

        $this->actingAs($adopter)
            ->get(route('monitoring.create', $log))
            ->assertOk()
            ->assertSee('Testing clock active');

        $this->actingAs($administrator)
            ->delete(route('time-travel.destroy'))
            ->assertRedirect(route('time-travel.index'));

        $this->assertFalse(app(PostAdoptionClock::class)->isActive());
        $this->actingAs($adopter)
            ->get(route('monitoring.create', $log))
            ->assertUnprocessable();
    }

    public function test_quick_actions_jump_to_the_next_date_and_then_unlock_all_milestones(): void
    {
        [, $application] = $this->approvedApplication();
        app(PostAdoptionScheduleService::class)->ensureForApplication($application);
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('time-travel.store'), ['mode' => 'next'])
            ->assertRedirect(route('time-travel.index'));
        $this->assertSame('2026-08-24', app(PostAdoptionClock::class)->today()->toDateString());

        $this->actingAs($administrator)
            ->post(route('time-travel.store'), ['mode' => 'all'])
            ->assertRedirect(route('time-travel.index'));
        $this->assertSame('2026-11-21', app(PostAdoptionClock::class)->today()->toDateString());
    }

    /** @return array{User, AdoptionApplication} */
    private function approvedApplication(): array
    {
        $adopter = $this->adopter();
        $pet = Pet::create([
            'name' => 'Time Travel Test Pet',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Adopted->value,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved->value,
            'adopted_at' => '2026-08-21 01:00:00',
            'queue_closed_at' => '2026-08-21 01:00:00',
        ]);
        Handover::create([
            'code' => 'HV-TEST-'.$application->id,
            'application_id' => $application->id,
            'pet_id' => $pet->id,
            'user_id' => $adopter->id,
            'released_at' => now(),
            'adopter_outcome' => 'received',
            'received_at' => now(),
        ]);

        return [$adopter, $application];
    }

    private function administrator(): User
    {
        return $this->user(Role::Administrator, uniqid('admin-', true));
    }

    private function adopter(): User
    {
        return $this->user(Role::Adopter, uniqid('adopter-', true));
    }

    private function user(Role $role, string $identity): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => $role->value,
            'email' => str_replace('.', '', $identity).'@example.test',
            'password' => 'password',
            'role' => $role->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function freezeManilaTime(string $dateTime): void
    {
        $now = CarbonImmutable::parse($dateTime, PostAdoptionClock::TIMEZONE);
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }
}
