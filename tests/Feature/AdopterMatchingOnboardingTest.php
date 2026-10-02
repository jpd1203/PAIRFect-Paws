<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class AdopterMatchingOnboardingTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function registerAdopter(?string $redirect = null): User
    {
        $this->post(route('register.store'), [
            'first_name' => 'New', 'last_name' => 'Adopter',
            'email' => 'onboarding@example.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'region_code' => '1300000000', 'province_code' => '__direct__',
            'city_municipality_code' => '1380600000', 'barangay_code' => '1380606197',
            'street_address' => '4489 V. Francisco St. Sta. Mesa', 'zip_code' => '1016',
            'terms_accepted' => '1', 'privacy_consent' => '1',
            'redirect' => $redirect,
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'onboarding@example.test')->sole();
        $this->assertTrue($user->matching_onboarding_pending);
        $this->assertNull($user->adopterProfile);

        return $user;
    }

    private function verify(User $user): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('recommendation.onboarding'));
        $user->refresh();
    }

    private function completeAssessment(): array
    {
        return [
            'housing_type' => 'Single Family Home (Fenced Yard)',
            'monthly_income_range' => '₱30,000 - ₱50,000',
            'has_existing_pets' => false,
            'has_children' => false,
            'bfi_responses' => $this->bfiResponses(),
        ];
    }

    public function test_registration_offers_twenty_questions_but_skip_allows_browsing_without_a_profile(): void
    {
        $user = $this->registerAdopter();
        $this->get(route('recommendation.onboarding'))
            ->assertOk()
            ->assertSee('Verify your email address before you can apply to adopt a pet.');
        $this->get(route('pets.index'))->assertOk();
        $this->verify($user);

        $page = $this->get(route('recommendation.onboarding'))->assertOk()
            ->assertSee('Skip for now')->assertSee('bfi_responses[EX1]', false)
            ->assertSee('bfi_responses[O4]', false);
        $this->assertSame(20, substr_count($page->getContent(), 'name="bfi_responses['));

        $this->post(route('recommendation.onboarding.skip'))->assertRedirect(route('animal.index'));
        $this->assertFalse($user->fresh()->matching_onboarding_pending);
        $this->assertNull($user->fresh()->adopterProfile);
        $this->get(route('animal.index'))->assertOk();
    }

    public function test_skipped_adopter_must_complete_profile_before_recommendations_or_a_formal_application(): void
    {
        $user = $this->registerAdopter();
        $this->verify($user);
        $this->post(route('recommendation.onboarding.skip'))->assertRedirect(route('animal.index'));
        $pet = $this->matchingPet();

        $this->get(route('recommendation.results'))->assertRedirect(route('recommendation.intake'));
        $this->get(route('recommendation.intake'))->assertOk()->assertSee('bfi_responses[EX1]', false);
        $this->get(route('application.apply', $pet))
            ->assertRedirect(route('recommendation.intake', ['return_pet' => $pet->id]));
        $this->post(route('application.submit'), ['pet_id' => $pet->id])
            ->assertRedirect(route('recommendation.intake', ['return_pet' => $pet->id]));
        $this->assertDatabaseCount('adoption_applications', 0);

        $this->get(route('recommendation.intake', ['return_pet' => $pet->id]))->assertOk();
        $this->post(route('recommendation.start'), $this->completeAssessment())
            ->assertRedirect(route('application.apply', $pet));
        $this->assertCount(20, $user->fresh()->adopterProfile->bfi_responses);
        $this->get(route('application.apply', $pet))->assertOk();
    }

    public function test_onboarding_preserves_a_selected_pet_and_rejects_an_incomplete_questionnaire(): void
    {
        $pet = $this->matchingPet();
        $user = $this->registerAdopter(route('application.apply', $pet));
        $this->verify($user);

        $this->get(route('recommendation.onboarding'))->assertOk()
            ->assertSee('Personality Assessment Required for '.$pet->name);
        $incomplete = $this->completeAssessment();
        unset($incomplete['bfi_responses']['O4']);
        $this->post(route('recommendation.start'), $incomplete)
            ->assertSessionHasErrors('bfi_responses.O4');
        $this->assertTrue($user->fresh()->matching_onboarding_pending);
        $this->assertNull($user->fresh()->adopterProfile);

        $this->post(route('recommendation.start'), $this->completeAssessment())
            ->assertRedirect(route('application.apply', $pet));
        $this->assertFalse($user->fresh()->matching_onboarding_pending);
    }

    public function test_unanswered_n1_shows_a_clear_question_link_and_inline_error(): void
    {
        $user = $this->registerAdopter();
        $this->get(route('recommendation.onboarding'))->assertOk();
        $incomplete = $this->completeAssessment();
        unset($incomplete['bfi_responses']['N1']);

        $response = $this->post(route('recommendation.start'), $incomplete)
            ->assertSessionHasErrors([
                'bfi_responses.N1' => 'Please answer question 13: Is emotionally stable, not easily upset.',
            ]);
        $response->assertRedirect(route('recommendation.onboarding'));

        $this->get(route('recommendation.onboarding'))
            ->assertOk()
            ->assertSee('name="bfi_responses[N1]" required', false)
            ->assertDontSee('novalidate');
        $this->withViewErrors([
            'bfi_responses.N1' => 'Please answer question 13: Is emotionally stable, not easily upset.',
        ]);
        $html = view('recommendation.intake', [
            'options' => \App\Support\ApplicationOptions::class,
            'profile' => null,
            'returnPet' => null,
            'isOnboarding' => true,
        ])->render();
        $this->assertStringContainsString('href="#bfi_N1"', $html);
        $this->assertStringContainsString('aria-describedby="bfi_N1_error"', $html);
        $this->assertStringContainsString('Please answer question 13: Is emotionally stable, not easily upset.', $html);
        $this->assertNull($user->fresh()->adopterProfile);
    }

    public function test_onboarding_is_offered_after_verification_in_a_logged_out_session(): void
    {
        $pet = $this->matchingPet();
        $user = $this->registerAdopter(route('application.apply', $pet));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'email' => $user->email, 'password' => 'Password123!',
            'redirect' => route('application.apply', $pet),
        ])->assertRedirect(route('recommendation.onboarding'));
        $this->get(route('recommendation.onboarding'))->assertOk()
            ->assertSee('Skip for now')
            ->assertSee('Personality Assessment Required for '.$pet->name);
    }
}
