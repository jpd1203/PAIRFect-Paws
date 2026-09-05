<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StaffEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_alerts_reach_the_configured_mailbox_and_real_verified_staff_but_not_placeholder_accounts(): void
    {
        Mail::fake();
        config(['mail.staff_alert_address' => 'staff-alerts@example.test']);

        $placeholderAdmin = $this->staff(
            Role::Administrator,
            'admin@pairfectpaws.com',
        );
        $placeholderVolunteer = $this->staff(
            Role::Volunteer,
            'volunteer@pairfectpaws.com',
        );
        $realStaff = $this->staff(
            Role::Volunteer,
            'real-volunteer@example.test',
        );

        app(EmailNotificationService::class)->staff(
            'Priority welfare review',
            'A welfare report requires attention',
            ['Review the submitted report.'],
        );

        Mail::assertQueued(TransactionalMail::class, 2);
        Mail::assertQueued(
            TransactionalMail::class,
            fn (TransactionalMail $mail): bool => $mail->hasTo('staff-alerts@example.test'),
        );
        Mail::assertQueued(
            TransactionalMail::class,
            fn (TransactionalMail $mail): bool => $mail->hasTo($realStaff->email),
        );
        Mail::assertNotQueued(
            TransactionalMail::class,
            fn (TransactionalMail $mail): bool => $mail->hasTo($placeholderAdmin->email),
        );
        Mail::assertNotQueued(
            TransactionalMail::class,
            fn (TransactionalMail $mail): bool => $mail->hasTo($placeholderVolunteer->email),
        );
    }

    public function test_configured_staff_mailbox_is_not_queued_twice_when_it_is_also_a_verified_staff_account(): void
    {
        Mail::fake();
        config(['mail.staff_alert_address' => 'alerts@example.test']);

        $staff = $this->staff(Role::Administrator, 'alerts@example.test');

        app(EmailNotificationService::class)->staff(
            'Priority welfare review',
            'A welfare report requires attention',
            ['Review the submitted report.'],
        );

        Mail::assertQueued(TransactionalMail::class, 1);
        Mail::assertQueued(
            TransactionalMail::class,
            fn (TransactionalMail $mail): bool => $mail->hasTo($staff->email),
        );
    }

    private function staff(Role $role, string $email): User
    {
        return User::create([
            'first_name' => 'Email',
            'last_name' => 'Reviewer',
            'email' => $email,
            'password' => 'password123',
            'role' => $role->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
