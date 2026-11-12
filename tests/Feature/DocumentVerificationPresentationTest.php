<?php

namespace Tests\Feature;

use App\Contracts\GoogleAccessTokenProvider;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Services\DocumentVerificationService;
use App\ValueObjects\DocumentVerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class DocumentVerificationPresentationTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        config(['document_verification.provider' => 'google_vision']);
        $this->app->instance(GoogleAccessTokenProvider::class, new class implements GoogleAccessTokenProvider
        {
            public function accessToken(): string
            {
                return 'synthetic-test-token';
            }
        });
    }

    public function test_manual_approval_without_text_is_not_presented_as_google_completion_and_does_not_rewrite_history(): void
    {
        config(['document_verification.provider' => 'manual']);
        $application = $this->application();
        $this->persistResult($application, $this->extract());
        $staff = $this->matchingUser(Role::Administrator);
        $reason = 'Synthetic manual staff decision; no OCR extraction or real identity assertion.';
        $this->actingAs($staff)->post(route('admin.applications.document-decision', $application), [
            'document_decision' => 'Verified', 'document_reason' => $reason,
        ])->assertSessionHasNoErrors();
        $raw = (array) DB::table('adoption_applications')->find($application->id);
        $audits = AuditLog::orderBy('id')->get()->toJson();
        $this->assertSame('Verified', $raw['document_verification_status']);
        $this->assertNotNull($raw['document_verified_at']);
        $this->assertNull($raw['ocr_extracted_text']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Manual Document Verification Decision', 'entity_id' => $application->id]);

        $this->get(route('admin.applications.document-verification', $application))->assertOk()
            ->assertSee('Document Review Status')->assertSee('The document is marked verified.')
            ->assertSee('may reflect a manual staff decision')->assertSee('Verification status alone does not confirm OCR completion.')
            ->assertSee($reason)->assertDontSee('Google Vision completed')->assertDontSee('Automatic OCR Cross-Reference (Current Policy)');
        $this->assertSame($raw, (array) DB::table('adoption_applications')->find($application->id));
        $this->assertSame($audits, AuditLog::orderBy('id')->get()->toJson());
        Http::assertNothingSent();
    }

    public function test_recorded_text_is_escaped_and_described_without_provider_or_identity_claims(): void
    {
        $text = "PHILIPPINE IDENTIFICATION CARD\nSYNTHETIC ADOPTER\nADDRESS 16 FICTIONAL STREET MANILA\n<script>window.__ocrMarker=1</script>";
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [['fullTextAnnotation' => ['text' => $text]]]])]);
        $application = $this->application();
        $this->persistResult($application, $this->extract());
        $this->actingAs($this->matchingUser(Role::Administrator))
            ->get(route('admin.applications.document-verification', $application))->assertOk()
            ->assertSee('Extracted OCR text is recorded below.')->assertSee('Text extraction does not establish identity or document authenticity.')
            ->assertSee($text)->assertDontSee('<script>window.__ocrMarker=1</script>', false)
            ->assertSee('Automatic OCR Cross-Reference (Current Policy)')->assertDontSee('Google Vision completed');
        Http::assertSentCount(1);
    }

    public function test_successful_empty_provider_response_is_not_confused_with_a_proven_google_completion_marker(): void
    {
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [[]]])]);
        $application = $this->application();
        $this->persistResult($application, $this->extract());
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $application->fresh()->document_verification_status);
        $this->actingAs($this->matchingUser(Role::Administrator))->get(route('admin.applications.document-verification', $application))
            ->assertOk()->assertSee('No readable OCR text is recorded.')->assertSee('text is incomplete or unclear')
            ->assertDontSee('Google Vision completed');
        Http::assertSentCount(1);
    }

    public static function unavailableStates(): array
    {
        return [['manual', false, 'configured OCR provider is unavailable'],
            ['google_vision', true, 'Google Vision OCR is not authorized']];
    }

    #[DataProvider('unavailableStates')]
    public function test_actual_unavailable_and_rejected_extraction_preserve_the_recorded_reason(string $provider, bool $reject, string $reason): void
    {
        config(['document_verification.provider' => $provider]);
        if ($reject) {
            Http::fake(['vision.googleapis.com/*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);
        }
        $application = $this->application();
        $this->persistResult($application, $this->extract());
        $this->assertSame(DocumentVerificationStatus::ManualReview, $application->fresh()->document_verification_status);
        $this->actingAs($this->matchingUser(Role::Administrator))->get(route('admin.applications.document-verification', $application))
            ->assertOk()->assertSee('No completed OCR result is recorded.')->assertSee($reason)
            ->assertDontSee('Google Vision completed')->assertDontSee('Google did not return');
        $reject ? Http::assertSentCount(1) : Http::assertNothingSent();
    }

    public static function ambiguousStates(): array
    {
        return [['Pending', null, 'No readable OCR text is recorded.'],
            ['Verified', null, 'Verification status alone does not confirm OCR completion.'],
            ['Verified', " \n\t ", 'Verification status alone does not confirm OCR completion.'],
            ['LegacyReview', null, 'No OCR extraction result is recorded for this legacy document.'],
            ['NeedsResubmission', null, 'No readable OCR text is recorded.']];
    }

    #[DataProvider('ambiguousStates')]
    public function test_ambiguous_legacy_metadata_remains_neutral(string $status, ?string $text, string $expected): void
    {
        $application = $this->application();
        $application->update(['document_verification_status' => $status, 'ocr_extracted_text' => $text,
            'document_verification_reasons' => ['Synthetic note <b>must be escaped</b>']]);
        $raw = (array) DB::table('adoption_applications')->find($application->id);
        $this->actingAs($this->matchingUser(Role::Administrator))->get(route('admin.applications.document-verification', $application))
            ->assertOk()->assertSee($expected)->assertDontSee('Google Vision completed')->assertDontSee('has not been processed by OCR')
            ->assertSee('Synthetic note <b>must be escaped</b>')->assertDontSee('<b>must be escaped</b>', false);
        $this->assertSame($raw, (array) DB::table('adoption_applications')->find($application->id));
    }

    public function test_document_text_still_requires_authorized_active_verified_staff(): void
    {
        $application = $this->application();
        $application->update(['ocr_extracted_text' => 'SENSITIVE SYNTHETIC TEXT']);
        $url = route('admin.applications.document-verification', $application);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($application->user)->get($url)->assertRedirect(route('access-denied'));
        $inactive = $this->matchingUser(Role::Volunteer);
        $inactive->update(['is_active' => false]);
        $this->actingAs($inactive)->get($url)->assertRedirect();
        $unverified = $this->matchingUser(Role::Volunteer);
        $unverified->update(['email_verified_at' => null]);
        $this->actingAs($unverified)->get($url)->assertRedirect(route('verification.notice'));
        $this->actingAs($this->matchingUser(Role::Volunteer))->get($url)->assertOk()->assertSee('SENSITIVE SYNTHETIC TEXT');
    }

    private function application(): AdoptionApplication
    {
        $user = $this->matchingUser();
        $pet = Pet::create(['name' => 'Synthetic OCR pet', 'species' => 'Dog', 'age' => 36, 'weight_kg' => '12.000', 'availability_status' => 'Available']);

        return AdoptionApplication::create(['user_id' => $user->id, 'pet_id' => $pet->id, 'status' => 'Pending',
            'document_path' => 'adoption-documents/synthetic.png', 'document_disk' => 'local',
            'document_mime_type' => 'image/png', 'document_verification_status' => 'Pending']);
    }

    private function extract(): DocumentVerificationResult
    {
        Storage::disk('local')->put('adoption-documents/synthetic.png', 'fictional provider fixture bytes');

        return app(DocumentVerificationService::class)->verify('local', 'adoption-documents/synthetic.png', 'image/png',
            ['first_name' => 'Synthetic', 'last_name' => 'Adopter', 'address' => '16 Fictional Street Manila']);
    }

    private function persistResult(AdoptionApplication $application, DocumentVerificationResult $result): void
    {
        $application->update(['document_verification_status' => $result->status->value,
            'document_verification_reasons' => $result->reasons, 'ocr_extracted_text' => $result->extractedText,
            'document_match_score' => $result->matchScore, 'document_type' => $result->documentType]);
    }
}
