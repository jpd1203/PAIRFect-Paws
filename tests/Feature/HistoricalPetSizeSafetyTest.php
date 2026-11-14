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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class HistoricalPetSizeSafetyTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    private const HISTORY = ['physical_size', 'legacy_physical_size', 'size_classification_status', 'size_rule_version'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
    }

    private function legacy(array $history = []): Pet
    {
        $pet = $this->matchingPet();
        // Reproduce the additive migration's historical provenance, not new intake.
        DB::table('pets')->where('id', $pet->id)->update(array_replace([
            'physical_size' => 'Small', 'legacy_physical_size' => 'Small',
            'size_classification_status' => 'legacy_unclassified', 'size_rule_version' => null,
        ], $history));

        return $pet->fresh();
    }

    private function history(Pet $pet): array
    {
        return array_intersect_key($pet->getAttributes(), array_flip(self::HISTORY));
    }

    private function assertProtected(Pet $pet, array $before): void
    {
        $this->assertSame($before, $this->history($pet->refresh()));
        $this->assertFalse($pet->hasClassifiedPhysicalSize());
        $this->assertFalse(Pet::recommendationEligible()->whereKey($pet->id)->exists());
    }

    public static function ordinaryEdits(): array
    {
        return [
            'name' => [['name' => 'Synthetic corrected name']],
            'description' => [['description' => 'Synthetic corrected description']],
            'photo' => [['photo_path' => 'synthetic/replacement.jpg']],
            'availability' => [['availability_status' => 'Assessing']],
            'age' => [['age' => 48]],
            'weight' => [['weight_kg' => '30.000']],
            'age and weight' => [['age' => 48, 'weight_kg' => '30.000']],
            'species' => [['species' => 'Cat', 'age' => 12, 'weight_kg' => '6.500']],
            'manual size' => [['physical_size' => 'Extra Large']],
            'metadata only' => [['size_classification_status' => 'classified', 'size_rule_version' => 'weight-v1', 'legacy_physical_size' => 'Extra Large']],
        ];
    }

    #[DataProvider('ordinaryEdits')]
    public function test_normal_model_saves_preserve_persisted_legacy_history(array $changes): void
    {
        $pet = $this->legacy();
        $before = $this->history($pet);
        $pet->forceFill($changes)->save();
        $this->assertProtected($pet, $before);
        foreach (array_diff_key($changes, array_flip(self::HISTORY)) as $field => $value) {
            $this->assertEquals($value, $pet->getRawOriginal($field));
        }
    }

    public static function httpEdits(): array
    {
        return [
            'cosmetic' => [['name' => 'Synthetic correction', 'description' => 'Corrected story']],
            'availability' => [['status' => 'Assessing']],
            'age' => [['age_years' => 4, 'age_months' => 0]],
            'weight' => [['weight_kg' => '30.000']],
            'age and weight' => [['age_years' => 4, 'weight_kg' => '30.000']],
            'forged history' => [['physical_size' => 'Extra Large', 'size_classification_status' => 'classified', 'size_rule_version' => 'weight-v1', 'legacy_physical_size' => 'Extra Large']],
        ];
    }

    private function edit(Pet $pet, array $changes = [])
    {
        return $this->put(route('admin.animals.update', $pet), array_replace([
            'name' => $pet->name, 'species' => $pet->species->value,
            'status' => $pet->availability_status->value, 'version' => $pet->version,
        ], $changes));
    }

    #[DataProvider('httpEdits')]
    public function test_version_checked_staff_updates_preserve_history(array $changes): void
    {
        $pet = $this->legacy();
        $before = $this->history($pet);
        $this->actingAs($this->matchingUser(Role::Volunteer));
        $this->edit($pet, $changes)->assertSessionHasNoErrors();
        $this->assertProtected($pet, $before);
        $this->assertSame(2, (int) $pet->version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Updated Animal Profile', 'entity_id' => $pet->id]);
        if (isset($changes['weight_kg'])) {
            $this->assertSame($changes['weight_kg'], $pet->weight_kg);
        }
        if (isset($changes['age_years'])) {
            $this->assertSame(48, $pet->age);
        }
    }

    public function test_photo_upload_does_not_promote_history(): void
    {
        $pet = $this->legacy();
        $before = $this->history($pet);
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->edit($pet, ['photo' => UploadedFile::fake()->image('synthetic.jpg')])->assertSessionHasNoErrors();
        $this->assertProtected($pet, $before);
        Storage::disk('public')->assertExists($pet->photo_path);
    }

    public function test_original_provenance_cannot_be_forged_before_trust_check_or_direct_classification_write(): void
    {
        $pet = $this->legacy();
        $before = $this->history($pet);
        $classifier = app(PetSizeClassifier::class);
        $forged = ['physical_size' => 'Medium', 'size_classification_status' => 'classified',
            'size_rule_version' => PetSizeClassifier::VERSION, 'legacy_physical_size' => 'Extra Large'];
        $pet->forceFill($forged);
        $this->assertFalse($pet->hasClassifiedPhysicalSize());
        $changes = array_replace($forged, ['age' => 48, 'weight_kg' => '30.000']);
        $protected = $classifier->changes($pet, $changes);
        $this->assertSame($before, array_intersect_key($protected, array_flip(self::HISTORY)));
        $this->assertSame(1, DB::table('pets')->where('id', $pet->id)->where('version', $pet->version)
            ->update(array_replace($changes, $protected, ['version' => $pet->version + 1])));
        $this->assertProtected($pet, $before);
        $this->assertSame('30.000', $pet->weight_kg);
        $this->assertSame(48, $pet->age);
    }

    public function test_model_update_and_update_or_create_cannot_promote_history(): void
    {
        $pet = $this->legacy();
        $before = $this->history($pet);
        $pet->update(['age' => 48, 'weight_kg' => '30.000']);
        $this->assertProtected($pet, $before);
        Pet::updateOrCreate(['id' => $pet->id], ['name' => 'Synthetic updated import', 'weight_kg' => '15.000']);
        $this->assertProtected($pet, $before);
    }

    public static function historicalValues(): array
    {
        return [
            'null history' => [['physical_size' => null, 'legacy_physical_size' => null]],
            'distinct snapshots' => [['physical_size' => 'Medium', 'legacy_physical_size' => 'Extra Small']],
            'untrusted version' => [['size_rule_version' => 'weight-v1']],
        ];
    }

    #[DataProvider('historicalValues')]
    public function test_historical_values_are_preserved_exactly_without_inventing_a_snapshot(array $values): void
    {
        $pet = $this->legacy($values);
        $before = $this->history($pet);
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->edit($pet, ['age_years' => 4, 'weight_kg' => '30.000'])->assertSessionHasNoErrors();
        $this->assertProtected($pet, $before);
    }

    public function test_stale_revision_does_not_change_history_measurements_or_audit(): void
    {
        $pet = $this->legacy();
        $history = $this->history($pet);
        $oldVersion = $pet->version;
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->edit($pet, ['name' => 'Synthetic correction'])->assertSessionHasNoErrors();
        $this->assertProtected($pet, $history);
        $before = $pet->getAttributes();
        $audits = AuditLog::count();
        $this->edit($pet, ['version' => $oldVersion, 'weight_kg' => '30.000'])->assertSessionHasErrors('concurrency');
        $this->assertSame($before, $pet->fresh()->getAttributes());
        $this->assertSame($audits, AuditLog::count());
    }

    public function test_unauthorized_adopter_cannot_edit_or_forge_classification(): void
    {
        $pet = $this->legacy();
        $before = $pet->getAttributes();
        $this->actingAs($this->matchingUser());
        $this->edit($pet, ['weight_kg' => '30.000', 'size_classification_status' => 'classified'])->assertRedirect();
        $this->assertSame($before, $pet->fresh()->getAttributes());
        $this->assertSame(0, AuditLog::count());
    }

    public static function currentPolicyStates(): array
    {
        return [
            'trusted adult' => [['age' => 36, 'weight_kg' => '12.000']],
            'juvenile' => [['age' => 23, 'weight_kg' => '12.000']],
            'unknown age' => [['age' => null, 'weight_kg' => '12.000']],
            'unavailable weight' => [['age' => 36, 'weight_kg' => null]],
        ];
    }

    #[DataProvider('currentPolicyStates')]
    public function test_new_and_current_policy_pets_still_classify_automatically(array $values): void
    {
        $pet = $this->matchingPet($values);
        $expected = app(PetSizeClassifier::class)->classify('Dog', $values['weight_kg'], $values['age']);
        foreach ($expected as $key => $value) {
            $this->assertSame($value, $pet->$key);
        }
        $pet->update(['age' => 24, 'weight_kg' => '30.000']);
        $this->assertTrue($pet->refresh()->hasClassifiedPhysicalSize());
        $this->assertSame('Extra Large', $pet->physical_size);
        $this->assertNull($pet->legacy_physical_size);
    }

    public function test_ordinary_edit_preserves_recommendation_reasons_scores_order_and_terminal_history(): void
    {
        $profile = $this->matchingAdopter();
        $legacy = $this->legacy();
        $trusted = [$this->matchingPet(['weight_kg' => '7.000']), $this->matchingPet(['weight_kg' => '12.000'])];
        $matcher = app(KnnRecommendationService::class);
        $before = $matcher->recommendPets($profile)->map(fn ($row) => $row['match']->toArray())->all();
        $this->assertCount(2, $before);
        $terminals = collect(ApplicationMatchService::TERMINAL)->map(fn ($status) => AdoptionApplication::create([
            'user_id' => $profile->user_id, 'pet_id' => $legacy->id, 'status' => $status, 'knn_score' => 88,
            'compatibility_result' => ['synthetic' => 'immutable terminal history'],
        ]));
        $snapshots = $terminals->map(fn ($application) => $application->fresh()->getAttributes())->all();
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->edit($legacy, ['age_years' => 4, 'weight_kg' => '30.000'])->assertSessionHasNoErrors();
        $this->assertFalse($legacy->refresh()->hasClassifiedPhysicalSize());
        $this->assertSame($before, $matcher->recommendPets($profile)->map(fn ($row) => $row['match']->toArray())->all());
        $all = $matcher->recommendPets($profile, preferences: ['species' => 'Dog'], includeIneligible: true);
        $this->assertCount(3, $all);
        $historical = $all->first(fn ($row) => $row['pet']->id === $legacy->id)['match'];
        $this->assertFalse($historical->eligible);
        $this->assertSame('PET_SIZE_NOT_ASSESSED', $historical->exclusionReason);
        $this->assertNull($historical->compatibilityScore);
        foreach (array_keys(config('matching.size_levels')) as $size) {
            $this->assertNotContains($legacy->id, $matcher->recommendPets($profile,
                preferences: ['species' => 'Dog', 'size' => $size], includeIneligible: true)->pluck('pet.id')->all());
        }
        $this->get(route('pets.index'))->assertOk()->assertSee($legacy->name);
        $this->assertSame($snapshots, $terminals->map(fn ($application) => $application->fresh()->getAttributes())->all());
        $this->assertSame('weight-v1', $trusted[0]->size_rule_version);
    }
}
