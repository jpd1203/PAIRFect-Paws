<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\User;
use App\Services\DocumentVerificationService;
use App\ValueObjects\DocumentVerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StructuredAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_endpoints_expose_the_ncr_direct_locality_hierarchy_from_the_local_dataset(): void
    {
        $this->getJson(route('locations.regions'))
            ->assertOk()
            ->assertJsonFragment([
                'code' => '1300000000',
                'name' => 'National Capital Region (NCR)',
            ]);

        $this->getJson(route('locations.provinces', [
            'region_code' => '1300000000',
        ]))
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('has_direct_localities', true);

        $this->getJson(route('locations.localities', [
            'region_code' => '1300000000',
            'province_code' => '__direct__',
        ]))
            ->assertOk()
            ->assertJsonFragment([
                'code' => '1380600000',
                'name' => 'City of Manila',
                'type' => 'city',
            ]);

        $this->getJson(route('locations.barangays', [
            'city_municipality_code' => '1380600000',
        ]))
            ->assertOk()
            ->assertJsonFragment([
                'code' => '1380606197',
                'name' => 'Barangay 590',
            ]);
    }

    public function test_registration_resolves_and_persists_canonical_structured_address_names(): void
    {
        $this->post(route('register.store'), [
            'first_name' => 'Josh',
            'last_name' => 'Oliver',
            'email' => 'josh.structured@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$this->ncrAddressPayload(),
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'josh.structured@example.test',
            'region' => 'National Capital Region (NCR)',
            'province' => null,
            'city_municipality' => 'City of Manila',
            'barangay' => 'Barangay 590',
            'street_address' => '4489 V. Francisco St. Sta. Mesa',
            'zip_code' => '1016',
        ]);

        $user = User::where('email', 'josh.structured@example.test')->sole();
        $this->assertSame(
            '4489 V. Francisco St. Sta. Mesa, Barangay 590, City of Manila, National Capital Region (NCR), 1016',
            $user->address,
        );
    }

    public function test_registration_rejects_a_province_that_does_not_belong_to_the_selected_region(): void
    {
        $payload = [
            'first_name' => 'Forged',
            'last_name' => 'Hierarchy',
            'email' => 'forged.address@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$this->ncrAddressPayload(),
            'province_code' => '0128000000',
        ];

        $this->from(route('register'))
            ->post(route('register.store'), $payload)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('province_code');

        $this->assertDatabaseMissing('users', [
            'email' => 'forged.address@example.test',
        ]);
    }

    public function test_application_persists_an_address_snapshot_and_passes_structured_address_data_to_ocr(): void
    {
        Mail::fake();
        Storage::fake('local');

        $adopter = User::create([
            'first_name' => 'Josh',
            'last_name' => 'Oliver',
            'email' => 'josh.application@example.test',
            'password' => bcrypt('password123'),
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
        ]);
        $pet = Pet::create([
            'name' => 'Structured Address Pet',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);

        $verifier = \Mockery::mock(DocumentVerificationService::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->withArgs(function (string $disk, string $path, string $mimeType, array $applicant): bool {
                $this->assertSame('local', $disk);
                $this->assertStringStartsWith('adoption-documents/', $path);
                $this->assertSame('image/jpeg', $mimeType);
                $this->assertSame('Josh', $applicant['first_name']);
                $this->assertSame('Oliver', $applicant['last_name']);
                $this->assertSame(
                    '4489 V. Francisco St. Sta. Mesa, Barangay 590, City of Manila, National Capital Region (NCR), 1016',
                    $applicant['address'],
                );
                $this->assertSame($this->canonicalAddressComponents(), $applicant['address_components']);

                return true;
            })
            ->andReturn(new DocumentVerificationResult(
                DocumentVerificationStatus::Verified,
                'JOSH OLIVER 4489 V FRANCISCO ST CITY OF MANILA IDENTIFICATION CARD',
                null,
                0.95,
                [],
                'Government ID',
            ));
        $this->app->instance(DocumentVerificationService::class, $verifier);

        $this->actingAs($adopter)->post(route('application.submit'), [
            'pet_id' => $pet->id,
            'first_name' => 'Josh',
            'last_name' => 'Oliver',
            'email' => 'josh.application@example.test',
            'phone_number' => '09171234567',
            ...$this->ncrAddressPayload(),
            'motivation_statement' => 'I can provide a safe and permanent home.',
            'housing_type' => 'Apartment / Condo',
            'physical_activity_level' => 'Moderate (Daily walks, occasional play)',
            'time_availability' => '4-8 hours/day',
            'prior_pet_experience' => 'Have owned pets in the past',
            'household_composition' => 'Living with adults only',
            'monthly_income_range' => 'Above PHP 80,000',
            'agreed_to_animal_welfare_act' => '1',
            'document' => UploadedFile::fake()->image('identity.jpg'),
        ])->assertRedirect(route('application.index'));

        $application = AdoptionApplication::sole();
        $this->assertSame(ApplicationStatus::UnderReview, $application->status);
        $this->assertSame(DocumentVerificationStatus::Verified, $application->document_verification_status);
        $this->assertSame('National Capital Region (NCR)', $application->applicant_region);
        $this->assertNull($application->applicant_province);
        $this->assertSame('City of Manila', $application->applicant_city_municipality);
        $this->assertSame('Barangay 590', $application->applicant_barangay);
        $this->assertSame('4489 V. Francisco St. Sta. Mesa', $application->applicant_street_address);
        $this->assertSame('1016', $application->applicant_zip_code);
        $this->assertSame($this->canonicalAddressComponents(), $application->address_components);
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool => $mail->hasTo($adopter->email)
            && $mail->subjectLine === "Application received for {$pet->name}"
        );
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool => $mail->hasTo($adopter->email)
            && $mail->subjectLine === "Document verified for application #{$application->id}"
        );
    }

    private function ncrAddressPayload(): array
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

    private function canonicalAddressComponents(): array
    {
        return [
            'region' => 'National Capital Region (NCR)',
            'province' => null,
            'city_municipality' => 'City of Manila',
            'barangay' => 'Barangay 590',
            'street_address' => '4489 V. Francisco St. Sta. Mesa',
            'zip_code' => '1016',
        ];
    }
}
