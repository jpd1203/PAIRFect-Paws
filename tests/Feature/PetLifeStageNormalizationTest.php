<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\CurrentWeightSizeNormalizer;
use App\Services\PetLifeStageNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class PetLifeStageNormalizationTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    private function snapshot(): array
    {
        return collect(['pets', 'assessment_records', 'adoption_applications', 'handovers', 'post_adoption_logs', 'audit_logs'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_dry_run_apply_and_repeat_preserve_ages_weights_history_and_unselected_records(): void
    {
        Mail::fake();
        $young = $this->matchingPet(['age' => 23]);
        $adult = $this->matchingPet(['species' => 'Cat', 'age' => 12, 'weight_kg' => null]);
        $unknown = $this->matchingPet(['age' => null]);
        $unselected = $this->matchingPet(['age' => 96]);
        DB::table('pets')->whereIn('id', [$young->id, $unknown->id, $unselected->id])->update(['life_stage' => 'adult']);
        DB::table('pets')->where('id', $adult->id)->update(['life_stage' => 'young']);
        $before = $this->snapshot();
        $source = $young->fresh()->getAttributes();
        $ids = [$young->id, $adult->id, $unknown->id];
        $service = app(PetLifeStageNormalizer::class);
        $plan = $service->plan($ids, CurrentWeightSizeNormalizer::DATASET);
        $this->assertSame(['selected' => 3, 'would_change' => 3, 'unchanged' => 0, 'skipped' => 0], $plan['counts']);
        $this->assertSame($before, $this->snapshot());
        $result = $service->apply($ids, CurrentWeightSizeNormalizer::DATASET, $plan['plan_hash'], DB::getDatabaseName(), 'synthetic restore-verified backup');
        $this->assertSame(3, $result['changed']);
        $this->assertSame('young', $young->fresh()->getRawOriginal('life_stage'));
        $this->assertSame('adult', $adult->fresh()->getRawOriginal('life_stage'));
        $this->assertNull($adult->fresh()->weight_kg);
        $this->assertNull($unknown->fresh()->age);
        $this->assertNull($unknown->fresh()->getRawOriginal('life_stage'));
        $this->assertSame('adult', $unselected->fresh()->getRawOriginal('life_stage'));
        $allowed = array_fill_keys(['life_stage', 'version', 'updated_at'], true);
        $this->assertSame(array_diff_key($source, $allowed), array_diff_key($young->fresh()->getAttributes(), $allowed));
        $after = $this->snapshot();
        foreach (['assessment_records', 'adoption_applications', 'handovers', 'post_adoption_logs'] as $table) {
            $this->assertSame($before[$table], $after[$table]);
        }
        $this->assertDatabaseCount('audit_logs', 3);
        $plan = $service->plan($ids, CurrentWeightSizeNormalizer::DATASET);
        $this->assertSame(0, $service->apply($ids, CurrentWeightSizeNormalizer::DATASET, $plan['plan_hash'], DB::getDatabaseName(), 'synthetic backup')['changed']);
        $this->assertSame($after, $this->snapshot());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_stale_plan_and_second_audit_failure_are_atomic(): void
    {
        $pets = [$this->matchingPet(['age' => 23]), $this->matchingPet(['age' => 23])];
        $ids = array_map(fn ($p) => $p->id, $pets);
        DB::table('pets')->whereIn('id', $ids)->update(['life_stage' => 'adult']);
        $service = app(PetLifeStageNormalizer::class);
        $plan = $service->plan($ids, CurrentWeightSizeNormalizer::DATASET);
        DB::table('pets')->where('id', $ids[0])->update(['version' => $pets[0]->version + 1]);
        try {
            $service->apply($ids, CurrentWeightSizeNormalizer::DATASET, $plan['plan_hash'], DB::getDatabaseName(), 'test backup');
            $this->fail('Stale plan accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('stale', $e->getMessage());
        }
        $plan = $service->plan($ids, CurrentWeightSizeNormalizer::DATASET);
        $before = $this->snapshot();
        $audits = 0;
        AuditLog::creating(function () use (&$audits): void {
            if (++$audits === 2) {
                throw new RuntimeException('synthetic second audit failure');
            }
        });
        try {
            $service->apply($ids, CurrentWeightSizeNormalizer::DATASET, $plan['plan_hash'], DB::getDatabaseName(), 'test backup');
            $this->fail('Audit failure did not abort.');
        } catch (RuntimeException $e) {
            $this->assertSame('synthetic second audit failure', $e->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_command_requires_explicit_selection_confirmation_and_reviewed_plan(): void
    {
        $pet = $this->matchingPet(['age' => 23]);
        DB::table('pets')->where('id', $pet->id)->update(['life_stage' => 'adult']);
        $options = ['--pet-ids' => (string) $pet->id, '--dataset' => CurrentWeightSizeNormalizer::DATASET, '--json' => true];
        $this->assertSame(1, Artisan::call('pets:normalize-life-stage'));
        $this->assertSame(1, Artisan::call('pets:normalize-life-stage', ['--pet-ids' => 'all']));
        $this->assertSame(1, Artisan::call('pets:normalize-life-stage', array_replace($options, ['--dataset' => 'real'])));
        $this->assertSame(0, Artisan::call('pets:normalize-life-stage', $options));
        $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($plan['dry_run']);
        $apply = $options + ['--apply' => true, '--plan-hash' => $plan['plan_hash'], '--confirm-database' => DB::getDatabaseName(), '--backup-reference' => 'test backup'];
        foreach (['--plan-hash', '--confirm-database', '--backup-reference'] as $field) {
            $this->assertSame(1, Artisan::call('pets:normalize-life-stage', array_replace($apply, [$field => ''])));
        }
        $this->assertSame('adult', $pet->fresh()->getRawOriginal('life_stage'));
        $this->assertSame(0, Artisan::call('pets:normalize-life-stage', $apply));
        $this->assertSame('young', $pet->fresh()->getRawOriginal('life_stage'));
    }
}
