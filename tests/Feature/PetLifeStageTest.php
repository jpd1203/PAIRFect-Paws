<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicationMatchService;
use App\Services\PetLifeStageClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;
use Tests\Unit\PetLifeStageClassifierTest;

class PetLifeStageTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public static function boundaries(): array
    {
        return array_filter(PetLifeStageClassifierTest::boundaries(), fn ($row) => in_array($row[0], ['Dog', 'Cat'], true));
    }

    #[DataProvider('boundaries')]
    public function test_model_storage_display_catalog_and_knn_consume_one_policy(string $species, ?int $age, ?string $expected): void
    {
        $pet = $this->matchingPet(['species' => $species, 'age' => $age, 'life_stage' => 'forged']);
        $this->assertSame($expected, $pet->getRawOriginal('life_stage'));
        $this->assertSame($expected, $pet->life_stage);
        $this->assertSame($expected === null ? 'Unknown' : ucfirst($expected), $pet->age_group);
        foreach (array_keys(PetLifeStageClassifier::LABELS) as $stage) {
            $this->assertSame($stage === $expected, Pet::lifeStage($stage)->whereKey($pet->id)->exists());
        }
        $match = app(KnnRecommendationService::class)->calculateMatch($this->matchingAdopter(['financial_readiness' => 5]), $pet);
        $this->assertSame($expected !== null, $match->eligible);
        if ($expected === null) {
            $this->assertSame('SAFETY_INFORMATION_INCOMPLETE', $match->exclusionReason);
        }
    }

    private function edit(Pet $pet, array $values = [])
    {
        return $this->put(route('admin.animals.update', $pet), array_replace([
            'name' => $pet->name, 'species' => $pet->species->value,
            'status' => $pet->availability_status->value, 'version' => $pet->version,
        ], $values));
    }

    public function test_http_create_and_edits_derive_stage_and_ignore_forged_values(): void
    {
        $this->actingAs($this->matchingUser(Role::Administrator))->post(route('admin.animals.store'), [
            'name' => 'Synthetic life-stage pet', 'species' => 'Dog', 'age_years' => 1, 'age_months' => 11,
            'weight_kg' => '5', 'status' => 'Assessing', 'life_stage' => ['forged'],
        ])->assertSessionHasNoErrors();
        $pet = Pet::sole();
        $this->assertSame('young', $pet->getRawOriginal('life_stage'));
        $this->edit($pet, ['species' => 'Cat', 'life_stage' => 'senior'])->assertSessionHasNoErrors();
        $this->assertSame('adult', $pet->refresh()->getRawOriginal('life_stage'));
        $this->assertSame(23, $pet->age);
        $this->edit($pet, ['age_years' => 11, 'age_months' => 0])->assertSessionHasNoErrors();
        $this->assertSame('senior', $pet->refresh()->getRawOriginal('life_stage'));
        $this->edit($pet, ['description' => 'Unrelated edit'])->assertSessionHasNoErrors();
        $this->assertSame('senior', $pet->refresh()->getRawOriginal('life_stage'));
        $this->edit($pet, ['age_years' => '', 'age_months' => '', 'life_stage' => 'adult'])->assertSessionHasNoErrors();
        $this->assertNull($pet->refresh()->getRawOriginal('life_stage'));
        $this->assertNull($pet->age);
        $this->assertTrue($pet->hasClassifiedPhysicalSize()); // weight-v2 remains age independent.
        $this->assertDatabaseHas('audit_logs', ['action' => 'Updated Animal Profile', 'entity_id' => $pet->id]);
    }

    public function test_invalid_age_stale_revision_and_unauthorized_writes_preserve_records(): void
    {
        $pet = $this->matchingPet();
        $before = $pet->getAttributes();
        $this->actingAs($this->matchingUser(Role::Volunteer));
        foreach ([['age_years' => -1], ['age_months' => 12], ['age_years' => '1.5'], ['age_months' => []]] as $input) {
            $this->edit($pet, $input)->assertSessionHasErrors();
            $this->assertSame($before, $pet->fresh()->getAttributes());
        }
        $this->edit($pet, ['version' => $pet->version + 10])->assertSessionHasErrors('concurrency');
        $this->assertSame(0, AuditLog::count());
        $this->actingAs($this->matchingUser());
        $this->edit($pet, ['age_years' => 12])->assertRedirect();
        $this->assertSame($before, $pet->fresh()->getAttributes());
    }

    public function test_legacy_stage_does_not_override_known_age_or_fake_unknown_age_into_knn(): void
    {
        $profile = $this->matchingAdopter(['financial_readiness' => 1]);
        $pet = $this->matchingPet(['age' => 11, 'species' => 'Cat', 'medical_needs' => 1]);
        DB::table('pets')->where('id', $pet->id)->update(['life_stage' => 'adult']);
        $this->assertSame('young', $pet->fresh()->life_stage);
        $this->assertSame('adult', $pet->fresh()->getRawOriginal('life_stage')); // No read-time write.
        $matcher = app(KnnRecommendationService::class);
        $this->assertSame('EXCLUDED_FINANCIAL_READINESS', $matcher->calculateMatch($profile, $pet->fresh())->exclusionReason);
        $pet->update(['age' => 12]);
        $this->assertTrue($matcher->calculateMatch($profile, $pet->fresh())->eligible);
        $pet->update(['age' => 132]);
        $this->assertSame('EXCLUDED_FINANCIAL_READINESS', $matcher->calculateMatch($profile, $pet->fresh())->exclusionReason);
        DB::table('pets')->where('id', $pet->id)->update(['age' => null, 'life_stage' => 'adult']);
        $this->assertSame('SAFETY_INFORMATION_INCOMPLETE', $matcher->calculateMatch($this->matchingAdopter(), $pet->fresh())->exclusionReason);
    }

    public function test_public_and_adopter_catalog_filters_ignore_stale_stored_stage(): void
    {
        $pets = [$this->matchingPet(['name' => 'Cat twelve', 'species' => 'Cat', 'age' => 12]),
            $this->matchingPet(['name' => 'Dog twenty-three', 'age' => 23]),
            $this->matchingPet(['name' => 'Cat one-thirty-two', 'species' => 'Cat', 'age' => 132]),
            $this->matchingPet(['name' => 'Age unknown', 'age' => null])];
        foreach ($pets as $pet) {
            app(ApplicationMatchService::class)->refreshPetSummary($pet);
        }
        DB::table('pets')->whereIn('id', array_map(fn ($p) => $p->id, $pets))->update(['life_stage' => 'young']);
        $this->actingAs($this->matchingUser());
        foreach (['young' => $pets[1], 'adult' => $pets[0], 'senior' => $pets[2]] as $stage => $expected) {
            foreach (['pets.index', 'animal.index'] as $route) {
                $response = $this->get(route($route, ['age' => ucfirst($stage)]))->assertOk()->assertSee($expected->name);
                foreach ($pets as $other) {
                    if ($other->id !== $expected->id) {
                        $response->assertDontSee($other->name);
                    }
                }
            }
        }
    }

    public function test_stage_changes_refresh_active_eligibility_but_preserve_every_terminal_snapshot(): void
    {
        $pet = $this->matchingPet(['species' => 'Cat', 'age' => 11, 'medical_needs' => 1]);
        $profile = $this->matchingAdopter(['financial_readiness' => 1]);
        $pending = AdoptionApplication::create(['user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => 'Pending']);
        app(ApplicationMatchService::class)->refresh($pending);
        $this->assertNull($pending->fresh()->knn_score);
        $terminal = collect(ApplicationMatchService::TERMINAL)->map(fn ($status) => AdoptionApplication::create([
            'user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => $status, 'knn_score' => 88,
            'compatibility_result' => ['synthetic immutable history' => true],
        ]));
        $snapshots = $terminal->map(fn ($a) => $a->fresh()->getAttributes())->all();
        $this->actingAs($this->matchingUser(Role::Administrator));
        $this->edit($pet, ['age_years' => 1, 'age_months' => 0])->assertSessionHasNoErrors();
        $this->assertNotNull($pending->fresh()->knn_score);
        $pending->refresh();
        $this->assertCount(6, $pending->compatibility_result['pet_vector']);
        $this->assertSame($snapshots, $terminal->map(fn ($a) => $a->fresh()->getAttributes())->all());
    }

    public function test_staff_forms_have_read_only_unsubmitted_life_stage(): void
    {
        $pet = $this->matchingPet(['age' => 23]);
        $this->actingAs($this->matchingUser(Role::Administrator));
        foreach ([route('admin.animals.index'), route('admin.pets.create'), route('admin.pets.edit', $pet)] as $url) {
            $this->get($url)->assertOk()->assertSee('data-life-stage-output', false)
                ->assertSee('readonly', false)->assertDontSee('name="life_stage"', false)
                ->assertSee('does not advance automatically')->assertSee('not a veterinary diagnosis');
        }
    }
}
