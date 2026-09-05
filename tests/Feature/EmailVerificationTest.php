<?php

namespace Tests\Feature;

use App\Enums\AvailabilityStatus;
use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\Pet;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_keeps_the_adopter_signed_in_and_dispatches_verification_immediately(): void
    {
        Notification::fake();
        config(['queue.default' => 'database']);

        $this->post(route('register.store'), [
            'first_name' => 'Jessa',
            'last_name' => 'Tester',
            'email' => 'jessa@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$this->addressPayload(),
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'jessa@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo(
            $user,
            QueuedVerifyEmail::class,
            fn (QueuedVerifyEmail $notification): bool => $notification->connection === 'sync'
                && $notification->queue === 'emails',
        );
    }

    public function test_a_valid_signed_link_verifies_the_user_and_dispatches_the_event(): void
    {
        Event::fake([Verified::class]);
        $user = $this->user(Role::Adopter, false, 'valid-link');
        $url = $this->verificationUrl($user, now()->addMinutes(60));

        $this->actingAs($user)->get($url)
            ->assertRedirect(route('animal.index'));

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_a_logged_out_adopter_can_verify_with_a_valid_signed_link(): void
    {
        Event::fake([Verified::class]);
        $adopter = $this->user(Role::Adopter, false, 'guest-adopter-link');

        $this->get($this->verificationUrl($adopter, now()->addMinutes(60)))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();
        $this->assertTrue($adopter->refresh()->hasVerifiedEmail());
        Event::assertDispatched(
            Verified::class,
            fn (Verified $event): bool => $event->user->is($adopter),
        );
    }

    public function test_a_logged_out_volunteer_can_verify_with_a_valid_signed_link(): void
    {
        Event::fake([Verified::class]);
        $volunteer = $this->user(Role::Volunteer, false, 'guest-volunteer-link');

        $this->get($this->verificationUrl($volunteer, now()->addMinutes(60)))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();
        $this->assertTrue($volunteer->refresh()->hasVerifiedEmail());
        Event::assertDispatched(
            Verified::class,
            fn (Verified $event): bool => $event->user->is($volunteer),
        );
    }

    public function test_expired_or_tampered_links_are_rejected(): void
    {
        $user = $this->user(Role::Adopter, false, 'invalid-link');

        $this->actingAs($user)
            ->get($this->verificationUrl($user, now()->subMinute()))
            ->assertForbidden();

        $valid = $this->verificationUrl($user, now()->addMinutes(60));
        $this->get($valid.'&hash=tampered')->assertForbidden();
        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_a_validly_signed_link_with_the_wrong_email_hash_is_rejected(): void
    {
        $user = $this->user(Role::Adopter, false, 'wrong-email-hash');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1('different-email@example.test'),
        ]);

        $this->get($url)->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_a_valid_signed_link_cannot_verify_an_inactive_account(): void
    {
        $user = $this->user(Role::Volunteer, false, 'inactive-link-target');
        $user->forceFill(['is_active' => false])->save();

        $this->get($this->verificationUrl($user, now()->addMinutes(60)))
            ->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_an_already_verified_guest_link_is_idempotent_and_does_not_redispatch_the_event(): void
    {
        Event::fake([Verified::class]);
        $user = $this->user(Role::Adopter, true, 'already-verified-guest-link');

        $this->get($this->verificationUrl($user, now()->addMinutes(60)))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        Event::assertNotDispatched(Verified::class);
    }

    public function test_a_verification_link_for_another_account_shows_a_clear_mismatch_message(): void
    {
        $linkOwner = $this->user(Role::Adopter, false, 'link-owner');
        $signedInUser = $this->user(Role::Adopter, false, 'signed-in-user');
        $url = $this->verificationUrl($linkOwner, now()->addMinutes(60));

        $this->actingAs($signedInUser)
            ->get($url)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_error', function (string $message) use ($signedInUser): bool {
                return str_contains($message, $signedInUser->email)
                    && str_contains($message, 'different account');
            });

        $this->assertFalse($linkOwner->refresh()->hasVerifiedEmail());
        $this->assertFalse($signedInUser->refresh()->hasVerifiedEmail());
    }

    public function test_unverified_users_can_resend_but_the_endpoint_is_throttled(): void
    {
        Notification::fake();
        $user = $this->user(Role::Adopter, false, 'resend');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($user)
                ->post(route('verification.send'))
                ->assertRedirect();
        }

        $this->post(route('verification.send'))->assertTooManyRequests();
        Notification::assertSentToTimes($user, QueuedVerifyEmail::class, 6);
    }

    public function test_unverified_adopters_can_browse_but_cannot_use_protected_workflows(): void
    {
        $user = $this->user(Role::Adopter, false, 'adopter-gate');
        $pet = Pet::create([
            'name' => 'Gate Test Pet',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);

        $this->actingAs($user)->get(route('animal.index'))->assertOk();
        $this->get(route('application.apply', $pet))->assertRedirect(route('verification.notice'));
        $this->get(route('application.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('monitoring.index'))->assertRedirect(route('verification.notice'));

        $user->markEmailAsVerified();
        $this->get(route('application.apply', $pet))->assertOk();
    }

    public function test_administrators_and_volunteers_must_verify_before_staff_access(): void
    {
        foreach ([Role::Administrator, Role::Volunteer] as $role) {
            $staff = $this->user($role, false, 'staff-gate-'.$role->value);

            $this->actingAs($staff)
                ->get(route('admin.dashboard'))
                ->assertRedirect(route('verification.notice'));

            $staff->markEmailAsVerified();
            $this->get(route('admin.dashboard'))->assertOk();
        }
    }

    public function test_only_the_two_placeholder_staff_accounts_are_seeded_as_verified(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@pairfectpaws.com')->sole();
        $volunteer = User::where('email', 'volunteer@pairfectpaws.com')->sole();
        $adopter = User::where('email', 'adopter@example.com')->sole();

        $this->assertSame(Role::Administrator, $admin->role);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertSame(Role::Volunteer, $volunteer->role);
        $this->assertTrue($volunteer->hasVerifiedEmail());
        $this->assertFalse($adopter->hasVerifiedEmail());
    }

    public function test_admin_created_administrator_is_immediately_verified_without_a_verification_email(): void
    {
        Notification::fake();
        $admin = $this->user(Role::Administrator, true, 'admin-creator');

        $this->actingAs($admin)->post(route('admin.volunteers.store'), [
            'first_name' => 'New',
            'last_name' => 'Administrator',
            'email' => 'new-administrator@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::Administrator->value,
            'branch_id' => null,
        ])->assertRedirect(route('admin.volunteers.index'));

        $administrator = User::where('email', 'new-administrator@example.test')->sole();
        $this->assertTrue($administrator->hasVerifiedEmail());
        Notification::assertNotSentTo($administrator, QueuedVerifyEmail::class);
    }

    public function test_admin_created_volunteer_remains_unverified_and_receives_a_verification_email(): void
    {
        Notification::fake();
        $admin = $this->user(Role::Administrator, true, 'staff-creator');

        $this->actingAs($admin)->post(route('admin.volunteers.store'), [
            'first_name' => 'New',
            'last_name' => 'Volunteer',
            'email' => 'new-volunteer@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::Volunteer->value,
            'branch_id' => null,
        ])->assertRedirect(route('admin.volunteers.index'));

        $volunteer = User::where('email', 'new-volunteer@example.test')->sole();
        $this->assertFalse($volunteer->hasVerifiedEmail());
        Notification::assertSentTo($volunteer, QueuedVerifyEmail::class);
    }

    public function test_add_volunteer_modal_uses_the_store_action_field_contract(): void
    {
        $admin = $this->user(Role::Administrator, true, 'volunteer-form');

        $this->actingAs($admin)
            ->get(route('admin.volunteers.index'))
            ->assertOk()
            ->assertSee('name="first_name"', false)
            ->assertSee('name="last_name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('value="Administrator"', false)
            ->assertSee('name="branch_id"', false)
            ->assertSee('Email Verification');
    }

    public function test_email_delivery_command_supports_configured_gmail_smtp(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'sender@gmail.com',
            'mail.mailers.smtp.password' => 'test-app-password',
            'mail.from.address' => 'sender@gmail.com',
        ]);

        $this->artisan('email:test recipient@example.test --audience=staff')
            ->expectsOutput('Queued the staff delivery test on the emails queue.')
            ->assertSuccessful();

        Mail::assertQueued(
            TransactionalMail::class,
            fn (TransactionalMail $mail): bool => $mail->envelope()->using === [],
        );
    }

    public function test_email_delivery_command_refuses_missing_smtp_credentials(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'sender@gmail.com',
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => 'sender@gmail.com',
        ]);

        $this->artisan('email:test recipient@example.test')
            ->expectsOutput('SMTP is not fully configured. Add the provider credential to MAIL_PASSWORD and clear the configuration cache.')
            ->assertFailed();

        Mail::assertNothingQueued();
    }

    private function user(Role $role, bool $verified, string $key): User
    {
        return User::create([
            'first_name' => 'Email',
            'last_name' => 'Tester',
            'email' => str_replace([' ', '.'], '-', $key).'-'.uniqid().'@example.test',
            'password' => 'password123',
            'role' => $role->value,
            'is_active' => true,
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    private function verificationUrl(User $user, \DateTimeInterface $expiration): string
    {
        return URL::temporarySignedRoute('verification.verify', $expiration, [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    /** @return array<string, string> */
    private function addressPayload(): array
    {
        return [
            'region_code' => '1300000000',
            'province_code' => '__direct__',
            'city_municipality_code' => '1380600000',
            'barangay_code' => '1380606197',
            'street_address' => '4489 V. Francisco St. Sta. Mesa',
            'zip_code' => '1016',
        ];
    }
}
