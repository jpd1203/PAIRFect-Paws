<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_displays_two_separate_unchecked_consents_and_public_legal_pages(): void
    {
        $this->get(route('login', ['tab' => 'register']))
            ->assertOk()
            ->assertSee('name="terms_accepted"', false)
            ->assertSee('name="privacy_consent"', false)
            ->assertSee('PAIRfect Paws Terms and Conditions')
            ->assertSee('PAIRfect Paws Privacy Notice')
            ->assertSee('data-legal-dialog="termsDialog"', false)
            ->assertSee('data-legal-dialog="privacyDialog"', false)
            ->assertSee('<dialog id="termsDialog"', false)
            ->assertSee('<dialog id="privacyDialog"', false)
            ->assertSee('Changes to These Terms')
            ->assertSee('Consent and Acknowledgment')
            ->assertSee('You must agree to the Terms and Conditions and acknowledge the Privacy Notice before creating an account.')
            ->assertSee(route('legal.terms'))
            ->assertSee(route('legal.privacy'))
            ->assertDontSee('name="terms"', false)
            ->assertDontSee('name="terms_accepted" checked', false)
            ->assertDontSee('name="privacy_consent" checked', false);

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Changes to These Terms')
            ->assertSee('Republic Act No. 10631');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Consent and Acknowledgment')
            ->assertDontSee('[INSERT OFFICIAL PRIVACY CONTACT / EMAIL]');
    }

    public function test_registration_rejects_each_missing_or_false_consent_without_creating_an_account(): void
    {
        Notification::fake();

        foreach ([
            [[], ['terms_accepted', 'privacy_consent']],
            [['terms_accepted' => '1'], ['privacy_consent']],
            [['privacy_consent' => '1'], ['terms_accepted']],
            [['terms_accepted' => '0', 'privacy_consent' => '0'], ['terms_accepted', 'privacy_consent']],
        ] as $index => [$consents, $errors]) {
            $email = "consent-rejected-{$index}@example.test";

            $this->from(route('login', ['tab' => 'register']))
                ->post(route('register.store'), $this->registrationPayload($email) + $consents)
                ->assertRedirect(route('login', ['tab' => 'register']))
                ->assertSessionHasErrors($errors);

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_successful_registration_records_server_generated_consent_audit_values_and_requires_email_verification_even_locally(): void
    {
        Notification::fake();
        $acceptedAt = now()->startOfSecond();
        $this->travelTo($acceptedAt);
        $this->app->detectEnvironment(fn () => 'local');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post(route('register.store'), $this->registrationPayload('consented@example.test') + [
            'terms_accepted' => '1',
            'privacy_consent' => '1',
            'terms_accepted_at' => '2000-01-01 00:00:00',
            'privacy_consent_at' => '2000-01-01 00:00:00',
            'terms_version' => '99.0',
            'privacy_notice_version' => '99.0',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'consented@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue($user->terms_accepted_at->equalTo($acceptedAt));
        $this->assertTrue($user->privacy_consent_at->equalTo($acceptedAt));
        $this->assertSame('1.0', $user->terms_version);
        $this->assertSame('1.0', $user->privacy_notice_version);
    }

    private function registrationPayload(string $email): array
    {
        return [
            'first_name' => 'Consent',
            'last_name' => 'Tester',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'region_code' => '1300000000',
            'province_code' => '__direct__',
            'city_municipality_code' => '1380600000',
            'barangay_code' => '1380606197',
            'street_address' => '4489 V. Francisco St. Sta. Mesa',
            'zip_code' => '1016',
        ];
    }
}
