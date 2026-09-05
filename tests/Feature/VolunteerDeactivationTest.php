<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VolunteerDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_update_and_deactivate_a_volunteer_and_revoke_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $administrator = $this->user(Role::Administrator, 'manager');
        $volunteer = $this->user(Role::Volunteer, 'managed-volunteer', [
            'first_name' => 'Old',
            'last_name' => 'Name',
            'remember_token' => 'original-remember-token',
        ]);
        $otherVolunteer = $this->user(Role::Volunteer, 'other-volunteer');

        $this->databaseSession('managed-session', $volunteer);
        $this->databaseSession('other-session', $otherVolunteer);

        $this->actingAs($administrator)
            ->patch(route('admin.volunteers.update', $volunteer), [
                'first_name' => 'Updated',
                'last_name' => 'Volunteer',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.volunteers.index'))
            ->assertSessionHas('success');

        $volunteer->refresh();

        $this->assertSame('Updated', $volunteer->first_name);
        $this->assertSame('Volunteer', $volunteer->last_name);
        $this->assertSame(Role::Volunteer, $volunteer->role);
        $this->assertFalse($volunteer->is_active);
        $this->assertNotSame('original-remember-token', $volunteer->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'managed-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'Volunteer Account Deactivated',
            'entity_name' => 'User',
            'entity_id' => $volunteer->id,
        ]);

        $this->patch(route('admin.volunteers.update', $volunteer), [
            'full_name' => 'Reactivated Helper',
            'is_active' => '1',
        ])->assertRedirect(route('admin.volunteers.index'));

        $volunteer->refresh();
        $this->assertTrue($volunteer->is_active);
        $this->assertSame('Reactivated', $volunteer->first_name);
        $this->assertSame('Helper', $volunteer->last_name);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'Volunteer Account Reactivated',
            'entity_name' => 'User',
            'entity_id' => $volunteer->id,
        ]);
    }

    public function test_the_volunteer_endpoint_cannot_promote_a_volunteer_or_modify_an_administrator(): void
    {
        $administrator = $this->user(Role::Administrator, 'protected-admin');
        $volunteer = $this->user(Role::Volunteer, 'promotion-target');

        $this->actingAs($administrator)
            ->patch(route('admin.volunteers.update', $volunteer), [
                'role' => Role::Administrator->value,
                'is_active' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::Volunteer, $volunteer->refresh()->role);
        $this->assertTrue($volunteer->is_active);

        $this->patch(route('admin.volunteers.update', $administrator), [
            'is_active' => '0',
        ])->assertNotFound();

        $this->assertSame(Role::Administrator, $administrator->refresh()->role);
        $this->assertTrue($administrator->is_active);
    }

    public function test_an_inactive_volunteer_cannot_log_in(): void
    {
        $volunteer = $this->user(Role::Volunteer, 'inactive-login', [
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $volunteer->email,
            'password' => 'password123',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_volunteer_cannot_update_another_volunteer_account(): void
    {
        $actor = $this->user(Role::Volunteer, 'non-admin-actor');
        $target = $this->user(Role::Volunteer, 'protected-target');

        $this->actingAs($actor)
            ->patch(route('admin.volunteers.update', $target), [
                'is_active' => '0',
            ])
            ->assertRedirect(route('access-denied'));

        $this->assertTrue($target->refresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', [
            'entity_name' => 'User',
            'entity_id' => $target->id,
        ]);
    }

    public function test_an_inactive_volunteer_with_an_existing_session_is_logged_out_on_the_next_staff_request(): void
    {
        config(['session.driver' => 'array']);

        $volunteer = $this->user(Role::Volunteer, 'stale-session');

        $this->actingAs($volunteer)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $volunteer->update(['is_active' => false]);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_existing_inactive_administrator_behavior_is_preserved(): void
    {
        $administrator = $this->user(Role::Administrator, 'inactive-admin', [
            'is_active' => false,
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('access-denied'));

        $this->assertAuthenticatedAs($administrator);
    }

    /** @param array<string, mixed> $attributes */
    private function user(Role $role, string $key, array $attributes = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Feature',
            'last_name' => 'Tester',
            'email' => $key.'@example.test',
            'password' => 'password123',
            'role' => $role->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function databaseSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
        ]);
    }
}
