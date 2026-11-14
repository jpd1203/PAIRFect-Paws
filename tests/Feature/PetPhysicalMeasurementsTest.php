<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\PetController;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Rules\PositivePetMeasurement;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicationMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class PetPhysicalMeasurementsTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
    }

    private function newPetData(array $extra = []): array
    {
        return array_replace(['name' => 'Synthetic Measured Pet', 'species' => 'Dog', 'status' => 'Assessing', 'weight_kg' => '12.500'], $extra);
    }

    private function editData(Pet $pet, array $extra = []): array
    {
        return array_replace(['name' => $pet->name, 'species' => $pet->species->value, 'status' => $pet->availability_status->value, 'version' => $pet->version], $extra);
    }

    public static function staffRoles(): array
    {
        return [['Administrator'], ['Volunteer']];
    }

    #[DataProvider('staffRoles')]
    public function test_authorized_staff_create_and_update_precise_fixed_unit_measurements(string $role): void
    {
        $staff = $this->matchingUser(Role::from($role));
        $this->actingAs($staff)->post(route('admin.animals.store'), $this->newPetData(['height_cm' => '38.125', 'photo' => UploadedFile::fake()->image('synthetic.jpg')]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.animals.index'));
        $pet = Pet::sole();
        $this->assertSame('12.500', $pet->weight_kg);
        $this->assertSame('38.125', $pet->height_cm);
        $this->assertNull($pet->physical_size);
        Storage::disk('public')->assertExists($pet->photo_path);
        $photo = $pet->photo_path;
        $this->put(route('admin.pets.update', $pet), $this->editData($pet, ['weight_kg' => '13.375', 'height_cm' => '39.001']))->assertSessionHasNoErrors();
        $pet->refresh();
        $this->assertSame('13.375', $pet->weight_kg);
        $this->assertSame('39.001', $pet->height_cm);
        $this->assertSame(2, $pet->version);
        $this->assertSame($photo, $pet->photo_path);
        $this->assertNull($pet->physical_size);
    }

    public function test_new_staff_pet_requires_weight_but_never_requires_height_or_matching_size(): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator));
        foreach ([null, ''] as $missing) {
            $this->post(route('admin.pets.store'), $this->newPetData(['weight_kg' => $missing]))->assertSessionHasErrors('weight_kg');
        }
        $data = $this->newPetData();
        unset($data['weight_kg']);
        $this->post(route('admin.pets.store'), $data)->assertSessionHasErrors('weight_kg');
        $this->assertDatabaseCount('pets', 0);
        $this->post(route('admin.pets.store'), $this->newPetData())->assertSessionHasNoErrors();
        $pet = Pet::sole();
        $this->assertNull($pet->height_cm);
        $this->assertNull($pet->physical_size);
    }

    public static function invalidMeasurements(): array
    {
        $values = ['zero' => '0', 'negative' => '-1.2', 'text' => 'many', 'units' => '12 kg', 'pounds' => '25 lb',
            'comma' => '12,5', 'NaN' => 'NaN', 'infinity' => 'INF', 'overflow' => '1e999', 'scientific' => '1e2',
            'precision' => '1.1234', 'too_small' => '0.0001', 'too_wide' => '10000000', 'array' => ['12'], 'boolean' => true];
        $cases = [];
        foreach (['weight_kg', 'height_cm'] as $field) {
            foreach ($values as $label => $value) {
                $cases[$field.'_'.$label] = [$field, $value];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidMeasurements')]
    public function test_invalid_measurements_are_rejected_on_creation_and_update_without_partial_changes(string $field, mixed $value): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->post(route('admin.animals.store'), $this->newPetData([$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('pets', 0);
        $pet = Pet::create(['name' => 'Historical Pet', 'species' => 'Dog', 'availability_status' => 'Assessing', 'physical_size' => 'Medium', 'weight_kg' => '9.125', 'height_cm' => '25.5'])->fresh();
        $before = $pet->getAttributes();
        $this->put(route('admin.animals.update', $pet), $this->editData($pet, [$field => $value]))->assertSessionHasErrors($field);
        $this->assertSame($before, $pet->fresh()->getAttributes());
    }

    public static function roundTrips(): array
    {
        return [['0.001', '0.001'], ['9999999.999', '9999999.999'], [1, '1.000'], [12.5, '12.500'], ['001.200', '1.200']];
    }

    #[DataProvider('roundTrips')]
    public function test_positive_decimal_values_round_trip_without_float_model_casts(mixed $value, string $expected): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator))->post(route('admin.animals.store'), $this->newPetData(['weight_kg' => $value, 'height_cm' => $value]))->assertSessionHasNoErrors();
        $pet = Pet::sole();
        $this->assertSame($expected, $pet->weight_kg);
        $this->assertSame($expected, $pet->height_cm);
        $this->assertSame($expected, $pet->toArray()['weight_kg']);
    }

    public function test_legacy_unknowns_omitted_updates_and_explicit_blanks_have_distinct_behavior(): void
    {
        $pet = Pet::withoutEvents(fn () => Pet::create(['name' => 'Legacy Unknown', 'species' => 'Cat', 'physical_size' => 'Extra Small', 'availability_status' => 'Assessing']))->fresh();
        $this->assertNull($pet->weight_kg);
        $this->assertNull($pet->height_cm);
        $this->assertSame('Not recorded', $pet->weight_display);
        $this->actingAs($this->matchingUser(Role::Administrator))->put(route('admin.animals.update', $pet), $this->editData($pet, ['name' => 'Unrelated correction']))->assertSessionHasNoErrors();
        $pet->refresh();
        $this->assertNull($pet->weight_kg);
        $this->assertNull($pet->height_cm);
        $this->assertSame('Extra Small', $pet->physical_size);
        $this->assertNull($pet->legacy_physical_size); // Preserve the exact preexisting snapshot, including null.
        $this->assertSame('legacy_unclassified', $pet->size_classification_status);
        $this->assertFalse($pet->hasClassifiedPhysicalSize());
        $this->put(route('admin.animals.update', $pet), $this->editData($pet, ['weight_kg' => '3.123', 'height_cm' => '22.001']))->assertSessionHasNoErrors();
        $pet->refresh();
        $this->put(route('admin.animals.update', $pet), $this->editData($pet, ['description' => 'Unrelated story edit']))->assertSessionHasNoErrors();
        $pet->refresh();
        $this->assertSame('3.123', $pet->weight_kg);
        $this->assertSame('22.001', $pet->height_cm);
        $this->put(route('admin.animals.update', $pet), $this->editData($pet, ['weight_kg' => '', 'height_cm' => '']))->assertSessionHasNoErrors();
        $this->assertNull($pet->fresh()->weight_kg);
        $this->assertNull($pet->fresh()->height_cm);
    }

    public static function speciesAndSizes(): array
    {
        $cases = [];
        foreach (['Dog', 'Cat'] as $species) {
            foreach (['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large'] as $size) {
                $cases[] = [$species, $size];
            }
        }

        return $cases;
    }

    #[DataProvider('speciesAndSizes')]
    public function test_both_species_ignore_manual_size_and_use_weight_classification(string $species, string $size): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator))->post(route('admin.animals.store'), $this->newPetData(['species' => $species, 'physical_size' => $size]))->assertSessionHasNoErrors();
        $pet = Pet::sole();
        $this->put(route('admin.animals.update', $pet), $this->editData($pet, ['weight_kg' => '1.125', 'height_cm' => '20']))->assertSessionHasNoErrors();
        $this->assertNull($pet->fresh()->physical_size); // Unknown age cannot be made adult by a forged label.
        $this->assertSame(['Extra Small' => 1.0, 'Small' => 2.0, 'Medium' => 3.0, 'Large' => 4.0, 'Extra Large' => 5.0], config('matching.size_levels'));
    }

    public static function deniedActors(): array
    {
        return [['Adopter', true, true], ['Volunteer', false, true], ['Administrator', false, true], ['Volunteer', true, false]];
    }

    #[DataProvider('deniedActors')]
    public function test_unauthorized_users_cannot_write_measurements(string $role, bool $active, bool $verified): void
    {
        $actor = $this->matchingUser(Role::from($role));
        $actor->forceFill(['is_active' => $active, 'email_verified_at' => $verified ? now() : null])->save();
        $pet = Pet::create(['name' => 'Protected Pet', 'species' => 'Cat'])->fresh();
        $before = $pet->getAttributes();
        $this->actingAs($actor)->post(route('admin.animals.store'), $this->newPetData())->assertRedirect();
        $this->actingAs($actor)->put(route('admin.pets.update', $pet), $this->editData($pet, ['weight_kg' => '1']))->assertRedirect();
        $this->assertSame($before, $pet->fresh()->getAttributes());
        $this->assertDatabaseCount('pets', 1);
    }

    public function test_guests_and_stale_authenticated_staff_objects_cannot_bypass_write_authorization(): void
    {
        $pet = Pet::create(['name' => 'Protected Pet', 'species' => 'Dog'])->fresh();
        $this->post(route('admin.animals.store'), $this->newPetData())->assertRedirect(route('login'));
        $this->put(route('admin.animals.update', $pet), $this->editData($pet, ['weight_kg' => '1']))->assertRedirect(route('login'));
        $staff = $this->matchingUser(Role::Administrator);
        DB::table('users')->where('id', $staff->id)->update(['is_active' => false]);
        $this->assertTrue($staff->is_active);
        $request = Request::create('/admin/animals', 'POST', $this->newPetData());
        $request->setUserResolver(fn () => $staff);
        try {
            app(PetController::class)->store($request);
            $this->fail('Fresh database staff authority must be checked.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertNull($pet->fresh()->weight_kg);
    }

    public function test_stale_edit_and_reservation_rules_remain_enforced(): void
    {
        $pet = Pet::create(['name' => 'Measured Pet', 'species' => 'Dog', 'weight_kg' => '12.5', 'physical_size' => 'Small'])->fresh();
        $this->actingAs($this->matchingUser(Role::Administrator));
        $data = $this->editData($pet, ['weight_kg' => '13']);
        $this->put(route('admin.animals.update', $pet), $data)->assertSessionHasNoErrors();
        $this->put(route('admin.animals.update', $pet), $data)->assertSessionHasErrors('concurrency');
        $pet->refresh();
        $this->assertSame('13.000', $pet->weight_kg);
        $this->put(route('admin.animals.update', $pet), $this->editData($pet, ['status' => 'Soft-Reserved', 'weight_kg' => '14']))->assertSessionHasErrors('status');
        $this->assertSame('13.000', $pet->fresh()->weight_kg);
    }

    public function test_profiles_modals_and_all_staff_forms_render_measurements_and_unknowns(): void
    {
        $pet = $this->matchingPet(['weight_kg' => '12.5', 'height_cm' => '38', 'physical_size' => 'Medium']);
        $this->get(route('pets.show', $pet))->assertOk()->assertSee('12.5 kg')->assertSee('38 cm')->assertSee('Medium');
        $this->get(route('pets.modal', $pet))->assertOk()->assertSee('12.5 kg')->assertSee('38 cm');
        $this->get(route('pets.index'))->assertOk()->assertSee($pet->name);
        $pet->update(['weight_kg' => null, 'height_cm' => null]);
        $this->get(route('pets.modal', $pet))->assertSee('Not recorded')->assertDontSee('0 kg')->assertDontSee('0 cm');
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->get(route('admin.animals.index'))->assertOk()->assertSee('Weight (kg)')->assertSee('Height (cm, optional)')
            ->assertSee('Dogs must be at least 24 months')->assertSee('cats at least 12 months')->assertSee('shelter approval pending');
        $this->get(route('admin.pets.create'))->assertOk()->assertSee('name="weight_kg"', false)->assertDontSee('name="physical_size"', false);
        $this->get(route('admin.pets.edit', $pet))->assertOk()->assertSee('name="height_cm"', false)->assertDontSee('name="physical_size"', false);
    }

    public function test_measurement_only_http_edit_preserves_matching_scores_fingerprints_eligibility_and_order(): void
    {
        $profile = $this->matchingAdopter();
        $pet = $this->matchingPet(['physical_size' => 'Medium']);
        $this->matchingPet(['physical_size' => 'Small']);
        $this->matchingPet(['physical_size' => 'Large']);
        $matcher = app(KnnRecommendationService::class);
        $before = $matcher->calculateMatch($profile, $pet)->toArray();
        $order = $matcher->recommendPets($profile, includeIneligible: true)->pluck('pet.id')->all();
        $application = AdoptionApplication::create(['user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => 'Pending']);
        app(ApplicationMatchService::class)->refresh($application);
        $stored = $application->fresh()->getAttributes();
        $observations = $pet->assessmentRecords->map->getAttributes()->all();
        $this->travelTo(now()->addHour());
        $this->actingAs($this->matchingUser(Role::Administrator))->put(route('admin.animals.update', $pet), $this->editData($pet, ['weight_kg' => '13.125', 'height_cm' => '41.375']))->assertSessionHasNoErrors();
        $pet->refresh()->load('assessmentRecords');
        $this->assertSame($before, $matcher->calculateMatch($profile, $pet)->toArray());
        $this->assertSame($order, $matcher->recommendPets($profile, includeIneligible: true)->pluck('pet.id')->all());
        $this->assertSame($stored, $application->fresh()->getAttributes());
        $this->assertSame($observations, $pet->assessmentRecords->map->getAttributes()->all());
        $this->assertCount(6, $before['pet_vector']);
        $this->assertSame(3.0, $before['pet_vector']['physical_size']);
    }

    public function test_measurements_do_not_replace_size_eligibility_or_become_assessment_inputs(): void
    {
        $pet = $this->matchingPet(['physical_size' => null, 'weight_kg' => '15', 'height_cm' => '40', 'age' => null], 0);
        $this->actingAs($this->matchingUser(Role::Administrator))->post(route('admin.assessments.store', $pet), [
            'responses' => $this->behaviorResponses('dog', 2), 'weight_kg' => '99', 'height_cm' => '99', 'physical_size' => 'Large',
        ])->assertSessionHasNoErrors();
        $pet->refresh();
        $this->assertSame('15.000', $pet->weight_kg);
        $this->assertSame('40.000', $pet->height_cm);
        $this->assertNull($pet->physical_size);
        $this->assertFalse(app(KnnRecommendationService::class)->calculateMatch($this->matchingAdopter(), $pet)->eligible);
    }

    public function test_nonfinite_native_numbers_are_rejected_without_numeric_warnings(): void
    {
        foreach ([NAN, INF, -INF] as $value) {
            $validator = Validator::make(['weight_kg' => $value], ['weight_kg' => [new PositivePetMeasurement]]);
            $this->assertTrue($validator->fails());
        }
    }
}
