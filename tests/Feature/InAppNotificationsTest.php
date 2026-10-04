<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\InAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class InAppNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_bell_remains_available_without_duplicate_header_bells(): void
    {
        $adopter = $this->user('adopter@example.test', Role::Adopter);
        $feed = $this->actingAs($adopter)->get(route('notifications.index'))->assertOk();

        $this->assertSame(1, substr_count($feed->getContent(), 'class="notification-bell'));
        $feed->assertDontSee('<article onclick=', false);

        $admin = $this->user('admin@example.test', Role::Administrator);
        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(1, substr_count($dashboard->getContent(), 'class="notification-bell'));
    }

    public function test_shared_feed_is_private_paginated_and_marks_only_the_owner_records_read(): void
    {
        $owner = $this->user('owner@example.test', Role::Adopter);
        $other = $this->user('other@example.test', Role::Adopter);
        $notices = app(InAppNotificationService::class);

        for ($i = 1; $i <= 18; $i++) {
            $notices->user($owner, 'application_update', "Update {$i}", 'Your application changed.',
                route('application.index'), "owner-update:{$i}", 'AdoptionApplication', $i);
        }
        $foreign = $notices->user($other, 'application_update', 'Private update', 'Only the other user sees this.',
            route('application.index'), 'other-update:1');

        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()
            ->assertSee('Update 18')->assertDontSee('Private update')
            ->assertSee('18 unread');
        $this->get(route('notifications.index', ['page' => 2]))->assertOk()
            ->assertSee('Update 1')->assertSee('Showing');

        $this->post(route('notifications.read', $foreign))->assertForbidden();
        $this->get(route('notifications.open', $foreign))->assertForbidden();
        $this->assertNull($foreign->fresh()->read_at);

        $own = $owner->inAppNotifications()->firstOrFail();
        $this->post(route('notifications.read', $own))->assertRedirect();
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertTrue($own->fresh()->read);

        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $owner->inAppNotifications()->whereNull('read_at')->count());
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_duplicate_events_are_suppressed_and_links_do_not_bypass_underlying_authorization(): void
    {
        $adopter = $this->user('adopter@example.test', Role::Adopter);
        $notices = app(InAppNotificationService::class);
        $notice = $notices->user($adopter, 'application_update', 'Application update', 'Status changed.',
            route('admin.audit-logs.index'), 'same-event:1');
        $this->assertSame($notice->id, $notices->user($adopter, 'application_update', 'Application update', 'Status changed.',
            route('admin.audit-logs.index'), 'same-event:1')->id);
        $this->assertSame(1, $adopter->inAppNotifications()->count());

        $redirect = $this->actingAs($adopter)->get(route('notifications.open', $notice))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
        $this->get($redirect->headers->get('Location'))->assertRedirect(route('access-denied'));

        $unsafe = $notices->user($adopter, 'test', 'Unsafe link', 'No off-site redirects.', 'https://example.com/steal', 'unsafe:1');
        $this->get(route('notifications.open', $unsafe))->assertRedirect(route('notifications.index'));
    }

    public function test_existing_email_event_points_create_role_specific_in_app_notices(): void
    {
        Mail::fake();
        $adopter = $this->user('adopter@example.test', Role::Adopter);
        $volunteer = $this->user('volunteer@example.test', Role::Volunteer);
        $admin = $this->user('admin@example.test', Role::Administrator);
        $emails = app(EmailNotificationService::class);

        $emails->user($adopter, 'Application received', 'Application received', ['Your application was submitted.'],
            'View', route('application.index'), 'application_received', 42);
        $emails->staff('New application', 'New application submitted', ['Application #42 needs review.'],
            'Review', route('admin.applications.index'), true, 'application_received_staff', 42);

        $this->assertSame(1, $adopter->inAppNotifications()->where('kind', 'application_received')->count());
        $this->assertSame(1, $admin->inAppNotifications()->where('kind', 'application_received_staff')->count());
        $this->assertSame(0, $volunteer->inAppNotifications()->count());

        $emails->user($volunteer, 'Interview assigned', 'Interview assigned to you', ['Application #42 interview assigned.'],
            'Review', route('admin.applications.index'), 'interview_assignment_staff', 42);
        $this->assertSame(1, $volunteer->inAppNotifications()->where('kind', 'interview_assignment_staff')->count());
    }

    private function user(string $email, Role $role): User
    {
        return User::create([
            'first_name' => 'Notification', 'last_name' => 'Tester', 'email' => $email,
            'password' => 'password123', 'role' => $role->value,
            'email_verified_at' => now(), 'is_active' => true,
        ]);
    }
}
