<?php

namespace Tests\Feature;

use App\Contracts\GoogleAccessTokenProvider;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\KnnRecommendationService;
use App\Support\ResearchMode;
use Database\Seeders\ResearchCatalogSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\SubmitsResearchCheckIns;
use Tests\TestCase;

final class ResearchScenarioTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase, SubmitsResearchCheckIns;

    public function test_catalog_distribution_and_species_matching(): void
    {
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);
        $this->assertDatabaseCount('research_catalog', 70);
        $this->assertDatabaseCount('assessment_records', 210);
        $this->assertDatabaseCount('research_placements', 0);
        $this->assertDatabaseCount('adoption_applications', 0);
        $this->assertSame(70, Pet::fullyAssessed()->count());
        $this->assertSame(70, Pet::distinct()->count('name'));
        $this->assertSame(0, Pet::whereNotNull('photo_path')->count());
        $this->assertTrue(Pet::all()->every(fn (Pet $pet) => $pet->hasClassifiedPhysicalSize()));
        foreach (['Dog', 'Cat'] as $species) {
            $this->assertCount(35, Pet::where('species', $species)->get());
        }
        foreach (['young' => 22, 'adult' => 34, 'senior' => 14] as $stage => $count) {
            $this->assertSame($count, Pet::where('life_stage', $stage)->count());
        }
        foreach ([1 => 28, 2 => 16, 3 => 13, 4 => 8, 5 => 5] as $need => $count) {
            $this->assertSame($count, Pet::where('medical_needs', $need)->count());
        }
        $this->assertSame(70, DB::table('assessment_records')->select('pet_id')->groupBy('pet_id')->havingRaw('COUNT(DISTINCT assessor_id) = 3')->get()->count());
        $this->assertSame(3, User::where('is_active', false)->count());
        $this->assertSame(5, Pet::where('medical_needs', 5)->where('availability_status', 'On Hold')->count());
        $this->assertEqualsCanonicalizing(['Complete', 'Incomplete', 'Unknown'], Pet::distinct()->pluck('vaccination_record_status')->all());
        $matcher = app(KnnRecommendationService::class);
        $profile = $this->matchingAdopter();
        $this->assertCount(57, $matcher->recommendPets($profile));
        $this->assertCount(29, $matcher->recommendPets($profile, preferences: ['species' => 'Dog']));
        $this->assertCount(28, $matcher->recommendPets($profile, preferences: ['species' => 'Cat']));
        $this->assertGreaterThan(10, $matcher->recommendPets($profile)->pluck('result.compatibility_score')->unique()->count());
        $this->assertSame('EXCLUDED_FINANCIAL_READINESS', $matcher->calculateMatch($this->matchingAdopter(['financial_readiness' => 1]), Pet::first())->exclusionReason);
        $this->assertSame('EXCLUDED_EXISTING_PETS_REACTIVE', $matcher->calculateMatch($this->matchingAdopter(['has_existing_pets' => true]), Pet::where('name', 'Isla')->firstOrFail())->exclusionReason);
        $this->assertCount(30, $matcher->recommendPets($this->matchingAdopter(['financial_readiness' => 1, 'has_existing_pets' => true])));
        $this->expectExceptionMessage('fresh research database');
        $this->seed(ResearchCatalogSeeder::class);
    }

    public function test_research_registration_requires_email_verification_before_applications(): void
    {
        Notification::fake();
        Queue::fake();
        config(['research.enabled' => true]);
        $this->get(route('recommendation.intake'))->assertRedirect(route('login'));
        $payload = ['first_name' => 'Survey', 'last_name' => 'Respondent', 'email' => 'survey@example.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'region_code' => '1300000000', 'province_code' => '__direct__',
            'city_municipality_code' => '1380600000', 'barangay_code' => '1380606197',
            'street_address' => 'Fictional Street', 'zip_code' => '1016', 'terms_accepted' => '1', 'privacy_consent' => '1'];
        $this->post(route('register.store'), array_replace($payload, ['privacy_consent' => '0']))->assertSessionHasErrors('privacy_consent');
        $this->post(route('register.store'), $payload)->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'survey@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->privacy_consent_at);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertTrue($user->matching_onboarding_pending);
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
        $this->get(route('verification.notice'))->assertOk()->assertViewIs('auth.verify-email')->assertSee('Verify your email address');
        $this->get(route('animal.index'))->assertOk()->assertViewIs('animal.index')
            ->assertSee('Available Pets')->assertSee('Verify your email')->assertDontSee('Study Information')->assertDontSee('study-information', false);
        $this->get('/study-information')->assertRedirect(route('animal.index'));
        $this->post('/study-information', ['study_consent' => 1])->assertMethodNotAllowed();
        $this->assertDatabaseCount('research_study_consents', 0);
        $this->get(route('application.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('research.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('adopter.handover.my'))->assertRedirect(route('verification.notice'));
        $this->get(route('monitoring.my-checkins'))->assertRedirect(route('verification.notice'));
        $this->get(route('recommendation.results'))->assertRedirect(route('recommendation.intake'));
        $this->get(route('recommendation.intake'))->assertOk()->assertSee('Verify your email');
        $user->markEmailAsVerified();
        $this->actingAs($user->fresh());
        $this->get(route('application.index'))->assertOk()->assertViewIs('application.index')->assertDontSee('Study Information');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect(route('animal.index'));
        $this->assertAuthenticatedAs($user);
        Queue::assertNothingPushed();

        $staff = $this->matchingUser(Role::Volunteer);
        $staff->update(['email_verified_at' => null]);
        $this->actingAs($staff)->get(route('admin.applications.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('research.index'))->assertRedirect(route('access-denied'));
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get(route('animal.index'))->assertRedirect(route('access-denied'));
        $this->get(route('recommendation.intake'))->assertForbidden();
    }

    public function test_queue_uses_current_compatibility_and_enforces_owner_and_current_primary(): void
    {
        config(['research.enabled' => true]);
        Mail::fake();
        Queue::fake();
        Notification::fake();
        $this->seed(ResearchCatalogSeeder::class);
        $pet = Pet::firstOrFail();
        $a = $this->matchingUser();
        $b = $this->matchingUser();
        $c = $this->matchingUser();
        $apply = route('research.apply', $pet);
        $this->actingAs($a)->get(route('research.preview', $pet))
            ->assertRedirect(route('recommendation.intake', ['return_pet' => $pet->id]));
        $this->actingAs($a)->postJson($apply, ['eligible' => true])->assertUnprocessable();
        $profiles = collect(range(1, 5))->map(function ($rating) use ($a, $pet) {
            $profile = $this->completeMatchingProfile($a, ['bfi_responses' => $this->variedAnswers($rating)]);

            return ['rating' => $rating, 'score' => app(KnnRecommendationService::class)->calculateMatch($profile, $pet)->compatibilityScore];
        })->sortBy('score')->values();
        $this->assertLessThan($profiles->last()['score'], $profiles->first()['score']);
        foreach ([[$a, 3], [$b, $profiles->first()['rating']], [$c, $profiles->last()['rating']]] as [$u, $rating]) {
            $this->completeMatchingProfile($u, ['bfi_responses' => $this->variedAnswers($rating)]);
            $this->actingAs($u->fresh())->get(route('research.preview', $pet))->assertOk()
                ->assertViewIs('application.apply')->assertSee('Personal Information')->assertSee('Adopter Profile')
                ->assertSee('Adoption and Data Processing Agreement')->assertSee('id="termsModal"', false)
                ->assertSee('name="document"', false)->assertSee('Google Cloud Vision')->assertSee('(optional)')
                ->assertDontSee('Research testing mode')->assertDontSee('Study Information');
            $this->post($apply, $this->application())->assertRedirect(route('application.index'));
            $this->post($apply, $this->application())->assertRedirect(route('application.index'));
        }
        $primary = ResearchPlacement::where('user_id', $a->id)->sole();
        $earlier = ResearchPlacement::where('user_id', $b->id)->sole();
        $later = ResearchPlacement::where('user_id', $c->id)->sole();
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $primary->status);
        $this->assertSame(ApplicationStatus::Waitlisted, $earlier->status);
        $this->assertSame('Soft-Reserved', $pet->fresh()->availability_status->value);
        $applications = $this->get(route('application.index'))->assertOk()->assertViewIs('application.index')
            ->assertSee('Current waitlist position: 1.')->assertDontSee('Continue Application');
        $this->assertActiveSidebarLink($applications, route('application.index'));
        $this->get(route('adopter.handover.my'))->assertRedirect(route('application.index'))
            ->assertSessionHas('toast.type', 'error');
        $this->assertNull($later->document_verification_status);
        $this->post(route('research.continue', $later))->assertConflict();
        $this->post(route('research.continue', $primary))->assertNotFound();
        $this->post(route('research.receive', $later))->assertNotFound();
        $this->actingAs($a)->post(route('research.withdraw', $primary))->assertRedirect(route('application.index'));
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $later->fresh()->status);
        $this->assertSame(ApplicationStatus::Waitlisted, $earlier->fresh()->status);
        $this->actingAs($c)->post(route('research.continue', $later))->assertRedirect(route('application.index'));
        $this->post(route('research.continue', $later))->assertRedirect(route('application.index'));
        $this->assertSame('Adopted', $pet->fresh()->availability_status->value);
        $this->assertSame(ApplicationStatus::Closed, $earlier->fresh()->status);
        $this->assertSame(1, ResearchPlacement::where('status', 'Approved')->count());
        $this->actingAs($this->matchingUser(Role::Administrator))->put(route('admin.animals.update', $pet), [
            'name' => $pet->name, 'species' => $pet->species->value, 'status' => 'Available', 'version' => $pet->version,
        ])->assertRedirect(route('admin.animals.index'));
        $this->assertSame('Adopted', $pet->fresh()->availability_status->value);
        $other = $this->matchingUser();
        $this->completeMatchingProfile($other);
        $this->actingAs($other);
        $this->postJson($apply)->assertUnprocessable();
        $this->get(route('adopter.handover.my'))->assertRedirect(route('application.index'));
        $this->get(route('research.handover', $later))->assertNotFound();
        $this->post(route('research.receive', $later))->assertNotFound();
        $report = ['pet_current_status' => 'Good', 'living_conditions' => 'Indoor Only', 'eating_habits' => 'Normal', 'behavioral_observations' => 'Still adjusting'];
        $this->post(route('research.checkin', $later), $report + ['milestone' => '3-day'])->assertNotFound();
        $this->actingAs($c)->get(route('adopter.handover.my'))->assertRedirect(route('research.handover', $later));
        $detail = $this->get(route('research.handover', $later))->assertOk()->assertViewIs('handover.status')
            ->assertSee($pet->name)->assertSee('Handover Method Preference')->assertDontSee('Confirm Receipt');
        $this->assertActiveSidebarLink($detail, route('adopter.handover.my'));
        $this->post(route('research.checkin', $later), $report + ['milestone' => '3-day'])->assertConflict();
        $this->post(route('research.receive', $later), ['outcome' => 'received', 'schedule_version' => 0])->assertConflict();
        $this->post(route('research.handover.method', $later), ['method' => 'pickup', 'schedule_version' => 0])->assertRedirect();
        $this->post(route('research.handover.confirm', $later), ['schedule_version' => 1])->assertRedirect();
        $this->get(route('research.receipt', $later))->assertOk()->assertSee('Confirm Pet Receipt');
        $this->post(route('research.receive', $later), ['outcome' => 'received', 'schedule_version' => 2])->assertRedirect(route('research.receipt', $later));
        $this->post(route('research.receive', $later), ['outcome' => 'received', 'schedule_version' => 2])->assertConflict();
        $this->get(route('research.handover', $later))->assertOk()->assertSee('Receipt confirmation recorded');
        $checkinsPage = $this->get(route('monitoring.my-checkins'))->assertOk()->assertViewIs('monitoring.my-checkins');
        $this->assertActiveSidebarLink($checkinsPage, route('monitoring.my-checkins'));
        $this->postJson(route('research.capture-challenge', [$later, '3-month']))->assertConflict();
        $this->probeResearchVideo();
        Storage::fake('research-private');
        foreach (['3-day', '3-week', '3-month'] as $milestone) {
            $this->get(route('research.report', $later))->assertOk()->assertViewIs('monitoring.create')
                ->assertSee('Living Conditions')->assertSee('Start Camera');
            $body = $report + ['milestone' => $milestone] + $this->researchCapture($later, $milestone, $milestone);
            $this->post(route('research.checkin', $later), $body)->assertRedirect(route('monitoring.my-checkins'));
            $this->post(route('research.checkin', $later), $body)->assertConflict();
        }
        $this->get(route('monitoring.my-checkins'))->assertOk()->assertViewIs('monitoring.my-checkins')
            ->assertSee('Submitted Reports')->assertSee('3-Month');
        $this->assertDatabaseCount('research_checkins', 3);
        $this->assertDatabaseCount('adoption_applications', 0);
        $this->assertDatabaseCount('handovers', 0);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->assertDatabaseCount('identity_verifications', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    private function assertActiveSidebarLink(TestResponse $response, string $href): void
    {
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $links = (new DOMXPath($document))->query('//*[@id="appSidebar"]//a[@aria-current="page"]');

        $this->assertCount(1, $links);
        $this->assertSame($href, $links->item(0)->getAttribute('href'));
        $this->assertStringContainsString('active', $links->item(0)->getAttribute('class'));
    }

    private function variedAnswers(int $rating): array
    {
        return array_replace($this->bfiResponses(), ['EX1' => $rating, 'EX4' => $rating, 'EX5' => $rating]);
    }

    public function test_queue_exhaustion_and_disabled_mode(): void
    {
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);
        $user = $this->matchingUser();
        $this->completeMatchingProfile($user);
        $this->actingAs($user);
        $pet = Pet::firstOrFail();
        $this->post(route('research.apply', $pet), $this->application())->assertRedirect();
        $this->post(route('research.withdraw', ResearchPlacement::sole()))->assertRedirect();
        $this->assertSame('Available', $pet->fresh()->availability_status->value);
        $this->post(route('application.submit'), ['pet_id' => $pet->id])->assertForbidden();
        config(['research.enabled' => false]);
        $this->get(route('research.index'))->assertNotFound();
        $this->get(route('application.apply', $pet))->assertOk()->assertViewIs('application.apply')
            ->assertSee('Upload Government ID Showing Your Name and Residential Address')
            ->assertSee('name="document"', false)
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_research_accepts_applications_without_documents_and_preserves_prior_records(): void
    {
        config(['research.enabled' => true, 'document_verification.provider' => 'google_vision']);
        Storage::fake('research-ocr');
        $this->seed(ResearchCatalogSeeder::class);
        $user = $this->matchingUser();
        $this->completeMatchingProfile($user);
        $this->actingAs($user);
        $this->app->bind(GoogleAccessTokenProvider::class, fn () => new class implements GoogleAccessTokenProvider
        {
            public function accessToken(): string
            {
                return 'synthetic-token';
            }
        });
        Http::fake();
        $pet = Pet::where('availability_status', 'Available')->firstOrFail();
        $apply = route('research.apply', $pet);
        $this->post($apply, array_replace($this->application(), ['agreed_to_terms' => 0]))->assertSessionHasErrors('agreed_to_terms');
        $this->post($apply, $this->application() + ['document' => UploadedFile::fake()->image('synthetic-id.png')])
            ->assertSessionHasErrors('document_consent');
        $this->assertDatabaseCount('research_placements', 0);
        $this->post($apply, $this->application())->assertRedirect(route('application.index'));
        $placement = ResearchPlacement::sole();
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $placement->status);
        $this->assertNull($placement->document_verification_status);
        $this->assertNull($placement->document_consent_at);
        $this->assertNotEmpty($placement->application_details['agreed_at']);
        $this->assertStringNotContainsString('Rizal', $placement->getRawOriginal('application_details'));

        // Model an application carried over from before ID collection ended.
        $consentAt = now()->subDay()->startOfSecond();
        $placement->update([
            'document_verification_status' => DocumentVerificationStatus::NeedsResubmission,
            'document_verification_reasons' => ['Legacy mismatch'],
            'document_consent_at' => $consentAt,
            'document_consent_version' => 'study-ocr-v3',
        ]);
        $this->get(route('application.index'))->assertOk()->assertViewIs('application.index')
            ->assertSee('Continue Application')->assertSee('Document Verification')
            ->assertDontSee('Submit Replacement')->assertDontSee('Submit Follow-up Document')->assertSee('Legacy mismatch');
        $this->post("/research/placements/{$placement->id}/document", [
            'document' => UploadedFile::fake()->image('synthetic-id.png'),
        ])->assertNotFound();
        $this->post(route('research.continue', $placement))->assertRedirect(route('application.index'));
        $placement->refresh();
        $this->assertSame(ApplicationStatus::Approved, $placement->status);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $placement->document_verification_status);
        $this->assertSame(['Legacy mismatch'], $placement->document_verification_reasons);
        $this->assertSame($consentAt->toISOString(), $placement->document_consent_at->toISOString());
        $this->assertSame('study-ocr-v3', $placement->document_consent_version);
        $this->assertSame(0, $placement->document_reupload_count);
        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('research-ocr')->allFiles());
        $this->assertFalse(config('filesystems.disks.research-ocr.serve'));
    }

    public function test_consented_documents_are_private_and_ocr_outcomes_never_gate_approval(): void
    {
        config(['research.enabled' => true, 'research.ocr_enabled' => true, 'document_verification.provider' => 'google_vision']);
        $disk = Storage::fake('research-private');
        $this->seed(ResearchCatalogSeeder::class);
        $user = $this->matchingUser();
        $this->completeMatchingProfile($user);
        $this->app->bind(GoogleAccessTokenProvider::class, fn () => new class implements GoogleAccessTokenProvider
        {
            public function accessToken(): string
            {
                return 'synthetic-token';
            }
        });
        Http::preventStrayRequests();
        Http::fake(['vision.googleapis.com/*' => Http::sequence()
            ->push(['responses' => [['fullTextAnnotation' => ['text' => "PHILIPPINE IDENTIFICATION CARD\nJUAN CRUZ\nADDRESS 123 RIZAL STREET CITY OF MANILA\nNONRETAINEDWORD"]]]])
            ->push(['responses' => [['fullTextAnnotation' => ['text' => "PHILIPPINE IDENTIFICATION CARD\nSAMPLE PERSON\nADDRESS 987 OTHER ROAD QUEZON CITY"]]]])
            ->push(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);
        $pets = Pet::where('availability_status', 'Available')->orderBy('id')->take(3)->get();
        $statuses = [DocumentVerificationStatus::Verified, DocumentVerificationStatus::NeedsResubmission, DocumentVerificationStatus::ManualReview];
        $this->actingAs($user);
        foreach ($pets as $index => $pet) {
            $this->post(route('research.apply', $pet), $this->application() + [
                'document' => UploadedFile::fake()->image('synthetic-id.png'), 'document_consent' => 1,
            ])->assertRedirect(route('application.index'));
            $placement = ResearchPlacement::where('pet_id', $pet->id)->sole();
            $this->assertSame($statuses[$index], $placement->document_verification_status);
            $this->assertNotNull($placement->document_consent_at);
            $this->assertNotNull($placement->document_uploaded_at);
            $disk->assertExists($placement->document_path);
            $this->assertStringNotContainsString('NONRETAINEDWORD', $placement->toJson());
            $this->assertNull($placement->getRawOriginal('ocr_extracted_text'));
            $this->assertArrayNotHasKey('document_path', $placement->toArray());
            $this->post(route('research.continue', $placement))->assertRedirect(route('application.index'));
            $this->assertSame(ApplicationStatus::Approved, $placement->fresh()->status);
        }
        Http::assertSentCount(3);
        $protected = route('admin.research.document', $placement);
        $this->get($protected)->assertRedirect(route('access-denied'));
        $this->actingAs($this->matchingUser(Role::Volunteer))->get($protected)->assertRedirect(route('access-denied'));
        $this->actingAs($this->matchingUser(Role::Administrator))->get($protected)->assertOk()
            ->assertDownload('identity-document.png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseCount('adoption_applications', 0);
    }

    public function test_consented_ocr_text_is_encrypted_and_visible_only_on_the_administrator_detail_page(): void
    {
        config(['research.enabled' => true, 'research.ocr_enabled' => true, 'research.retain_ocr_text' => true, 'document_verification.provider' => 'google_vision']);
        Storage::fake('research-private');
        $this->seed(ResearchCatalogSeeder::class);
        $adopter = $this->matchingUser();
        $this->completeMatchingProfile($adopter);
        [$pet, $blankPet] = Pet::where('availability_status', 'Available')->orderBy('id')->take(2)->get();
        $this->app->bind(GoogleAccessTokenProvider::class, fn () => new class implements GoogleAccessTokenProvider
        {
            public function accessToken(): string
            {
                return 'synthetic-token';
            }
        });
        Http::preventStrayRequests();
        $text = "PHILIPPINE IDENTIFICATION CARD\nJUAN CRUZ\nADDRESS 123 RIZAL STREET CITY OF MANILA\n<sample>OCRONLY-EXAMPLE</sample>";
        Http::fake(['vision.googleapis.com/*' => Http::sequence()
            ->push(['responses' => [['fullTextAnnotation' => ['text' => $text]]]])
            ->push(['responses' => [[]]])]);

        $this->actingAs($adopter)->get(route('research.preview', $pet))
            ->assertSee('encrypted storage of the extracted text')
            ->assertSee('name="document_consent_version"', false);
        $this->post(route('research.apply', $pet), $this->application() + [
            'document' => UploadedFile::fake()->image('example-id.png'), 'document_consent' => 1,
        ])->assertSessionHasErrors('document_consent_version');
        Http::assertNothingSent();
        foreach ([$pet, $blankPet] as $catalogPet) {
            $this->post(route('research.apply', $catalogPet), $this->application() + [
                'document' => UploadedFile::fake()->image('example-id.png'),
                'document_consent' => 1,
                'document_consent_version' => ResearchMode::OCR_TEXT_CONSENT_VERSION,
            ])->assertRedirect(route('application.index'));
        }
        Http::assertSentCount(2);

        $placement = ResearchPlacement::where('pet_id', $pet->id)->sole();
        $blank = ResearchPlacement::where('pet_id', $blankPet->id)->sole();
        $this->assertSame($text, $placement->ocr_extracted_text);
        $this->assertStringNotContainsString('OCRONLY-EXAMPLE', $placement->getRawOriginal('ocr_extracted_text'));
        $this->assertArrayNotHasKey('ocr_extracted_text', $placement->toArray());
        $this->assertNull($blank->getRawOriginal('ocr_extracted_text'));

        $url = route('admin.research.document.ocr', $placement);
        $urlInJson = str_replace('/', '\/', $url);
        $this->get($url)->assertRedirect(route('access-denied'));
        $this->get(route('application.index'))->assertDontSee('OCRONLY-EXAMPLE');
        $this->actingAs($this->matchingUser(Role::Volunteer))->get($url)->assertRedirect(route('access-denied'));
        $this->get(route('admin.applications.index'))
            ->assertDontSee($urlInJson, false)->assertDontSee('OCRONLY-EXAMPLE');

        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->get(route('admin.applications.index'))
            ->assertSee($urlInJson, false)->assertDontSee('OCRONLY-EXAMPLE');
        $this->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Extracted OCR Text')->assertSee('Match Score')
            ->assertSee('<sample>OCRONLY-EXAMPLE</sample>')
            ->assertDontSee('<sample>OCRONLY-EXAMPLE</sample>', false);
        $this->get(route('admin.research.document.ocr', $blank))->assertOk()->assertSee('No extracted text is available.');

        $blank->update(['document_consent_version' => 'research-document-v1']);
        $this->get(route('admin.research.document.ocr', $blank))->assertOk()->assertSee('No OCR text was retained');
        config(['research.retain_ocr_text' => false]);
        $this->get($url)->assertNotFound();
        config(['research.retain_ocr_text' => true, 'research.enabled' => false]);
        $this->get($url)->assertNotFound();
    }

    public function test_ocr_cleanup_only_removes_old_generated_study_files(): void
    {
        $disk = Storage::fake('research-ocr');
        $old = Str::uuid().'.ocr';
        $fresh = Str::uuid().'.ocr';
        foreach ([$old, $fresh, 'unrelated.txt'] as $path) {
            $disk->put($path, 'synthetic');
        }
        touch($disk->path($old), now()->subHours(2)->timestamp);
        touch($disk->path('unrelated.txt'), now()->subHours(2)->timestamp);
        $this->artisan('research:purge-ocr-temp')->assertFailed();
        $disk->assertExists($old);
        config(['research.enabled' => true]);
        $this->artisan('research:purge-ocr-temp')->assertSuccessful();
        $disk->assertMissing($old);
        $disk->assertExists([$fresh, 'unrelated.txt']);
    }

    private function application(): array
    {
        return [
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'sample@example.test',
            'phone_number' => '5550001234', 'motivation_statement' => 'I am completing the sample exercise.',
            'physical_activity_level' => 'Moderate', 'time_availability' => 'Daily',
            'prior_pet_experience' => 'Some', 'household_composition' => 'Adults',
            'region_code' => '1300000000', 'province_code' => '__direct__', 'city_municipality_code' => '1380600000',
            'barangay_code' => '1380606197', 'street_address' => '123 Rizal Street', 'zip_code' => '1016',
            'agreed_to_terms' => 1,
        ];
    }
}
