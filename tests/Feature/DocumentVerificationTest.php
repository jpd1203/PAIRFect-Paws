<?php

namespace Tests\Feature;

use App\Contracts\GoogleAccessTokenProvider;
use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\User;
use App\Services\DocumentVerificationNotificationService;
use App\Services\DocumentVerificationService;
use App\ValueObjects\DocumentVerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class DocumentVerificationTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(GoogleAccessTokenProvider::class, new class implements GoogleAccessTokenProvider
        {
            public function accessToken(): string
            {
                return 'adc-test-token';
            }
        });
    }

    public function test_replacement_required_notification_is_queued_for_the_verified_adopter(): void
    {
        Mail::fake();
        [$adopter, $pet] = $this->adopterAndPet();
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::DocumentFlagged->value,
            'document_verification_status' => DocumentVerificationStatus::NeedsResubmission->value,
            'document_verification_reasons' => ['The submitted image is unclear.'],
            'document_reupload_count' => 0,
        ]);

        app(DocumentVerificationNotificationService::class)->send($application);

        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool => $mail->hasTo($adopter->email)
            && $mail->subjectLine === 'Updated adoption document required'
        );
    }

    public function test_ocr_text_is_cross_referenced_without_biometrics(): void
    {
        $service = app(DocumentVerificationService::class);
        $result = $service->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nPHILIPPINE IDENTIFICATION CARD\nJUAN DELA CRUZ\n123 RIZAL STREET QUEZON CITY",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street, Quezon City']
        );

        $this->assertSame(DocumentVerificationStatus::Verified, $result->status);
        $this->assertSame('Philippine National ID', $result->documentType);
        $this->assertGreaterThanOrEqual(0.72, $result->matchScore);
    }

    public function test_passport_is_recognized_but_never_automatically_verified_even_with_a_perfect_match(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "PASSPORT\nJUAN CRUZ\nADDRESS 123 RIZAL STREET QUEZON CITY",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Quezon City'],
        );

        $this->assertSame('Passport', $result->documentType);
        $this->assertSame(1.0, $result->matchScore);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $result->status);
        $this->assertStringContainsString('not supported for automatic verification', implode(' ', $result->reasons));
    }

    public function test_proof_of_address_only_is_not_a_supported_identity_document(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "UTILITY BILL\nJUAN CRUZ\nADDRESS 123 RIZAL STREET QUEZON CITY",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Quezon City'],
        );

        $this->assertSame('Proof of Address', $result->documentType);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $result->status);
        $this->assertStringContainsString('not supported for automatic verification', implode(' ', $result->reasons));
    }

    public function test_supported_id_without_residential_address_requests_resubmission(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nPHILIPPINE IDENTIFICATION CARD\nJUAN CRUZ\nIDENTIFICATION NUMBER 123456789012",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Quezon City'],
        );

        $this->assertSame('Philippine National ID', $result->documentType);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $result->status);
        $this->assertStringContainsString('does not contain enough residential address information', implode(' ', $result->reasons));
    }

    public function test_generic_government_id_is_not_assumed_to_show_an_address(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nIDENTIFICATION CARD\nJUAN CRUZ\n123 RIZAL STREET QUEZON CITY",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Quezon City'],
        );

        $this->assertSame('Government ID', $result->documentType);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $result->status);
    }

    public function test_lto_drivers_license_with_name_and_address_is_supported(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nLAND TRANSPORTATION OFFICE\nDRIVER'S LICENSE\nJUAN CRUZ\nADDRESS 123 RIZAL STREET QUEZON CITY",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Quezon City'],
        );

        $this->assertSame('Philippine Driver License', $result->documentType);
        $this->assertSame(DocumentVerificationStatus::Verified, $result->status);
    }

    public function test_wrong_last_name_cannot_be_compensated_by_matching_address(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "PHILIPPINE IDENTIFICATION CARD\nJUAN SANTOS\n123 RIZAL STREET QUEZON CITY",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Quezon City'],
        );

        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $result->status);
        $this->assertStringContainsString('name extracted from the document does not consistently match', implode(' ', $result->reasons));
    }

    public function test_mismatched_or_unclear_text_requests_a_replacement(): void
    {
        $service = app(DocumentVerificationService::class);
        $mismatch = $service->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nIDENTIFICATION CARD\nMARIA SANTOS\nCEBU CITY",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street, Quezon City']
        );
        $unclear = $service->crossReference('blur', [
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => 'Quezon City',
        ]);

        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $mismatch->status);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $unclear->status);
        $this->assertNotEmpty($mismatch->reasons);
    }

    public function test_matching_name_and_document_type_cannot_override_a_conflicting_address(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nIDENTIFICATION CARD\nTEST APPLICANT\n4489 FRANCISCO STREET SANTA MESA MANILA",
            [
                'first_name' => 'Test',
                'last_name' => 'Applicant',
                'address' => '4489 Tes Test Street',
            ]
        );

        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $result->status);
        $this->assertStringContainsString(
            'address extracted from the document does not consistently match',
            implode(' ', $result->reasons)
        );
    }

    public function test_address_requires_matching_numbers_and_does_not_use_substring_collisions(): void
    {
        $service = app(DocumentVerificationService::class);
        $differentNumber = $service->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nIDENTIFICATION CARD\nTEST APPLICANT\n456 RIZAL STREET QUEZON CITY",
            ['first_name' => 'Test', 'last_name' => 'Applicant', 'address' => '123 Rizal Street Quezon City']
        );
        $substringCollision = $service->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nIDENTIFICATION CARD\nTEST APPLICANT\n321 TESTAMENT AVENUE OTHERVILLE",
            ['first_name' => 'Test', 'last_name' => 'Applicant', 'address' => '321 Tes Road Sampleville']
        );

        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $differentNumber->status);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $substringCollision->status);
    }

    public function test_equivalent_philippine_address_abbreviations_still_match(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nPHILIPPINE IDENTIFICATION CARD\nTEST APPLICANT\n4489 V. FRANCISCO ST. STA. MESA\nBARANGAY 590 CITY OF MANILA PHL",
            [
                'first_name' => 'Test',
                'last_name' => 'Applicant',
                'address' => '4489 V Francisco Street Santa Mesa, City of Manila',
            ]
        );

        $this->assertSame(DocumentVerificationStatus::Verified, $result->status);
    }

    public function test_ncr_and_national_capital_region_are_equivalent_without_weakening_street_matching(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "PHILIPPINE IDENTIFICATION CARD\nJUAN CRUZ\n123 RIZAL ST CITY OF MANILA NCR",
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Manila National Capital Region'],
        );

        $this->assertSame(DocumentVerificationStatus::Verified, $result->status);
    }

    public function test_missing_district_is_allowed_when_house_street_and_city_match(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nPHILIPPINE IDENTIFICATION CARD\nTEST APPLICANT\n1200 LUNA STREET MANILA",
            [
                'first_name' => 'Test',
                'last_name' => 'Applicant',
                'address' => '1200 Luna Street Santa Cruz Manila',
            ]
        );

        $this->assertSame(DocumentVerificationStatus::Verified, $result->status);
    }

    public function test_structured_address_allows_an_omitted_barangay_and_district_when_core_address_matches(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nPHILIPPINE IDENTIFICATION CARD\nTEST APPLICANT\n4489 V FRANCISCO ST CITY OF MANILA",
            $this->structuredOcrApplicant(),
        );

        $this->assertSame(DocumentVerificationStatus::Verified, $result->status);
    }

    public function test_structured_address_rejects_an_explicitly_conflicting_barangay(): void
    {
        $result = app(DocumentVerificationService::class)->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nIDENTIFICATION CARD\nTEST APPLICANT\n4489 V FRANCISCO ST CITY OF MANILA\nBARANGAY 591",
            $this->structuredOcrApplicant(),
        );

        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $result->status);
        $this->assertStringContainsString(
            'address extracted from the document does not consistently match',
            implode(' ', $result->reasons),
        );
    }

    public function test_matching_house_and_city_cannot_override_a_different_street_or_district(): void
    {
        $service = app(DocumentVerificationService::class);
        $differentStreet = $service->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nIDENTIFICATION CARD\nTEST APPLICANT\n1200 MABINI STREET SANTA CRUZ MANILA",
            ['first_name' => 'Test', 'last_name' => 'Applicant', 'address' => '1200 Luna Street Santa Cruz Manila']
        );
        $differentDistrict = $service->crossReference(
            "REPUBLIC OF THE PHILIPPINES\nIDENTIFICATION CARD\nTEST APPLICANT\n1200 LUNA STREET SANTA ANA MANILA",
            ['first_name' => 'Test', 'last_name' => 'Applicant', 'address' => '1200 Luna Street Santa Cruz Manila']
        );

        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $differentStreet->status);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $differentDistrict->status);
    }

    public function test_missing_ocr_configuration_never_mocks_a_success(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('adoption-documents/id.jpg', 'image bytes');
        $this->app->instance(GoogleAccessTokenProvider::class, new class implements GoogleAccessTokenProvider
        {
            public function accessToken(): string
            {
                throw new \RuntimeException('ADC is not configured for this test.');
            }
        });

        $result = app(DocumentVerificationService::class)->verify(
            'local',
            'adoption-documents/id.jpg',
            'image/jpeg',
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => 'Quezon City']
        );

        $this->assertSame(DocumentVerificationStatus::ManualReview, $result->status);
        $this->assertFalse($result->isVerified());
    }

    public function test_ocr_health_checks_configured_credentials_and_live_vision_without_printing_secrets(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('credentials.json', '{}');
        config()->set('document_verification.google_application_credentials', Storage::disk('local')->path('credentials.json'));
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [[]]])]);

        $this->artisan('ocr:health')->assertSuccessful()->expectsOutput('Result: HEALTHY');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer adc-test-token')
            && $request->data()['requests'][0]['features'] === [['type' => 'DOCUMENT_TEXT_DETECTION']]);
    }

    public function test_ocr_health_fails_without_configured_credentials_and_does_not_call_vision(): void
    {
        config()->set('document_verification.google_application_credentials', null);
        Http::fake();

        $this->artisan('ocr:health')->assertFailed()->expectsOutput('Result: CONFIGURATION OR PROVIDER ERROR');
        Http::assertNothingSent();
    }

    public function test_provider_request_uses_document_text_detection_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('adoption-documents/id.jpg', 'image bytes');
        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "REPUBLIC OF THE PHILIPPINES\nPHILIPPINE IDENTIFICATION CARD\nJUAN CRUZ\n123 RIZAL STREET QUEZON CITY",
                    ],
                ]],
            ]),
        ]);

        $result = app(DocumentVerificationService::class)->verify(
            'local',
            'adoption-documents/id.jpg',
            'image/jpeg',
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Quezon City']
        );

        $this->assertSame(DocumentVerificationStatus::Verified, $result->status);
        Http::assertSent(function ($request) {
            $features = $request->data()['requests'][0]['features'];

            return $features === [['type' => 'DOCUMENT_TEXT_DETECTION']]
                && ! str_contains(json_encode($request->data()), 'FACE_DETECTION')
                && $request->hasHeader('Authorization', 'Bearer adc-test-token')
                && ! $request->hasHeader('X-Goog-Api-Key');
        });
    }

    public function test_provider_billing_error_is_actionable_and_does_not_retry_or_leak_the_key(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('adoption-documents/id.jpg', 'image bytes');
        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 403,
                    'status' => 'PERMISSION_DENIED',
                    'message' => 'This API method requires billing to be enabled.',
                ],
            ], 403),
        ]);

        $result = app(DocumentVerificationService::class)->verify(
            'local',
            'adoption-documents/id.jpg',
            'image/jpeg',
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => 'Quezon City']
        );

        $this->assertSame(DocumentVerificationStatus::ManualReview, $result->status);
        $this->assertNull($result->extractedText);
        $this->assertStringContainsString('billing is not enabled', implode(' ', $result->reasons));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer adc-test-token')
            && ! $request->hasHeader('X-Goog-Api-Key'));
    }

    public function test_pdf_is_sent_directly_to_google_file_ocr_without_imagick(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('adoption-documents/id.pdf', '%PDF test bytes');
        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'responses' => [[
                    'responses' => [[
                        'fullTextAnnotation' => [
                            'text' => "REPUBLIC OF THE PHILIPPINES\nPHILIPPINE IDENTIFICATION CARD\nJUAN CRUZ\n123 RIZAL STREET QUEZON CITY",
                        ],
                    ]],
                ]],
            ]),
        ]);

        $result = app(DocumentVerificationService::class)->verify(
            'local',
            'adoption-documents/id.pdf',
            'application/pdf',
            ['first_name' => 'Juan', 'last_name' => 'Cruz', 'address' => '123 Rizal Street Quezon City']
        );

        $this->assertSame(DocumentVerificationStatus::Verified, $result->status);
        Http::assertSent(function ($request) {
            $data = $request->data()['requests'][0];

            return str_ends_with($request->url(), '/v1/files:annotate')
                && $data['inputConfig']['mimeType'] === 'application/pdf'
                && $data['features'] === [['type' => 'DOCUMENT_TEXT_DETECTION']]
                && $data['pages'] === [1];
        });
    }

    public function test_verified_submission_is_stored_privately_and_waits_for_staff_review(): void
    {
        Mail::fake();
        Storage::fake('local');
        [$adopter, $pet] = $this->adopterAndPet();
        $verifier = \Mockery::mock(DocumentVerificationService::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new DocumentVerificationResult(
            DocumentVerificationStatus::Verified,
            'JUAN CRUZ QUEZON CITY IDENTIFICATION CARD',
            null,
            0.94,
            [],
            'Government ID',
        ));
        $this->app->instance(DocumentVerificationService::class, $verifier);

        $this->actingAs($adopter)->post(route('application.submit'), [
            'pet_id' => $pet->id,
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'email' => 'juan@example.test',
            'phone_number' => '09171234567',
            ...$this->structuredAddressPayload(),
            'motivation_statement' => 'I can provide a safe and permanent home.',
            'housing_type' => 'Apartment / Condo',
            'physical_activity_level' => 'Moderate (Daily walks, occasional play)',
            'time_availability' => '4-8 hours/day',
            'prior_pet_experience' => 'Have owned pets in the past',
            'household_composition' => 'Living with adults only',
            'monthly_income_range' => 'Above ₱80,000',
            'agreed_to_terms' => '1',
            'document' => UploadedFile::fake()->image('identity.jpg'),
        ])->assertRedirect(route('application.index'));

        $application = AdoptionApplication::sole();
        $this->assertSame(ApplicationStatus::Pending, $application->status);
        $this->assertSame(DocumentVerificationStatus::Verified, $application->document_verification_status);
        $this->assertSame('local', $application->document_disk);
        $this->assertSame('Juan', $application->applicant_first_name);
        $this->assertSame('JUAN CRUZ QUEZON CITY IDENTIFICATION CARD', $application->ocr_extracted_text);
        $this->assertNotSame(
            'JUAN CRUZ QUEZON CITY IDENTIFICATION CARD',
            DB::table('adoption_applications')->where('id', $application->id)->value('ocr_extracted_text')
        );
        Storage::disk('local')->assertExists($application->document_path);
    }

    public function test_adopter_can_replace_a_flagged_document_and_verified_result_proceeds(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::disk('local')->put('adoption-documents/old.jpg', 'old sensitive document');
        [$adopter, $pet] = $this->adopterAndPet();
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'applicant_first_name' => 'Juan',
            'applicant_last_name' => 'Cruz',
            ...$this->applicationAddressAttributes(),
            'status' => ApplicationStatus::DocumentFlagged->value,
            'document_path' => 'adoption-documents/old.jpg',
            'document_disk' => 'local',
            'document_verification_status' => DocumentVerificationStatus::NeedsResubmission->value,
        ]);

        $verifier = \Mockery::mock(DocumentVerificationService::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new DocumentVerificationResult(
            DocumentVerificationStatus::Verified,
            'JUAN CRUZ QUEZON CITY REPUBLIC OF THE PHILIPPINES',
            null,
            0.95,
            [],
            'Government ID',
        ));
        $this->app->instance(DocumentVerificationService::class, $verifier);

        $this->actingAs($adopter)
            ->post(route('applications.document.replace', $application), [
                'document' => UploadedFile::fake()->image('replacement.jpg'),
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(DocumentVerificationStatus::Verified, $application->document_verification_status);
        $this->assertSame(ApplicationStatus::Pending, $application->status);
        $this->assertSame(1, $application->document_reupload_count);
        Storage::disk('local')->assertMissing('adoption-documents/old.jpg');
        Storage::disk('local')->assertExists($application->document_path);
    }

    public function test_adopter_can_submit_only_one_follow_up_document(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::disk('local')->put('adoption-documents/original.jpg', 'original private document');
        [$adopter, $pet] = $this->adopterAndPet();
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'applicant_first_name' => 'Juan',
            'applicant_last_name' => 'Cruz',
            ...$this->applicationAddressAttributes(),
            'status' => ApplicationStatus::DocumentFlagged->value,
            'document_path' => 'adoption-documents/original.jpg',
            'document_disk' => 'local',
            'document_verification_status' => DocumentVerificationStatus::NeedsResubmission->value,
            'document_reupload_count' => 0,
        ]);
        $verifier = \Mockery::mock(DocumentVerificationService::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new DocumentVerificationResult(
            DocumentVerificationStatus::NeedsResubmission,
            'JUAN CRUZ 999 DIFFERENT STREET MANILA IDENTIFICATION CARD',
            null,
            0.65,
            ['The address extracted from the document does not consistently match the application.'],
            'Government ID',
        ));
        $verifier->shouldReceive('crossReferenceBreakdown')->andReturn([
            'text_quality_pass' => true,
            'supported_document_type' => false,
            'first_name_pass' => true,
            'last_name_pass' => true,
            'address_pass' => false,
            'address_evidence' => true,
            'hard_address_conflict' => false,
        ]);
        $this->app->instance(DocumentVerificationService::class, $verifier);

        $this->actingAs($adopter)
            ->post(route('applications.document.replace', $application), [
                'document' => UploadedFile::fake()->image('follow-up.jpg'),
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(1, $application->document_reupload_count);
        $this->assertSame(DocumentVerificationStatus::NeedsResubmission, $application->document_verification_status);
        $this->assertFalse($application->canUploadReplacementDocument());

        $this->get(route('application.index'))
            ->assertOk()
            ->assertSee('Follow-up already submitted.')
            ->assertDontSee('replacement_document_'.$application->id, false);

        $currentPath = $application->document_path;
        $this->post(route('applications.document.replace', $application), [
            'document' => UploadedFile::fake()->image('blocked-second-follow-up.jpg'),
        ])->assertSessionHasErrors('document');

        $application->refresh();
        $this->assertSame(1, $application->document_reupload_count);
        $this->assertSame($currentPath, $application->document_path);

        $staff = User::create([
            'first_name' => 'Authorized',
            'last_name' => 'Reviewer',
            'email' => 'follow-up-reviewer@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Administrator->value,
            'email_verified_at' => now(),
        ]);
        $this->actingAs($staff)
            ->get(route('admin.applications.document-verification', $application))
            ->assertOk()
            ->assertDontSee('Request Follow-up Document')
            ->assertSee('one follow-up upload has already been used');
        $this->post(route('admin.applications.document-decision', $application), [
            'document_decision' => DocumentVerificationStatus::NeedsResubmission->value,
            'document_reason' => 'Please submit another replacement document.',
        ])->assertSessionHasErrors('document_decision');
    }

    public function test_staff_can_retry_a_manual_review_document_after_provider_recovery(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::disk('local')->put('adoption-documents/retry.jpg', 'private image bytes');
        [$adopter, $pet] = $this->adopterAndPet();
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'applicant_first_name' => 'Juan',
            'applicant_last_name' => 'Cruz',
            ...$this->applicationAddressAttributes(),
            'status' => ApplicationStatus::Pending->value,
            'document_path' => 'adoption-documents/retry.jpg',
            'document_disk' => 'local',
            'document_mime_type' => 'image/jpeg',
            'document_verification_status' => DocumentVerificationStatus::ManualReview->value,
            'document_verification_reasons' => ['Automatic OCR was unavailable.'],
        ]);
        $staff = User::create([
            'first_name' => 'Authorized',
            'last_name' => 'Staff',
            'email' => 'ocr-staff@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Administrator->value,
            'email_verified_at' => now(),
        ]);
        $verifier = \Mockery::mock(DocumentVerificationService::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new DocumentVerificationResult(
            DocumentVerificationStatus::Verified,
            'JUAN CRUZ QUEZON CITY IDENTIFICATION CARD',
            null,
            0.94,
            [],
            'Government ID',
        ));
        $this->app->instance(DocumentVerificationService::class, $verifier);

        $this->actingAs($staff)
            ->post(route('admin.applications.document-ocr-retry', $application))
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(DocumentVerificationStatus::Verified, $application->document_verification_status);
        $this->assertSame(ApplicationStatus::Pending, $application->status);
        $this->assertSame('JUAN CRUZ QUEZON CITY IDENTIFICATION CARD', $application->ocr_extracted_text);
        $this->assertNotNull($application->document_verified_at);
    }

    public function test_private_documents_are_available_to_staff_but_not_adopters(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('adoption-documents/private-id.jpg', 'private bytes');
        [$adopter, $pet] = $this->adopterAndPet();
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::UnderReview->value,
            'document_path' => 'adoption-documents/private-id.jpg',
            'document_disk' => 'local',
            'document_original_name' => 'identity.jpg',
            'document_mime_type' => 'image/jpeg',
        ]);
        $staff = User::create([
            'first_name' => 'Authorized',
            'last_name' => 'Staff',
            'email' => 'authorized@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Volunteer->value,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('admin.applications.document', $application))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->actingAs($adopter)
            ->get(route('admin.applications.document', $application))
            ->assertRedirect(route('access-denied'));
    }

    public function test_staff_verification_screen_shows_independent_gates_but_adopter_cannot_access_it(): void
    {
        [$adopter, $pet] = $this->adopterAndPet();
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'applicant_first_name' => 'Juan',
            'applicant_last_name' => 'Cruz',
            ...$this->applicationAddressAttributes(),
            'status' => ApplicationStatus::DocumentFlagged->value,
            'document_verification_status' => DocumentVerificationStatus::NeedsResubmission->value,
            'document_type' => 'Passport',
            'ocr_extracted_text' => "PASSPORT\nJUAN CRUZ\n123 RIZAL STREET QUEZON CITY",
        ]);
        $staff = User::create([
            'first_name' => 'Document',
            'last_name' => 'Reviewer',
            'email' => 'document-reviewer@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Administrator->value,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('admin.applications.document-verification', $application))
            ->assertOk()
            ->assertSee('Automatic OCR Cross-Reference (Current Policy)')
            ->assertSee('Supported ID type')
            ->assertSee('First name')
            ->assertSee('Residential address');

        $this->actingAs($adopter)
            ->get(route('admin.applications.document-verification', $application))
            ->assertRedirect(route('access-denied'));
    }

    private function adopterAndPet(): array
    {
        $adopter = User::create([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'email' => 'juan@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
        ]);
        $pet = Pet::create([
            'name' => 'OCR Pet',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);

        $this->completeMatchingProfile($adopter);
        $this->completePetAssessment($pet);

        return [$adopter, $pet];
    }

    private function structuredAddressPayload(): array
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

    private function applicationAddressAttributes(): array
    {
        return [
            'applicant_region' => 'National Capital Region (NCR)',
            'applicant_province' => null,
            'applicant_city_municipality' => 'City of Manila',
            'applicant_barangay' => 'Barangay 590',
            'applicant_street_address' => '4489 V. Francisco St. Sta. Mesa',
            'applicant_zip_code' => '1016',
        ];
    }

    private function structuredOcrApplicant(): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'address' => '4489 V. Francisco St. Sta. Mesa, Barangay 590, City of Manila, National Capital Region (NCR), 1016',
            'address_components' => [
                'region' => 'National Capital Region (NCR)',
                'province' => null,
                'city_municipality' => 'City of Manila',
                'barangay' => 'Barangay 590',
                'street_address' => '4489 V. Francisco St. Sta. Mesa',
                'zip_code' => '1016',
            ],
        ];
    }
}
