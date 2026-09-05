<?php

namespace Tests\Feature;

use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Models\User;
use App\Services\DocumentVerificationService;
use App\ValueObjects\DocumentVerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssessmentKnnIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cbarq_uses_one_to_five_and_stores_higher_temperament_as_calmer(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator->value]);
        $calmDog = $this->pet('Calm Dog');

        $this->actingAs($admin)
            ->post(route('admin.assessments.store', $calmDog), $this->validDogAssessment())
            ->assertRedirect(route('admin.assessments.record'));

        $calmRecord = $calmDog->assessmentRecords()->sole();
        $this->assertSame(5.0, (float) $calmRecord->energy_level);
        $this->assertSame(5.0, (float) $calmRecord->trainability);
        $this->assertSame(5.0, (float) $calmRecord->independence);
        $this->assertSame(5.0, (float) $calmRecord->temperament);

        $fearfulDog = $this->pet('Fearful Dog');
        $fearOverrides = array_fill_keys([
            'sf1', 'sf2', 'sf3', 'sf4', 'sf5',
            'nf1', 'nf2', 'nf3', 'nf4', 'nf5', 'nf6',
        ], 5);

        $this->actingAs($admin)
            ->post(route('admin.assessments.store', $fearfulDog), $this->validDogAssessment($fearOverrides))
            ->assertRedirect(route('admin.assessments.record'));

        $this->assertSame(1.0, (float) $fearfulDog->assessmentRecords()->sole()->temperament);
    }

    public function test_assessment_rejects_missing_and_out_of_range_answers(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator->value]);
        $dog = $this->pet('Tampered Assessment Dog');
        $payload = $this->validDogAssessment(['t5' => 6]);
        unset($payload['e1']);

        $this->actingAs($admin)
            ->from(route('admin.assessments.create', $dog))
            ->post(route('admin.assessments.store', $dog), $payload)
            ->assertRedirect(route('admin.assessments.create', $dog))
            ->assertSessionHasErrors(['e1', 't5']);

        $this->assertDatabaseCount('assessment_records', 0);
    }

    public function test_recommendation_persists_six_variables_and_ranks_calm_pet_for_children(): void
    {
        $adopter = User::factory()->create(['role' => Role::Adopter->value]);
        $calm = $this->eligiblePet('Calm Match', 5);
        $fearful = $this->eligiblePet('Fearful Match', 1);

        $response = $this->actingAs($adopter)->post(route('recommendation.start'), $this->profileInputs());

        $response->assertOk()->assertViewHas('matches', function ($matches) use ($calm, $fearful): bool {
            return $matches->pluck('pet.id')->all() === [$calm->id, $fearful->id]
                && abs($matches[0]['distance'] - 0.0) < 0.000001
                && abs($matches[1]['distance'] - 4.0) < 0.000001;
        });

        $this->assertDatabaseHas('adopter_profiles', [
            'user_id' => $adopter->id,
            ...$this->profileInputs(),
        ]);
    }

    public function test_application_uses_six_dimension_distance_and_updates_reusable_profile(): void
    {
        Mail::fake();
        Storage::fake('local');
        $adopter = User::factory()->create(['role' => Role::Adopter->value]);
        $pet = $this->eligiblePet('Application Match', 5);
        $verifier = \Mockery::mock(DocumentVerificationService::class);
        $verifier->shouldReceive('verify')->once()->andReturn(new DocumentVerificationResult(
            DocumentVerificationStatus::Verified,
            'QA ADOPTER IDENTIFICATION CARD 4489 FRANCISCO STREET MANILA',
            null,
            1.0,
            [],
            'Government ID',
        ));
        $this->app->instance(DocumentVerificationService::class, $verifier);

        $this->actingAs($adopter)->post(route('application.submit'), [
            'pet_id' => $pet->id,
            'first_name' => $adopter->first_name,
            'last_name' => $adopter->last_name,
            'email' => $adopter->email,
            'phone_number' => '09171234567',
            'region_code' => '1300000000',
            'province_code' => '__direct__',
            'city_municipality_code' => '1380600000',
            'barangay_code' => '1380606197',
            'street_address' => '4489 V. Francisco St. Sta. Mesa',
            'zip_code' => '1016',
            'motivation_statement' => 'I can provide a safe and permanent home.',
            ...$this->profileInputs(),
            'agreed_to_animal_welfare_act' => '1',
            'document' => UploadedFile::fake()->image('identity.jpg'),
        ])->assertRedirect(route('application.index'));

        $application = AdoptionApplication::sole();
        $this->assertSame(0.0, (float) $application->knn_score);
        $this->assertSame($this->profileInputs(), AdopterProfile::sole()->only(array_keys($this->profileInputs())));
    }

    /** @param array<string, int> $overrides */
    private function validDogAssessment(array $overrides = []): array
    {
        $payload = array_fill_keys([
            'e1', 'e2', 'e3',
            't1', 't2', 't3', 't4', 't8',
        ], 5);
        $payload += array_fill_keys([
            't5', 't6', 't7',
            'i1', 'i2', 'i3', 'i4', 'i5', 'i6',
            'sf1', 'sf2', 'sf3', 'sf4', 'sf5',
            'nf1', 'nf2', 'nf3', 'nf4', 'nf5', 'nf6',
        ], 1);
        $payload['medical_needs'] = 5;

        return array_replace($payload, $overrides);
    }

    /** @return array<string, string> */
    private function profileInputs(): array
    {
        return [
            'physical_activity_level' => 'High (Active, jogging, hiking)',
            'time_availability' => 'Less than 2 hours/day',
            'prior_pet_experience' => 'First-time owner',
            'housing_type' => 'Single Family Home (Fenced Yard)',
            'household_composition' => 'Living with children (under 12)',
            'monthly_income_range' => 'Above ₱80,000',
        ];
    }

    private function pet(string $name): Pet
    {
        return Pet::create([
            'name' => $name,
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
            'physical_size' => 'Extra Large',
        ]);
    }

    private function eligiblePet(string $name, float $temperament): Pet
    {
        $pet = $this->pet($name);
        $pet->update([
            'energy_level' => 5,
            'trainability' => 5,
            'independence' => 5,
            'temperament' => $temperament,
            'medical_needs' => 5,
            'assessment_count' => 3,
        ]);

        foreach (range(1, 3) as $assessment) {
            AssessmentRecord::create([
                'pet_id' => $pet->id,
                'energy_level' => 5,
                'trainability' => 5,
                'independence' => 5,
                'temperament' => $temperament,
            ]);
        }

        return $pet;
    }
}
