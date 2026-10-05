<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_settings_posts_the_complete_change_password_contract(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Change Password')
            ->assertSee('action="'.route('account.password.update').'"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('autocomplete="new-password"', false);
    }

    public function test_verified_user_can_change_the_password_and_other_database_sessions_are_revoked(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create([
            'password' => 'CurrentPass123',
            'remember_token' => 'old-remember-token',
        ]);
        $otherUser = User::factory()->create();
        $this->insertSession('another-session-for-user', $user);
        $this->insertSession('unrelated-user-session', $otherUser);

        $this->actingAs($user)
            ->from(route('account.settings'))
            ->patch(route('account.password.update'), [
                'current_password' => 'CurrentPass123',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'NewSecurePass456!',
            ])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('success')
            ->assertSessionHas('toast.type', 'success');

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass456!', $user->password));
        $this->assertFalse(Hash::check('CurrentPass123', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session-for-user']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-user-session']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'Account Password Changed',
            'entity_name' => 'User',
            'entity_id' => $user->id,
        ]);
        $auditNotes = DB::table('audit_logs')
            ->where('action', 'Account Password Changed')
            ->value('notes');
        $this->assertIsString($auditNotes);
        $this->assertStringNotContainsString('CurrentPass123', $auditNotes);
        $this->assertStringNotContainsString('NewSecurePass456!', $auditNotes);

        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'CurrentPass123'])
            ->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => $user->email, 'password' => 'NewSecurePass456!'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_incorrect_current_password_confirmation_and_password_reuse_do_not_change_it(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'CurrentPass123']);
        $this->insertSession('legitimate-other-session', $user);

        $this->actingAs($user)
            ->from(route('account.settings'))
            ->patch(route('account.password.update'), [
                'current_password' => 'IncorrectPass123',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'NewSecurePass456!',
            ])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->assertTrue(Hash::check('CurrentPass123', $user->refresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => 'legitimate-other-session']);

        $this->from(route('account.settings'))
            ->patch(route('account.password.update'), [
                'current_password' => 'CurrentPass123',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'DoesNotMatch456',
            ])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue(Hash::check('CurrentPass123', $user->refresh()->password));

        $this->from(route('account.settings'))
            ->patch(route('account.password.update'), [
                'current_password' => 'CurrentPass123',
                'password' => 'Short123',
                'password_confirmation' => 'Short123',
            ])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue(Hash::check('CurrentPass123', $user->refresh()->password));

        $this->from(route('account.settings'))
            ->patch(route('account.password.update'), [
                'current_password' => 'CurrentPass123',
                'password' => 'CurrentPass123',
                'password_confirmation' => 'CurrentPass123',
            ])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue(Hash::check('CurrentPass123', $user->refresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => 'legitimate-other-session']);
    }

    public function test_change_password_requires_authentication_and_a_verified_email(): void
    {
        $payload = [
            'current_password' => 'CurrentPass123',
            'password' => 'NewSecurePass456!',
            'password_confirmation' => 'NewSecurePass456!',
        ];

        $this->patch(route('account.password.update'), $payload)
            ->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create([
            'password' => 'CurrentPass123',
        ]);

        $this->actingAs($unverified)
            ->patch(route('account.password.update'), $payload)
            ->assertRedirect(route('verification.notice'));

        $this->assertTrue(Hash::check('CurrentPass123', $unverified->refresh()->password));
    }

    private function insertSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'AccountPasswordTest',
            'payload' => 'test-session-payload',
            'last_activity' => now()->timestamp,
        ]);
    }
}
