<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Mail\CheckInReminderMail;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Services\PostAdoptionScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PostAdoptionHandoverGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_application_waiting_for_handover_creates_no_checkins_until_receipt_is_confirmed(): void
    {
        [, $application, $handover] = $this->placement();
        Mail::fake();

        $this->assertCount(0, app(PostAdoptionScheduleService::class)->ensureForApplication($application));
        $this->artisan('checkins:send-reminders')->assertSuccessful();
        $this->assertDatabaseCount('post_adoption_logs', 0);
        Mail::assertNotSent(CheckInReminderMail::class);

        $handover->update(['released_at' => now(), 'adopter_outcome' => 'received', 'received_at' => now()]);
        $this->assertCount(3, app(PostAdoptionScheduleService::class)->ensureForApplication($application->fresh()));
        $this->assertDatabaseCount('post_adoption_logs', 3);
    }

    public function test_premature_legacy_log_is_hidden_and_cannot_trigger_monitoring_actions_before_handover(): void
    {
        [$adopter, $application, $handover] = $this->placement();
        $staff = $this->user(Role::Administrator, 'monitoring-staff');
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays->value,
            'scheduled_date' => now()->subDay()->toDateString(),
            'is_flagged' => true,
            'reminders_sent' => 0,
        ]);
        Mail::fake();

        $this->actingAs($staff)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertViewHas('checkIns', fn ($logs): bool => $logs->isEmpty());
        $this->get(route('admin.monitoring.flagged'))
            ->assertOk()
            ->assertViewHas('flagged', fn ($logs): bool => $logs->isEmpty());
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('activeMonitoring', 0)
            ->assertViewHas('flaggedMonitoring', 0);
        $this->get(route('admin.adopter-profiles.history', $adopter))
            ->assertOk()
            ->assertSee('Post-adoption monitoring has not started.')
            ->assertDontSee('Post-adoption check-in timeline');
        $this->post(route('admin.monitoring.reminder', $log))->assertNotFound();
        $this->post(route('admin.monitoring.flag', $log), ['reason' => 'A reason for review.'])->assertNotFound();

        $this->artisan('checkins:send-reminders')->assertSuccessful();
        Mail::assertNotSent(CheckInReminderMail::class);
        $this->assertSame(0, $log->refresh()->reminders_sent);

        $this->actingAs($adopter)
            ->get(route('monitoring.my-checkins'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->isEmpty());
        $this->get(route('monitoring.create', $log))->assertNotFound();

        $handover->update(['released_at' => now(), 'adopter_outcome' => 'received', 'received_at' => now()]);
        $this->actingAs($staff)
            ->get(route('admin.monitoring.index'))
            ->assertViewHas('checkIns', fn ($logs): bool => $logs->pluck('id')->contains($log->id));
    }

    /** @return array{User, AdoptionApplication, Handover} */
    private function placement(): array
    {
        $adopter = $this->user(Role::Adopter, 'monitoring-adopter');
        $pet = Pet::create([
            'name' => 'Handover Gate Pet',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Adopted->value,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved->value,
            'adopted_at' => now(),
        ]);
        $handover = Handover::create([
            'code' => 'HV-TEST-'.$application->id,
            'application_id' => $application->id,
            'pet_id' => $pet->id,
            'user_id' => $adopter->id,
        ]);

        return [$adopter, $application, $handover];
    }

    private function user(Role $role, string $key): User
    {
        return User::create([
            'first_name' => 'Handover',
            'last_name' => 'Tester',
            'email' => $key.'@example.test',
            'password' => 'password123',
            'role' => $role->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
