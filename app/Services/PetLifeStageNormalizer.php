<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Reuses explicit fictional selection, snapshots and row locks; never alters ages/weights. */
final class PetLifeStageNormalizer
{
    public function __construct(private CurrentWeightSizeNormalizer $selection, private PetLifeStageClassifier $classifier) {}

    public function plan(array $ids, string $dataset, bool $lock = false): array
    {
        $source = $this->selection->plan($ids, $dataset, $lock);
        $counts = ['selected' => count($source['records']), 'would_change' => 0, 'unchanged' => 0, 'skipped' => 0];
        $rows = [];
        foreach ($source['records'] as $pet) {
            $status = 'classified';
            $proposed = null;
            try {
                $proposed = $this->classifier->classify($pet['species'], $pet['age_months']);
                $status = ! isset(PetLifeStageClassifier::THRESHOLDS[$pet['species']])
                    ? 'unsupported_species' : ($pet['age_months'] === null ? 'age_unknown' : 'classified');
            } catch (InvalidArgumentException) {
                $status = 'invalid_age';
            }
            $changes = [];
            if (in_array($status, ['classified', 'age_unknown'], true)) {
                if ($pet['life_stage'] !== $proposed) {
                    $changes['life_stage'] = ['before' => $pet['life_stage'], 'after' => $proposed];
                }
                $counts[$changes === [] ? 'unchanged' : 'would_change']++;
            } else {
                $counts['skipped']++;
            }
            $rows[] = array_intersect_key($pet, array_flip(['id', 'name', 'species', 'age_months', 'life_stage', 'version', 'snapshot']))
                + ['proposed_life_stage' => $proposed, 'status' => $status, 'changes' => $changes];
        }
        $payload = array_intersect_key($source, array_flip(['dataset', 'database', 'driver', 'host', 'port']))
            + ['rule_version' => PetLifeStageClassifier::VERSION, 'thresholds' => PetLifeStageClassifier::THRESHOLDS,
                'counts' => $counts, 'records' => $rows];

        return ['dry_run' => true, 'plan_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), ...$payload];
    }

    public function apply(array $ids, string $dataset, string $hash, string $database, string $backup): array
    {
        if ($dataset !== CurrentWeightSizeNormalizer::DATASET || ! preg_match('/\A[a-f0-9]{64}\z/', $hash)
            || $database === '' || $database !== DB::connection()->getDatabaseName()
            || trim($backup) === '' || mb_strlen($backup) > 200) {
            throw new RuntimeException('Apply requires the exact fictional dataset, reviewed hash, database confirmation and backup reference.');
        }

        return DB::transaction(function () use ($ids, $dataset, $hash, $backup): array {
            $plan = $this->plan($ids, $dataset, lock: true);
            if (! hash_equals($plan['plan_hash'], $hash)) {
                throw new RuntimeException('Life-stage plan is stale; no changes applied. Review a new dry run.');
            }
            foreach ($plan['records'] as $row) {
                if ($row['changes'] === []) {
                    continue;
                }
                if (DB::table('pets')->where('id', $row['id'])->where('version', $row['version'])->update([
                    'life_stage' => $row['proposed_life_stage'], 'version' => $row['version'] + 1, 'updated_at' => now(),
                ]) !== 1) {
                    throw new RuntimeException('A selected pet revision changed; the entire normalization was rolled back.');
                }
                AuditLog::create(['user_id' => null, 'entity_name' => 'Pet', 'entity_id' => $row['id'],
                    'action' => 'Normalized Prototype Life Stage',
                    'notes' => json_encode(['dataset' => $dataset, 'plan_hash' => $hash, 'backup_reference' => $backup,
                        'rule_version' => PetLifeStageClassifier::VERSION, 'recorded_age_months' => $row['age_months'],
                        'changes' => $row['changes'], 'version' => ['before' => $row['version'], 'after' => $row['version'] + 1]], JSON_THROW_ON_ERROR)]);
            }

            return [...$plan, 'dry_run' => false, 'changed' => $plan['counts']['would_change']];
        });
    }
}
