<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\User;
use App\Services\KnnRecommendationService;
use App\Services\PetSizeClassifier;
use App\Services\PrototypePetNormalizer;
use App\Support\PrototypePetFixtures;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FreshStartPetsSeeder;
use Database\Seeders\MatchingDemoSeeder;
use Database\Seeders\PrototypePetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class PrototypePetNormalizationTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->app->instance('env', 'testing');
        parent::tearDown();
    }

    private function fixtures(): void
    {
        $this->seed(PrototypePetSeeder::class);
    }

    private function confirmation(): string
    {
        return DB::getDatabaseName();
    }

    private function legacy(Pet $pet): Pet
    {
        DB::table('pets')->where('id', $pet->id)->update(['age' => 2, 'weight_kg' => null,
            'physical_size' => 'Small', 'legacy_physical_size' => 'Small',
            'size_classification_status' => 'legacy_unclassified', 'size_rule_version' => null]);

        return $pet->fresh();
    }

    private function snapshot(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_fresh_fixtures_cover_both_species_and_every_band_with_deliberate_exclusions(): void
    {
        $this->fixtures();
        $this->assertDatabaseCount('pets', 15);
        $this->assertDatabaseCount('assessment_records', 44);
        $profile = User::where('email', 'prototype-adopter@example.test')->sole()->adopterProfile;
        $matcher = app(KnnRecommendationService::class);
        $this->assertCount(10, $matcher->recommendPets($profile));
        foreach (['Dog', 'Cat'] as $species) {
            foreach (array_keys(config('matching.size_levels')) as $size) {
                $matches = $matcher->recommendPets($profile, preferences: ['species' => $species, 'size' => $size]);
                $this->assertCount(1, $matches);
                $pet = $matches->first()['pet'];
                $this->assertTrue($pet->hasClassifiedPhysicalSize());
                $this->assertSame($species, $pet->species->value);
                $this->assertSame($size, $pet->physical_size);
                $this->assertSame(PetSizeClassifier::VERSION, $pet->size_rule_version);
            }
        }
        $all = $matcher->recommendPets($profile, includeIneligible: true);
        $this->assertCount(15, $all);
        foreach (['dog-juvenile' => 'PET_SIZE_NOT_ASSESSED', 'cat-age-unknown' => 'PET_SIZE_NOT_ASSESSED',
            'dog-weight-missing' => 'PET_SIZE_NOT_ASSESSED', 'cat-incomplete' => 'PET_PROFILE_INCOMPLETE',
            'dog-unavailable' => 'PET_UNAVAILABLE'] as $key => $reason) {
            $row = $all->first(fn ($row) => $row['pet']->name === PrototypePetFixtures::pets()[$key]['pet']['name']);
            $this->assertFalse($row['match']->eligible);
            $this->assertSame($reason, $row['match']->exclusionReason);
        }
        $this->assertSame(['juvenile' => 1, 'age_unknown' => 1, 'weight_unavailable' => 1],
            Pet::where('size_classification_status', '!=', 'classified')->get()->countBy('size_classification_status')->all());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_repeated_fixture_setup_is_a_noop_including_account_profile_and_assessment_timestamps(): void
    {
        $this->fixtures();
        $before = $this->snapshot(['pets', 'users', 'adopter_profiles', 'assessment_records', 'audit_logs']);
        $this->seed(PrototypePetSeeder::class);
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        $this->assertSame(0, app(PrototypePetNormalizer::class)->plan()['would_change']);
    }

    public function test_dry_run_is_read_only_and_lists_exact_changes_without_normalizing_unknown_history(): void
    {
        $this->fixtures();
        $legacy = $this->legacy(Pet::where('name', 'Prototype dog adult 3')->sole());
        $unknown = $this->legacy($this->matchingPet(['name' => 'Unrecognized fictional input']));
        $before = $this->snapshot(['pets', 'assessment_records', 'audit_logs']);
        $this->assertSame(0, Artisan::call('prototype:pets-normalize', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['would_change']);
        $row = collect($report['records'])->firstWhere('id', $legacy->id);
        $this->assertSame(['before' => 2, 'after' => 36], $row['changes']['age']);
        $this->assertSame(['before' => null, 'after' => '12.000'], $row['changes']['weight_kg']);
        $this->assertSame('classified', $row['changes']['size_classification_status']['after']);
        $this->assertContains(['id' => $unknown->id, 'reason' => 'unrecognized_fixture'], $report['skipped']);
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        $this->assertSame($report['plan_hash'], app(PrototypePetNormalizer::class)->plan()['plan_hash']);
    }

    public function test_apply_restores_only_designated_fixture_inputs_preserves_history_and_is_repeatable(): void
    {
        $this->fixtures();
        $pet = $this->legacy(Pet::where('name', 'Prototype dog adult 3')->sole());
        $user = User::where('email', 'prototype-adopter@example.test')->sole();
        $unknown = $this->legacy($this->matchingPet(['name' => 'Unrecognized dog']));
        foreach (['Approved', 'Rejected', 'Pending'] as $status) {
            AdoptionApplication::create(['user_id' => $user->id, 'pet_id' => $pet->id, 'status' => $status,
                'knn_score' => 72, 'compatibility_result' => ['synthetic' => 'preserved snapshot']]);
        }
        Handover::create(['code' => 'prototype-history', 'application_id' => AdoptionApplication::first()->id,
            'pet_id' => $pet->id, 'user_id' => $user->id, 'adopter_name' => 'Fictional Adopter',
            'history' => [['label' => 'Fictional original history', 'at' => now()->toIso8601String(), 'actor' => 'Synthetic']]]);
        $history = $this->snapshot(['adoption_applications', 'handovers', 'assessment_records', 'users', 'adopter_profiles']);
        $unknownBefore = $unknown->getAttributes();
        $normalizer = app(PrototypePetNormalizer::class);
        $plan = $normalizer->plan();
        $this->assertSame(0, Artisan::call('prototype:pets-normalize', ['--apply' => true, '--json' => true,
            '--plan-hash' => $plan['plan_hash'], '--confirm-disposable' => $this->confirmation()]));
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $result['changed']);
        $this->assertTrue($pet->refresh()->hasClassifiedPhysicalSize());
        $this->assertSame('Medium', $pet->physical_size);
        $this->assertSame('Small', $pet->legacy_physical_size);
        $this->assertSame(36, $pet->age);
        $this->assertSame('12.000', $pet->weight_kg);
        $this->assertSame($unknownBefore, $unknown->fresh()->getAttributes());
        $this->assertSame($history, $this->snapshot(array_keys($history)));
        $audit = AuditLog::where('action', 'Normalized Prototype Pet Fixture')->sole();
        $notes = json_decode($audit->notes, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($plan['plan_hash'], $notes['plan_hash']);
        $this->assertSame($result['applied'][0]['changes'], $notes['changes']);
        $this->assertSame(0, $normalizer->apply($normalizer->plan()['plan_hash'], $this->confirmation())['changed']);
        $this->assertSame(1, AuditLog::where('action', 'Normalized Prototype Pet Fixture')->count());
        // Unrecognized legacy records still cannot be promoted through ordinary saves.
        $unknown->update(['age' => 36, 'weight_kg' => '12.000']);
        $this->assertFalse($unknown->refresh()->hasClassifiedPhysicalSize());
    }

    public function test_stale_plan_rolls_back_without_audit_or_fixture_changes(): void
    {
        $this->fixtures();
        $pet = $this->legacy(Pet::first());
        $normalizer = app(PrototypePetNormalizer::class);
        $plan = $normalizer->plan();
        DB::table('pets')->where('id', $pet->id)->increment('version');
        $before = $this->snapshot(['pets', 'audit_logs']);
        try {
            $normalizer->apply($plan['plan_hash'], $this->confirmation());
            $this->fail('A stale plan must not apply.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('stale', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
    }

    public function test_audit_failure_rolls_back_the_whole_batch(): void
    {
        $this->fixtures();
        $this->legacy(Pet::first());
        $this->legacy(Pet::skip(1)->first());
        $before = $this->snapshot(['pets', 'audit_logs']);
        $normalizer = app(PrototypePetNormalizer::class);
        AuditLog::creating(function ($log): void {
            if ($log->action === 'Normalized Prototype Pet Fixture') {
                throw new RuntimeException('Synthetic audit failure');
            }
        });
        try {
            $normalizer->apply($normalizer->plan()['plan_hash'], $this->confirmation());
            $this->fail('An audit failure must abort normalization.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
    }

    public function test_duplicates_and_marker_only_impostors_are_skipped(): void
    {
        $this->fixtures();
        $pet = $this->legacy(Pet::first());
        $clone = $pet->replicate();
        $clone->save();
        $impostor = $this->matchingPet(['name' => 'Prototype cat adult 1',
            'behavioral_notes' => PrototypePetFixtures::marker('cat-adult-1'), 'description' => 'Unrecognized record']);
        $plan = app(PrototypePetNormalizer::class)->plan();
        $this->assertSame(0, $plan['would_change']);
        $this->assertContains(['id' => $pet->id, 'reason' => 'ambiguous_fixture'], $plan['skipped']);
        $this->assertContains(['id' => $clone->id, 'reason' => 'ambiguous_fixture'], $plan['skipped']);
        $this->assertContains(['id' => $impostor->id, 'reason' => 'unrecognized_fixture'], $plan['skipped']);
    }

    public static function refusedEnvironments(): array
    {
        return [['production'], ['local'], ['PRODUCTION']];
    }

    #[DataProvider('refusedEnvironments')]
    public function test_apply_is_refused_outside_disposable_testing_even_through_service(string $environment): void
    {
        $this->fixtures();
        $this->legacy(Pet::first());
        $before = $this->snapshot(['pets', 'audit_logs']);
        $this->app->instance('env', $environment);
        try {
            app(PrototypePetNormalizer::class)->apply(str_repeat('a', 64), $this->confirmation());
            $this->fail('Unsafe environment accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('restricted', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
    }

    public function test_wrong_confirmation_or_missing_dry_run_hash_cannot_write(): void
    {
        $this->fixtures();
        $this->legacy(Pet::first());
        $before = $this->snapshot(['pets', 'audit_logs']);
        $this->artisan('prototype:pets-normalize', ['--apply' => true, '--confirm-disposable' => 'wrong'])->assertFailed();
        $this->artisan('prototype:pets-normalize', ['--apply' => true, '--confirm-disposable' => $this->confirmation()])->assertFailed();
        $this->assertSame($before, $this->snapshot(array_keys($before)));
    }

    public function test_files_and_unmarked_mysql_configurations_are_refused_before_connecting(): void
    {
        $original = config('database.default');
        config(['database.default' => 'unsafe_prototype_target',
            'database.connections.unsafe_prototype_target' => ['driver' => 'sqlite', 'database' => 'unopened-real-database.sqlite']]);
        try {
            app(PrototypePetNormalizer::class)->apply(str_repeat('a', 64), 'unopened-real-database.sqlite');
            $this->fail('A file database must not be opened.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unverified', $exception->getMessage());
        } finally {
            config(['database.default' => $original]);
        }
        config(['database.default' => 'unsafe_prototype_target',
            'database.connections.unsafe_prototype_target' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'development']]);
        try {
            app(PrototypePetNormalizer::class)->apply(str_repeat('a', 64), 'development');
            $this->fail('A development database must not be opened.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unverified', $exception->getMessage());
        } finally {
            config(['database.default' => $original]);
        }
    }

    public function test_current_matching_scores_are_unchanged_and_legacy_labels_are_never_a_size_fallback(): void
    {
        $this->fixtures();
        $profile = User::where('email', 'prototype-adopter@example.test')->sole()->adopterProfile;
        $matcher = app(KnnRecommendationService::class);
        $before = $matcher->recommendPets($profile)->map(fn ($row) => $row['match']->toArray())->all();
        $normalizer = app(PrototypePetNormalizer::class);
        $normalizer->apply($normalizer->plan()['plan_hash'], $this->confirmation());
        $this->assertSame($before, $matcher->recommendPets($profile)->map(fn ($row) => $row['match']->toArray())->all());
        $pet = $this->legacy(Pet::where('name', 'Prototype dog adult 3')->sole());
        $this->assertFalse($pet->hasClassifiedPhysicalSize());
        $this->assertNotContains($pet->id, $matcher->recommendPets($profile, preferences: ['species' => 'Dog', 'size' => 'Small'], includeIneligible: true)->pluck('pet.id')->all());
        $this->assertSame('PET_SIZE_NOT_ASSESSED', $matcher->calculateMatch($profile, $pet)->exclusionReason);
    }

    public function test_sample_fixtures_use_canonical_months_and_weight_but_do_not_fabricate_assessments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('pets', 6);
        $this->assertDatabaseCount('assessment_records', 0);
        foreach (Pet::all() as $pet) {
            $this->assertTrue($pet->hasClassifiedPhysicalSize());
            $this->assertSame(PrototypePetFixtures::sampleMeasurements()[$pet->name]['age'], $pet->age);
            $this->assertNotNull(PrototypePetFixtures::identify($pet));
        }
        $this->assertSame(0, app(PrototypePetNormalizer::class)->plan()['would_change']);
        $this->assertSame(0, Pet::recommendationEligible()->count());
    }

    public function test_fresh_start_and_pilot_legacy_batches_preserve_juveniles_and_all_assessments(): void
    {
        $staff = collect(range(1, 3))->map(fn () => $this->matchingUser(Role::Volunteer));
        FreshStartPetsSeeder::seedWithAssessors($staff);
        FreshStartPetsSeeder::seedWithAssessors($staff, 'Demo ', '[PAIRFECT-PILOT-ASSESSED-15-V1]');
        $this->assertSame(0, app(PrototypePetNormalizer::class)->plan()['would_change']);
        foreach (Pet::all() as $pet) {
            $this->legacy($pet);
        }
        $before = $this->snapshot(['assessment_records']);
        $normalizer = app(PrototypePetNormalizer::class);
        $this->assertSame(30, $normalizer->apply($normalizer->plan()['plan_hash'], $this->confirmation())['changed']);
        $this->assertSame(24, Pet::recommendationEligible()->count());
        $this->assertSame(6, Pet::where('size_classification_status', 'juvenile')->whereNull('physical_size')->count());
        $this->assertSame($before, $this->snapshot(array_keys($before)));
    }

    public function test_matching_demo_legacy_measurements_normalize_without_replacing_raw_responses(): void
    {
        $this->seed(MatchingDemoSeeder::class);
        $this->assertSame(0, app(PrototypePetNormalizer::class)->plan()['would_change']);
        foreach (Pet::all() as $pet) {
            $this->legacy($pet);
        }
        $before = $this->snapshot(['assessment_records', 'adopter_profiles']);
        $normalizer = app(PrototypePetNormalizer::class);
        $this->assertSame(12, $normalizer->apply($normalizer->plan()['plan_hash'], $this->confirmation())['changed']);
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        $this->assertSame(12, Pet::where('size_rule_version', PetSizeClassifier::VERSION)->count());
    }

    public function test_all_prototype_legacy_cases_normalize_without_erasing_deliberate_exclusions(): void
    {
        $this->fixtures();
        foreach (Pet::all() as $pet) {
            $this->legacy($pet);
        }
        $this->assertSame(0, Pet::recommendationEligible()->count());
        $normalizer = app(PrototypePetNormalizer::class);
        $this->assertSame(15, $normalizer->apply($normalizer->plan()['plan_hash'], $this->confirmation())['changed']);
        $profile = User::where('email', 'prototype-adopter@example.test')->sole()->adopterProfile;
        $this->assertCount(10, app(KnnRecommendationService::class)->recommendPets($profile));
        $this->assertSame(1, Pet::where('size_classification_status', 'juvenile')->whereNull('physical_size')->count());
        $this->assertSame(1, Pet::where('size_classification_status', 'age_unknown')->whereNull('physical_size')->count());
        $this->assertSame(1, Pet::where('size_classification_status', 'weight_unavailable')->whereNull('physical_size')->count());
        $this->assertDatabaseCount('assessment_records', 44);
        $this->assertSame(15, Pet::where('legacy_physical_size', 'Small')->count());
    }

    public function test_fixture_setup_refuses_unrecognized_name_collisions_and_rolls_back_new_accounts(): void
    {
        $pet = $this->matchingPet(['name' => 'Prototype dog adult 1']);
        $before = $this->snapshot(['pets', 'users', 'adopter_profiles', 'assessment_records', 'audit_logs']);
        try {
            $this->seed(PrototypePetSeeder::class);
            $this->fail('An unrecognized name collision must not be claimed as a fixture.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unrecognized', strtolower($exception->getMessage()));
        }
        $this->assertSame($before, $this->snapshot(array_keys($before)));
        $this->assertNull(PrototypePetFixtures::identify($pet));
    }

    public function test_new_fixture_seeder_is_rejected_in_production_before_creating_records(): void
    {
        $this->app->instance('env', 'production');
        try {
            (new PrototypePetSeeder)->run();
            $this->fail('Production fixture creation must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('only', $exception->getMessage());
        }
        $this->assertDatabaseCount('pets', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
