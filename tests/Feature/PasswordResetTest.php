<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_links_to_a_working_password_reset_request_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('name="email"', false);
    }

    public function test_reset_link_requests_are_enumeration_safe_and_dispatch_an_encrypted_email_immediately_only_for_active_accounts(): void
    {
        Notification::fake();
        config(['queue.default' => 'database']);

        $active = User::factory()->create(['email' => 'active-reset@example.test']);
        $inactive = User::factory()->create([
            'email' => 'inactive-reset@example.test',
            'is_active' => false,
        ]);

        $activeResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $active->email,
        ])->assertRedirect(route('password.request'));

        $unknownResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'not-an-account@example.test',
        ])->assertRedirect(route('password.request'));

        $inactiveResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $inactive->email,
        ])->assertRedirect(route('password.request'));

        $activeStatus = $activeResponse->getSession()->get('status');
        $this->assertIsString($activeStatus);
        $this->assertSame($activeStatus, $unknownResponse->getSession()->get('status'));
        $this->assertSame($activeStatus, $inactiveResponse->getSession()->get('status'));
        $this->assertStringContainsString('sent', $activeStatus);

        Notification::assertSentTo($active, QueuedResetPassword::class, function ($notification): bool {
            $this->assertInstanceOf(ShouldQueue::class, $notification);
            $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
            $this->assertSame('sync', $notification->connection);
            $this->assertSame('emails', $notification->queue);

            return true;
        });
        Notification::assertNotSentTo($inactive, QueuedResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_the_emailed_token_opens_a_prefilled_reset_form(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset-form@example.test']);
        $token = $this->requestResetToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('action="'.route('password.update').'"', false)
            ->assertSee('name="token" value="'.$token.'"', false)
            ->assertSee('value="'.$user->email.'"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_a_valid_token_resets_the_password_revokes_sessions_and_cannot_be_reused(): void
    {
        config(['session.driver' => 'database']);

        Notification::fake();
        Event::fake([PasswordReset::class]);

        $user = User::factory()->create([
            'email' => 'valid-reset@example.test',
            'password' => 'old-password',
            'remember_token' => 'old-remember-token',
        ]);
        $otherUser = User::factory()->create();
        $token = $this->requestResetToken($user);

        $this->insertSession('reset-user-session', $user);
        $this->insertSession('other-user-session', $otherUser);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['id' => 'reset-user-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));

        $this->from(route('login'))->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'attempted-reuse-password',
            'password_confirmation' => 'attempted-reuse-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('new-secure-password', $user->refresh()->password));
    }

    public function test_invalid_and_expired_tokens_do_not_change_the_password(): void
    {
        Notification::fake();
        $invalidUser = User::factory()->create(['password' => 'original-password']);

        $this->from(route('password.request'))->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $invalidUser->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('original-password', $invalidUser->refresh()->password));

        $expiredUser = User::factory()->create(['password' => 'another-original-password']);
        $expiredToken = $this->requestResetToken($expiredUser);
        $this->travel(61)->minutes();

        $this->from(route('password.request'))->post(route('password.update'), [
            'token' => $expiredToken,
            'email' => $expiredUser->email,
            'password' => 'new-expired-password',
            'password_confirmation' => 'new-expired-password',
        ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('another-original-password', $expiredUser->refresh()->password));
    }

    public function test_password_confirmation_is_required_before_a_token_is_consumed(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'original-password']);
        $token = $this->requestResetToken($user);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('original-password', $user->refresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_a_deactivated_account_cannot_use_a_previously_issued_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'original-password']);
        $token = $this->requestResetToken($user);
        $user->update(['is_active' => false]);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('original-password', $user->refresh()->password));
    }

    public function test_password_reset_link_requests_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('password.email'), [
                'email' => "unknown-{$attempt}@example.test",
            ])->assertRedirect();
        }

        $this->post(route('password.email'), [
            'email' => 'unknown-sixth@example.test',
        ])->assertTooManyRequests();
    }

    private function requestResetToken(User $user): string
    {
        $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ])->assertRedirect(route('password.request'));

        return $this->sentResetToken($user);
    }

    private function sentResetToken(User $user): string
    {
        $token = null;

        Notification::assertSentTo(
            $user,
            QueuedResetPassword::class,
            function (QueuedResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        return $token;
    }

    private function insertSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PasswordResetTest',
            'payload' => 'test-session-payload',
            'last_activity' => now()->timestamp,
        ]);
    }
}
