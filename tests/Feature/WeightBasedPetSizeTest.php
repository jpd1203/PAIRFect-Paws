<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicationMatchService;
use App\Services\PetSizeClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class WeightBasedPetSizeTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
    }

    private function edit(Pet $pet, array $changes = [])
    {
        return $this->put(route('admin.animals.update', $pet), array_replace([
            'name' => $pet->name, 'species' => $pet->species->value,
            'status' => $pet->availability_status->value, 'version' => $pet->version,
        ], $changes));
    }

    private function historical(array $values = []): Pet
    {
        $id = DB::table('pets')->insertGetId(array_replace([
            'name' => 'Synthetic historical pet', 'species' => 'Dog', 'age' => 36,
            'physical_size' => 'Small', 'legacy_physical_size' => 'Small', 'weight_kg' => '15.000',
            'created_at' => now(), 'updated_at' => now(),
        ], $values));

        return Pet::withoutGlobalScopes()->findOrFail($id);
    }

    public function test_authorized_creation_ignores_forged_size_and_metadata_and_stores_months(): void
    {
        $this->actingAs($this->matchingUser(Role::Volunteer))->post(route('admin.animals.store'), [
            'name' => 'Synthetic Adult', 'species' => 'Dog', 'weight_kg' => '6.500', 'status' => 'Assessing',
            'age_years' => '2', 'age_months' => '0', 'physical_size' => ['forged'],
            'size_rule_version' => 'forged', 'size_classification_status' => 'forged', 'legacy_physical_size' => 'Extra Large',
        ])->assertSessionHasNoErrors();
        $pet = Pet::sole();
        $this->assertSame(24, $pet->age);
        $this->assertSame('Small', $pet->physical_size);
        $this->assertSame(PetSizeClassifier::VERSION, $pet->size_rule_version);
        $this->assertSame('classified', $pet->size_classification_status);
        $this->assertNull($pet->legacy_physical_size);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Added New Animal', 'entity_id' => $pet->id]);
    }

    public function test_age_unknown_zero_partial_and_legacy_age_contracts(): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator));
        $base = ['name' => 'Synthetic Age', 'species' => 'Cat', 'status' => 'Assessing', 'weight_kg' => '4.000'];
        $this->post(route('admin.animals.store'), $base + ['age' => 24])->assertSessionHasErrors('age');
        foreach ([[[], null, 'age_unknown'], [['age_years' => '', 'age_months' => ''], null, 'age_unknown'],
            [['age_years' => 0, 'age_months' => 0], 0, 'juvenile'], [['age_months' => 11], 11, 'juvenile'],
            [['age_years' => 1], 12, 'classified']] as [$ageInput, $months, $status]) {
            $this->post(route('admin.animals.store'), $base + $ageInput)->assertSessionHasNoErrors();
            $pet = Pet::latest('id')->first();
            $this->assertSame($months, $pet->age);
            $this->assertSame($status, $pet->size_classification_status);
        }
    }

    public function test_weight_age_species_and_blank_edits_recalculate_with_no_manual_override(): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator));
        $pet = $this->matchingPet(['weight_kg' => '8.999']);
        $this->assertSame('Small', $pet->physical_size);
        $this->edit($pet, ['weight_kg' => '9.000', 'physical_size' => 'Extra Large'])->assertSessionHasNoErrors();
        $this->assertSame('Medium', $pet->refresh()->physical_size);
        $this->edit($pet, ['age_years' => 1, 'age_months' => 11])->assertSessionHasNoErrors();
        $this->assertNull($pet->refresh()->physical_size);
        $this->assertSame('juvenile', $pet->size_classification_status);
        $this->edit($pet, ['species' => 'Cat', 'weight_kg' => '4.000'])->assertSessionHasNoErrors();
        $this->assertSame('Medium', $pet->refresh()->physical_size);
        $this->assertSame(23, $pet->age);
        $this->edit($pet, ['breed' => 'Any breed', 'height_cm' => '99.999'])->assertSessionHasNoErrors();
        $this->assertSame('Medium', $pet->refresh()->physical_size);
        $this->edit($pet, ['age_years' => '', 'age_months' => ''])->assertSessionHasNoErrors();
        $this->assertNull($pet->refresh()->age);
        $this->assertNull($pet->physical_size);
        $this->assertSame('age_unknown', $pet->size_classification_status);
        $this->edit($pet, ['age_years' => 1, 'age_months' => 0, 'weight_kg' => ''])->assertSessionHasNoErrors();
        $this->assertNull($pet->refresh()->physical_size);
        $this->assertSame('weight_unavailable', $pet->size_classification_status);
    }

    public function test_existing_manual_history_survives_review_and_future_reclassification(): void
    {
        $pet = $this->historical();
        $this->assertFalse($pet->hasClassifiedPhysicalSize());
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->edit($pet, ['weight_kg' => '15.000'])->assertSessionHasNoErrors();
        $this->assertSame('Large', $pet->refresh()->physical_size);
        $this->assertSame('Small', $pet->legacy_physical_size);
        $this->edit($pet, ['weight_kg' => '30.000', 'legacy_physical_size' => 'forged'])->assertSessionHasNoErrors();
        $this->assertSame('Extra Large', $pet->refresh()->physical_size);
        $this->assertSame('Small', $pet->legacy_physical_size);
    }

    public function test_raw_unclassified_history_and_stale_classifications_cannot_enter_knn(): void
    {
        $profile = $this->matchingAdopter();
        $pet = $this->matchingPet();
        DB::table('pets')->where('id', $pet->id)->update(['size_classification_status' => 'legacy_unclassified', 'size_rule_version' => null]);
        $match = app(KnnRecommendationService::class)->calculateMatch($profile, $pet->fresh());
        $this->assertSame('PET_SIZE_NOT_ASSESSED', $match->exclusionReason);
        $this->assertNull($match->compatibilityScore);
        DB::table('pets')->where('id', $pet->id)->update(['size_classification_status' => 'classified', 'size_rule_version' => 'weight-v1', 'weight_kg' => '30']);
        $this->assertSame('PET_SIZE_NOT_ASSESSED', app(KnnRecommendationService::class)->calculateMatch($profile, $pet->fresh())->exclusionReason);
        $pet = $pet->fresh();
        $pet->weight_kg = '9';
        $pet->physical_size = 'Extra Large';
        $pet->save();
        $this->assertSame('Medium', $pet->refresh()->physical_size);
        $this->assertTrue($pet->hasClassifiedPhysicalSize());
    }

    public function test_juvenile_unknown_and_historical_pets_still_appear_in_assessed_public_catalog(): void
    {
        $pets = [$this->matchingPet(['name' => 'Synthetic Juvenile', 'age' => 23]),
            $this->matchingPet(['name' => 'Synthetic Unknown', 'age' => null]),
            $this->matchingPet(['name' => 'Synthetic Historical'])];
        DB::table('pets')->where('id', $pets[2]->id)->update(['size_classification_status' => 'legacy_unclassified', 'size_rule_version' => null]);
        foreach ($pets as $pet) {
            app(ApplicationMatchService::class)->refreshPetSummary($pet->fresh());
        }
        $page = $this->get(route('pets.index'))->assertOk();
        foreach ($pets as $pet) {
            $page->assertSee($pet->name);
            $this->assertFalse(Pet::recommendationEligible()->whereKey($pet->id)->exists());
        }
        $this->assertCount(0, app(KnnRecommendationService::class)->recommendPets($this->matchingAdopter()));
    }

    public function test_current_numeric_size_changes_refresh_pending_but_not_terminal_matching_history(): void
    {
        $pet = $this->matchingPet();
        $profile = $this->matchingAdopter();
        $application = AdoptionApplication::create(['user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => 'Pending']);
        app(ApplicationMatchService::class)->refresh($application);
        $before = $application->fresh()->knn_source_fingerprint;
        $terminal = AdoptionApplication::create(['user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => 'Approved', 'knn_score' => 88.0]);
        $terminalBefore = $terminal->fresh()->getAttributes();
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->edit($pet, ['weight_kg' => '30'])->assertSessionHasNoErrors();
        $this->assertNotSame($before, $application->fresh()->knn_source_fingerprint);
        $application->refresh();
        $this->assertSame(5.0, (float) $application->compatibility_result['pet_vector']['physical_size']);
        $this->assertCount(6, $application->compatibility_result['pet_vector']);
        $this->assertSame($terminalBefore, $terminal->fresh()->getAttributes());
    }

    public function test_optimistic_concurrency_rejects_stale_reclassification_without_audit(): void
    {
        $pet = $this->matchingPet();
        $this->actingAs($this->matchingUser(Role::Administrator));
        $version = $pet->version;
        $this->edit($pet, ['weight_kg' => '15'])->assertSessionHasNoErrors();
        $before = $pet->refresh()->getAttributes();
        $audits = AuditLog::count();
        $this->edit($pet, ['version' => $version, 'weight_kg' => '30'])->assertSessionHasErrors('concurrency');
        $this->assertSame($before, $pet->fresh()->getAttributes());
        $this->assertSame($audits, AuditLog::count());
    }

    public function test_impact_command_is_read_only_including_archived_history(): void
    {
        $pets = [$this->historical(), $this->historical(['age' => null, 'is_archived' => true]),
            $this->historical(['species' => 'Cat', 'age' => 11])];
        $before = DB::table('pets')->orderBy('id')->get()->toJson();
        $this->assertSame(0, Artisan::call('pets:size-impact', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($report['dry_run']);
        $this->assertSame(3, $report['total']);
        $this->assertSame(['classified' => 1, 'age_unknown' => 1, 'juvenile' => 1], $report['proposed_status_counts']);
        $this->assertSame('Large', $report['pets'][0]['proposed_size']);
        $this->assertSame($before, DB::table('pets')->orderBy('id')->get()->toJson());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_forms_use_canonical_month_components_and_read_only_size_results(): void
    {
        $pet = $this->matchingPet(['age' => 25]);
        $this->actingAs($this->matchingUser(Role::Administrator));
        foreach ([route('admin.pets.create'), route('admin.pets.edit', $pet), route('admin.animals.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('name="age_years"', false)->assertSee('name="age_months"', false)
                ->assertDontSee('name="age"', false)->assertDontSee('name="physical_size"', false)
                ->assertSee('readonly', false)->assertSee('not healthy-weight assessments');
        }
        $this->get(route('admin.pets.edit', $pet))->assertSee('value="2"', false)->assertSee('value="1"', false);
    }
}
