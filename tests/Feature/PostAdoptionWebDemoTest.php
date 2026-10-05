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
use App\Models\User;
use App\Services\PostAdoptionScheduleService;
use App\Services\PostAdoptionWebDemoService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PostAdoptionWebDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['post_adoption.web_demo.enabled' => true]);
        $this->freezeManilaDate('2026-08-21 09:00:00');
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_demo_is_disabled_by_default_and_admin_only_when_enabled(): void
    {
        $admin = $this->user(Role::Administrator);
        $volunteer = $this->user(Role::Volunteer);
        $adopter = $this->user(Role::Adopter);

        config(['post_adoption.web_demo.enabled' => false]);
        $this->actingAs($admin)->get(route('admin.post-adoption-demo.index'))->assertNotFound();

        config(['post_adoption.web_demo.enabled' => true]);
        $this->actingAs($volunteer)->get(route('admin.post-adoption-demo.index'))
            ->assertRedirect(route('access-denied'));
        $this->actingAs($adopter)->get(route('admin.post-adoption-demo.index'))
            ->assertRedirect(route('access-denied'));
        $this->actingAs($admin)->get(route('admin.post-adoption-demo.index'))->assertOk();
    }

    public function test_only_the_selected_completed_handover_is_fast_forwarded_and_reset_restores_real_date(): void
    {
        [$adopter, $application, $log] = $this->completedAdoption();
        [$otherAdopter, , $otherLog] = $this->completedAdoption();
        $admin = $this->user(Role::Administrator);

        $this->actingAs($admin)
            ->get(route('admin.post-adoption-demo.index', ['application_id' => $application->id]))
            ->assertOk()
            ->assertSee('Search adopter, pet, or application number')
            ->assertSee($adopter->full_name);

        $this->actingAs($adopter)->get(route('monitoring.create', $log))->assertUnprocessable();
        $this->actingAs($otherAdopter)->get(route('monitoring.create', $otherLog))->assertUnprocessable();

        $this->actingAs($admin)->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'due',
        ])->assertRedirect();

        $this->assertSame('2026-08-24', app(PostAdoptionWebDemoService::class)->stateFor($application->id)['date']);
        $this->assertSame(1, $adopter->inAppNotifications()->where('kind', 'demo_checkin_due')->count());
        $this->assertSame(0, $otherAdopter->inAppNotifications()->where('kind', 'demo_checkin_due')->count());
        $this->assertSame('2026-08-24', $log->fresh()->scheduled_date->toDateString());
        $this->actingAs($adopter)->get(route('monitoring.create', $log))->assertOk()->assertSee('Presentation demo active');
        $this->actingAs($otherAdopter)->get(route('monitoring.create', $otherLog))->assertUnprocessable();

        $this->actingAs($admin)->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'overdue',
        ])->assertRedirect();
        $this->actingAs($adopter)->get(route('monitoring.overdue-notice'))->assertOk()
            ->assertSee('overdue', false);
        $this->assertSame(1, $adopter->inAppNotifications()->where('kind', 'demo_checkin_overdue')->count());
        $this->actingAs($otherAdopter)->get(route('monitoring.overdue-notice'))->assertOk()
            ->assertDontSee($log->adoptionApplication->pet->name);

        $this->actingAs($admin)->delete(route('admin.post-adoption-demo.reset', $application))->assertRedirect();
        $this->assertNull(app(PostAdoptionWebDemoService::class)->stateFor($application->id));
        $this->assertSame(0, $adopter->inAppNotifications()->where('kind', 'demo_checkin_due')->whereNull('read_at')->count());
        $this->actingAs($adopter)->get(route('monitoring.create', $log))->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['action' => 'Post-Adoption Web Demo Reset', 'entity_id' => $application->id]);
    }

    public function test_two_real_demo_emails_on_distinct_presentation_days_create_only_a_demo_flag(): void
    {
        Mail::fake();
        [$adopter, $application, $log] = $this->completedAdoption();
        $admin = $this->user(Role::Administrator);
        $this->actingAs($admin)->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'due',
        ])->assertRedirect();

        $this->post(route('admin.post-adoption-demo.reminder', $log), ['confirm_email' => '1'])->assertRedirect();
        Mail::assertSent(CheckInReminderMail::class, fn (CheckInReminderMail $mail): bool => $mail->isPresentationDemo && $mail->hasTo($adopter->email)
                && str_contains($mail->render(), 'Presentation demo'));
        $this->assertSame(1, app(PostAdoptionWebDemoService::class)->reminderCount($log));
        $this->assertSame(0, $log->fresh()->reminders_sent);

        $this->post(route('admin.post-adoption-demo.reminder', $log), ['confirm_email' => '1'])
            ->assertSessionHasErrors('confirm_email');
        Mail::assertSent(CheckInReminderMail::class, 1);

        $this->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'date', 'target_date' => '2026-08-25',
        ])->assertRedirect();
        $this->post(route('admin.post-adoption-demo.reminder', $log), ['confirm_email' => '1'])->assertRedirect();

        Mail::assertSent(CheckInReminderMail::class, 2);
        $this->assertSame(2, app(PostAdoptionWebDemoService::class)->reminderCount($log));
        $this->assertTrue(app(PostAdoptionWebDemoService::class)->isDemoFlagged($log));
        $this->assertSame(1, $adopter->inAppNotifications()->where('kind', 'demo_checkin_flagged')->count());
        $this->assertSame(1, $admin->inAppNotifications()->where('kind', 'demo_checkin_flagged')->count());
        $this->assertFalse($log->fresh()->is_flagged);
        $this->assertSame(0, $log->fresh()->reminders_sent);
        $this->actingAs($admin)->get(route('admin.monitoring.flagged'))->assertOk()->assertSee('Presentation demo');
        $this->actingAs($adopter)->get(route('monitoring.flagged-notice'))->assertOk()->assertSee('Under staff review');

        $this->actingAs($admin)->post(route('admin.post-adoption-demo.resolve', $log), [
            'resolution_note' => 'Presentation follow-up completed.',
        ])->assertRedirect();
        $this->assertFalse(app(PostAdoptionWebDemoService::class)->isDemoFlagged($log));
        $this->assertNull($log->fresh()->resolved_at);
    }

    public function test_unverified_email_incomplete_handover_and_missing_confirmation_are_rejected(): void
    {
        Mail::fake();
        [$adopter, $application, $log] = $this->completedAdoption();
        $admin = $this->user(Role::Administrator);
        $this->actingAs($admin)->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'due',
        ])->assertRedirect();

        $this->post(route('admin.post-adoption-demo.reminder', $log), [])->assertSessionHasErrors('confirm_email');
        $adopter->forceFill(['email_verified_at' => null])->save();
        $this->post(route('admin.post-adoption-demo.reminder', $log), ['confirm_email' => '1'])
            ->assertSessionHasErrors('confirm_email');
        Mail::assertNothingSent();

        $application->handover->update(['adopter_outcome' => 'pending']);
        $this->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'due',
        ])->assertNotFound();
    }

    public function test_demo_date_does_not_make_the_real_scheduler_send_future_reminders_and_expires_automatically(): void
    {
        Mail::fake();
        [$adopter, $application, $log] = $this->completedAdoption();
        $admin = $this->user(Role::Administrator);

        $this->actingAs($admin)->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'all',
        ])->assertRedirect();
        $this->artisan('checkins:send-reminders')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertSame(0, $log->fresh()->reminders_sent);

        $this->freezeManilaDate('2026-08-21 16:01:00');
        $this->assertNull(app(PostAdoptionWebDemoService::class)->stateFor($application->id));
        $this->actingAs($adopter)->get(route('monitoring.create', $log))->assertUnprocessable();
    }

    public function test_a_failed_demo_email_does_not_increment_a_reminder_or_flag_the_log(): void
    {
        [$adopter, $application, $log] = $this->completedAdoption();
        $admin = $this->user(Role::Administrator);
        $this->actingAs($admin)->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'due',
        ])->assertRedirect();

        Mail::shouldReceive('to')->once()
            ->with(\Mockery::on(fn ($recipient): bool => $recipient instanceof User && $recipient->id === $adopter->id))
            ->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->post(route('admin.post-adoption-demo.reminder', $log), ['confirm_email' => '1'])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'error');

        $this->assertSame(0, app(PostAdoptionWebDemoService::class)->reminderCount($log));
        $this->assertFalse(app(PostAdoptionWebDemoService::class)->isDemoFlagged($log));
        $this->assertSame(0, $log->fresh()->reminders_sent);
    }

    public function test_demo_state_and_lock_work_with_the_database_cache_used_by_the_hosted_app(): void
    {
        config(['cache.default' => 'database']);
        Cache::purge('database');
        Mail::fake();
        [, $application, $log] = $this->completedAdoption();
        $admin = $this->user(Role::Administrator);

        $this->actingAs($admin)->post(route('admin.post-adoption-demo.activate'), [
            'application_id' => $application->id, 'mode' => 'due',
        ])->assertRedirect();
        $this->post(route('admin.post-adoption-demo.reminder', $log), ['confirm_email' => '1'])->assertRedirect();

        Cache::purge('database');
        $this->assertSame(1, app(PostAdoptionWebDemoService::class)->reminderCount($log));
        Mail::assertSent(CheckInReminderMail::class, 1);
    }

    private function completedAdoption(): array
    {
        $adopter = $this->user(Role::Adopter);
        $pet = Pet::create([
            'name' => 'Demo Pet '.$adopter->id,
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Adopted->value,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved->value,
            'adopted_at' => '2026-08-21 01:00:00',
        ]);
        Handover::create([
            'code' => 'HV-DEMO-'.$application->id,
            'application_id' => $application->id,
            'pet_id' => $pet->id,
            'user_id' => $adopter->id,
            'released_at' => now(),
            'adopter_outcome' => 'received',
            'received_at' => now(),
        ]);
        $log = app(PostAdoptionScheduleService::class)->ensureForApplication($application)
            ->firstWhere('milestone', Milestone::ThreeDays);

        return [$adopter, $application, $log];
    }

    private function user(Role $role): User
    {
        $id = uniqid('demo-', true);

        return User::create([
            'first_name' => 'Demo', 'last_name' => $role->value,
            'email' => str_replace('.', '', $id).'@example.test',
            'password' => 'password',
            'role' => $role->value,
            'email_verified_at' => now(),
        ]);
    }

    private function freezeManilaDate(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date, 'Asia/Manila'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($date, 'Asia/Manila'));
    }
}
