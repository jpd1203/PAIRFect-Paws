<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Services\CurrentWeightSizeNormalizer;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicationMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class CurrentWeightSizeRevisionTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public static function youngPets(): array
    {
        return [['Dog', 6, '5', 'Extra Small'], ['Dog', 18, '12', 'Medium'],
            ['Cat', 8, '4', 'Medium'], ['Dog', 36, '20', 'Large']];
    }

    #[DataProvider('youngPets')]
    public function test_current_size_is_independent_of_age_and_knn_math_is_unchanged(string $species, int $age, string $weight, string $size): void
    {
        $pet = $this->matchingPet(['species' => $species, 'age' => $age, 'weight_kg' => $weight, 'life_stage' => $age < 24 ? 'young' : 'adult']);
        $profile = $this->matchingAdopter();
        $matcher = app(KnnRecommendationService::class);
        $match = $matcher->calculateMatch($profile, $pet);
        $this->assertSame($size, $pet->physical_size);
        $this->assertSame($age < 24 ? 'young' : 'adult', $pet->life_stage);
        $this->assertTrue($match->eligible);
        $this->assertCount(6, $match->petVector);
        $this->assertSame([$pet->id], $matcher->recommendPets($profile, preferences: ['species' => $species, 'size' => $size])->pluck('pet.id')->all());
        $pet->update(['age' => 48, 'life_stage' => 'adult']);
        $adult = $matcher->calculateMatch($profile, $pet->fresh());
        foreach (['petVector', 'adopterVector', 'featureBreakdown', 'baseDistance', 'penalty', 'adjustedDistance', 'compatibilityScore', 'algorithmVersion'] as $field) {
            $this->assertSame($match->$field, $adult->$field);
        }
    }

    public function test_unknown_age_can_have_size_without_inventing_life_stage_or_bypassing_safety(): void
    {
        $pet = $this->matchingPet(['age' => null, 'life_stage' => null]);
        $this->assertTrue($pet->hasClassifiedPhysicalSize());
        $this->assertTrue(Pet::withClassifiedSize('Dog', 'Medium')->whereKey($pet->id)->exists());
        $this->assertNull($pet->age);
        $this->assertNull($pet->life_stage);
        $this->assertSame('SAFETY_INFORMATION_INCOMPLETE', app(KnnRecommendationService::class)->calculateMatch($this->matchingAdopter(), $pet)->exclusionReason);
        $pet->update(['age' => 6, 'life_stage' => 'young', 'has_aggression_history' => true]);
        $this->assertSame('EXCLUDED_CHILD_SAFETY', app(KnnRecommendationService::class)->calculateMatch($this->matchingAdopter(['has_children' => true]), $pet->fresh())->exclusionReason);
        $this->assertSame('EXCLUDED_FINANCIAL_READINESS', app(KnnRecommendationService::class)->calculateMatch($this->matchingAdopter(['financial_readiness' => 1]), $pet->fresh())->exclusionReason);
        $pet->update(['availability_status' => 'Soft-Reserved']);
        $this->assertSame('PET_UNAVAILABLE', app(KnnRecommendationService::class)->calculateMatch($this->matchingAdopter(), $pet->fresh())->exclusionReason);
    }

    public function test_juvenile_size_results_paginate_without_changing_filter_values(): void
    {
        $profile = $this->matchingAdopter();
        for ($i = 0; $i < 7; $i++) {
            $this->matchingPet(['name' => 'Synthetic puppy '.$i, 'age' => 6, 'life_stage' => 'young', 'weight_kg' => '5']);
        }
        $this->actingAs($profile->user);
        $query = ['species' => 'Dog', 'size' => 'Extra Small'];
        $this->get(route('recommendation.results', $query))->assertOk()
            ->assertViewHas('matches', fn ($page) => $page->total() === 7 && $page->count() === 5
                && str_contains($page->url(2), 'size=Extra%20Small') && str_contains($page->url(2), 'species=Dog'));
        $this->get(route('recommendation.results', $query + ['page' => 2]))->assertOk()
            ->assertViewHas('matches', fn ($page) => $page->count() === 2);
    }

    public function test_status_messages_do_not_confuse_size_with_adoption_availability(): void
    {
        $missing = $this->matchingPet(['weight_kg' => null]);
        $this->assertStringContainsString('measured weight is unavailable', $missing->size_display);
        $invalid = $this->matchingPet(['weight_kg' => 0]);
        $this->assertStringContainsString('valid measured weight', $invalid->size_display);
        $historical = $this->historical();
        $this->assertStringContainsString('has not yet been updated', $historical->size_display);
        $historical->update(['size_classification_status' => 'classified', 'size_rule_version' => 'weight-v2']);
        $this->assertFalse($historical->fresh()->hasClassifiedPhysicalSize());
    }

    public function test_public_and_staff_views_show_untrusted_history_as_needing_update(): void
    {
        $pet = $this->historical();
        app(ApplicationMatchService::class)->refreshPetSummary($pet);
        foreach ([route('pets.show', $pet), route('pets.modal', $pet)] as $url) {
            $this->get($url)->assertOk()->assertSee($pet->size_display);
        }
        $this->actingAs($this->matchingUser(Role::Administrator))
            ->get(route('admin.assessments.summary', $pet))->assertOk()->assertSee($pet->size_display);
        $this->assertSame('Small', $pet->fresh()->physical_size);
        $this->assertFalse($pet->hasClassifiedPhysicalSize());
    }

    public static function legacySpecies(): array
    {
        return [['Dog', 24, '12'], ['Cat', 12, '4']];
    }

    #[DataProvider('legacySpecies')]
    public function test_v1_adults_remain_trusted_with_identical_scores_while_forged_v1_young_records_do_not(string $species, int $age, string $weight): void
    {
        $profile = $this->matchingAdopter();
        $pet = $this->matchingPet(['species' => $species, 'age' => $age, 'weight_kg' => $weight]);
        $matcher = app(KnnRecommendationService::class);
        $v2 = $matcher->calculateMatch($profile, $pet)->toArray();
        DB::table('pets')->where('id', $pet->id)->update(['size_rule_version' => 'weight-v1']);
        $this->assertTrue($pet->fresh()->hasClassifiedPhysicalSize());
        $this->assertTrue(Pet::withClassifiedSize($species, 'Medium')->whereKey($pet->id)->exists());
        $this->assertSame($v2, $matcher->calculateMatch($profile, $pet->fresh())->toArray());
        $this->assertSame([$pet->id], $matcher->recommendPets($profile)->pluck('pet.id')->all());
        foreach ([6, null] as $age) {
            DB::table('pets')->where('id', $pet->id)->update(['age' => $age]);
            $this->assertFalse($pet->fresh()->hasClassifiedPhysicalSize());
            $this->assertFalse(Pet::withClassifiedSize($species, 'Medium')->whereKey($pet->id)->exists());
        }
        $pet->refresh()->update(['weight_kg' => '9.000']);
        $this->assertSame('weight-v2', $pet->refresh()->size_rule_version);
        $this->assertTrue($pet->hasClassifiedPhysicalSize());
    }

    private function historical(array $overrides = []): Pet
    {
        $pet = $this->matchingPet(['age' => 6, 'life_stage' => 'young']);
        DB::table('pets')->where('id', $pet->id)->update(array_replace([
            'physical_size' => 'Small', 'legacy_physical_size' => null,
            'size_rule_version' => null, 'size_classification_status' => 'legacy_unclassified',
        ], $overrides));

        return $pet->fresh();
    }

    public function test_normalization_dry_run_apply_idempotency_and_immutable_history(): void
    {
        $pet = $this->historical();
        $other = $this->historical();
        $unmeasured = $this->historical(['weight_kg' => null, 'legacy_physical_size' => 'Extra Large']);
        $zero = $this->historical(['weight_kg' => 0]);
        $profile = $this->matchingAdopter();
        $petBefore = $pet->getAttributes();
        $otherBefore = $other->getAttributes();
        foreach (ApplicationMatchService::TERMINAL as $status) {
            AdoptionApplication::create(['user_id' => $profile->user_id, 'pet_id' => $pet->id, 'status' => $status,
                'knn_score' => 88, 'compatibility_result' => ['immutable' => true]]);
        }
        $tables = ['pets', 'adoption_applications', 'assessment_records', 'handovers', 'post_adoption_logs', 'audit_logs'];
        $before = $this->snapshot($tables);
        $normalizer = app(CurrentWeightSizeNormalizer::class);
        $ids = [$pet->id, $unmeasured->id, $zero->id];
        $plan = $normalizer->plan($ids, CurrentWeightSizeNormalizer::DATASET);
        $this->assertSame(['selected' => 3, 'would_change' => 1, 'unchanged' => 0, 'skipped' => 2], $plan['counts']);
        $this->assertSame($before, $this->snapshot($tables));
        $result = $normalizer->apply($ids, CurrentWeightSizeNormalizer::DATASET, $plan['plan_hash'], DB::getDatabaseName(), 'synthetic-backup-verified');
        $this->assertSame(1, $result['changed']);
        $pet->refresh();
        $this->assertSame('Medium', $pet->physical_size);
        $this->assertSame('Small', $pet->legacy_physical_size);
        $this->assertSame('young', $pet->life_stage);
        $this->assertSame(6, $pet->age);
        $this->assertTrue($pet->hasClassifiedPhysicalSize());
        $allowed = array_fill_keys(['physical_size', 'legacy_physical_size', 'size_classification_status', 'size_rule_version', 'version', 'updated_at'], true);
        $this->assertSame(array_diff_key($petBefore, $allowed), array_diff_key($pet->getAttributes(), $allowed));
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        $this->assertSame('legacy_unclassified', $other->fresh()->size_classification_status);
        $this->assertNull($unmeasured->fresh()->weight_kg);
        $this->assertSame('Extra Large', $unmeasured->legacy_physical_size);
        $this->assertSame('0.000', $zero->fresh()->weight_kg);
        foreach (array_diff($tables, ['pets', 'audit_logs']) as $table) {
            $this->assertSame($before[$table], $this->snapshot([$table])[$table]);
        }
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(0, $normalizer->plan($ids, CurrentWeightSizeNormalizer::DATASET)['counts']['would_change']);
        $again = $normalizer->plan($ids, CurrentWeightSizeNormalizer::DATASET);
        $this->assertSame(0, $normalizer->apply($ids, CurrentWeightSizeNormalizer::DATASET, $again['plan_hash'], DB::getDatabaseName(), 'synthetic-backup-verified')['changed']);
        $this->assertDatabaseCount('audit_logs', 1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    private function snapshot(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_stale_plan_and_audit_failure_roll_back_every_selected_pet(): void
    {
        $pet = $this->historical();
        $normalizer = app(CurrentWeightSizeNormalizer::class);
        $plan = $normalizer->plan([$pet->id], CurrentWeightSizeNormalizer::DATASET);
        $pet->update(['weight_kg' => '30.000']);
        $before = $pet->fresh()->getAttributes();
        try {
            $normalizer->apply([$pet->id], CurrentWeightSizeNormalizer::DATASET, $plan['plan_hash'], DB::getDatabaseName(), 'test-backup');
            $this->fail('Stale plan was accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('stale', $e->getMessage());
        }
        $this->assertSame($before, $pet->fresh()->getAttributes());
        // The weight edit now classifies that pet. Use two still-historical records
        // to exercise rollback after the normalizer's second audit fails.
        $pet = $this->historical();
        $before = $pet->getAttributes();
        $second = $this->historical();
        $secondBefore = $second->getAttributes();
        $plan = $normalizer->plan([$pet->id, $second->id], CurrentWeightSizeNormalizer::DATASET);
        $auditCount = 0;
        AuditLog::creating(function () use (&$auditCount): void {
            if (++$auditCount === 2) {
                throw new RuntimeException('synthetic audit failure');
            }
        });
        try {
            $normalizer->apply([$pet->id, $second->id], CurrentWeightSizeNormalizer::DATASET, $plan['plan_hash'], DB::getDatabaseName(), 'test-backup');
            $this->fail('Audit failure did not abort.');
        } catch (RuntimeException $e) {
            $this->assertSame('synthetic audit failure', $e->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame($before, $pet->fresh()->getAttributes());
        $this->assertSame($secondBefore, $second->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_command_requires_explicit_dataset_selection_database_hash_and_backup(): void
    {
        $pet = $this->historical();
        $base = ['--pet-ids' => (string) $pet->id, '--dataset' => CurrentWeightSizeNormalizer::DATASET, '--json' => true];
        $this->assertSame(1, Artisan::call('pets:normalize-current-size'));
        $this->assertSame(1, Artisan::call('pets:normalize-current-size', ['--pet-ids' => 'all']));
        $this->assertSame(1, Artisan::call('pets:normalize-current-size', array_replace($base, ['--dataset' => 'unknown'])));
        $this->assertSame(0, Artisan::call('pets:normalize-current-size', $base));
        $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($plan['dry_run']);
        $this->assertSame('legacy_unclassified', $pet->fresh()->size_classification_status);
        $apply = $base + ['--apply' => true, '--plan-hash' => $plan['plan_hash'], '--confirm-database' => DB::getDatabaseName(), '--backup-reference' => 'test-backup'];
        foreach (['--plan-hash', '--confirm-database', '--backup-reference'] as $key) {
            $this->assertSame(1, Artisan::call('pets:normalize-current-size', array_replace($apply, [$key => ''])));
        }
        $this->assertSame(0, Artisan::call('pets:normalize-current-size', $apply));
        $this->assertTrue($pet->fresh()->hasClassifiedPhysicalSize());
    }
}
