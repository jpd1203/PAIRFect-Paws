<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Pet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Operator-designated fictional records only; no inferred measurements or lifecycle writes. */
final class CurrentWeightSizeNormalizer
{
    public const DATASET = 'pairfect-fictional-prototype';

    public function __construct(private PetSizeClassifier $classifier) {}

    public function plan(array $ids, string $dataset, bool $lock = false): array
    {
        if ($dataset !== self::DATASET || $ids === [] || count($ids) > 500
            || array_filter($ids, fn ($id) => ! is_int($id) || $id < 1)) {
            throw new RuntimeException('Designate 1–500 positive pet IDs and the exact fictional prototype dataset.');
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        $query = Pet::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('id');
        $pets = ($lock ? $query->lockForUpdate() : $query)->get();
        if ($pets->count() !== count($ids)) {
            throw new RuntimeException('Some designated pet IDs do not exist; no plan or partial apply is permitted.');
        }
        $rows = [];
        $counts = ['selected' => count($ids), 'would_change' => 0, 'unchanged' => 0, 'skipped' => 0];
        foreach ($pets as $pet) {
            $raw = $pet->getAttributes();
            $proposed = $this->classifier->classify($raw['species'], $raw['weight_kg'] ?? null);
            $changes = [];
            if ($proposed['size_classification_status'] === 'classified') {
                // Retain the earliest snapshot, including an existing different legacy label.
                $proposed['legacy_physical_size'] = $raw['legacy_physical_size']
                    ?? ($raw['size_classification_status'] === 'legacy_unclassified' ? $raw['physical_size'] : null);
                foreach ($proposed as $field => $value) {
                    if (($raw[$field] ?? null) !== $value) {
                        $changes[$field] = ['before' => $raw[$field] ?? null, 'after' => $value];
                    }
                }
                $counts[$changes === [] ? 'unchanged' : 'would_change']++;
            } else {
                $counts['skipped']++;
            }
            $rows[] = ['id' => $pet->id, 'name' => $pet->name, 'species' => $raw['species'],
                'age_months' => $raw['age'], 'life_stage' => $raw['life_stage'],
                'weight_kg' => $raw['weight_kg'], 'status' => $proposed['size_classification_status'],
                'changes' => $changes, 'version' => (int) $pet->version,
                'snapshot' => hash('sha256', json_encode($raw, JSON_THROW_ON_ERROR))];
        }
        $connection = DB::connection();
        $payload = ['dataset' => $dataset, 'database' => $connection->getDatabaseName(),
            'driver' => $connection->getDriverName(), 'host' => $connection->getConfig('host'),
            'port' => $connection->getConfig('port'), 'rule_version' => PetSizeClassifier::VERSION,
            'counts' => $counts, 'records' => $rows];

        return ['dry_run' => true, 'plan_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), ...$payload];
    }

    public function apply(array $ids, string $dataset, string $hash, string $database, string $backup): array
    {
        if ($dataset !== self::DATASET || ! preg_match('/\A[a-f0-9]{64}\z/', $hash)
            || $database === '' || $database !== DB::connection()->getDatabaseName()
            || trim($backup) === '' || mb_strlen($backup) > 200) {
            throw new RuntimeException('Apply requires the exact dataset, reviewed plan hash, database confirmation and backup reference (1–200 characters).');
        }

        return DB::transaction(function () use ($ids, $dataset, $hash, $backup): array {
            $plan = $this->plan($ids, $dataset, lock: true);
            if (! hash_equals($plan['plan_hash'], $hash)) {
                throw new RuntimeException('The reviewed plan is stale; no changes applied. Run and review a new dry run.');
            }
            foreach ($plan['records'] as $row) {
                if ($row['changes'] === []) {
                    continue;
                }
                $values = array_map(fn ($change) => $change['after'], $row['changes']);
                $values['version'] = $row['version'] + 1;
                $values['updated_at'] = now();
                if (DB::table('pets')->where('id', $row['id'])->where('version', $row['version'])->update($values) !== 1) {
                    throw new RuntimeException('A selected pet revision changed; the entire operation was rolled back.');
                }
                AuditLog::create(['user_id' => null, 'entity_name' => 'Pet', 'entity_id' => $row['id'],
                    'action' => 'Normalized Prototype Current Weight Size',
                    'notes' => json_encode(['dataset' => $dataset, 'plan_hash' => $hash,
                        'backup_reference' => $backup, 'changes' => $row['changes'],
                        'version' => ['before' => $row['version'], 'after' => $values['version']]], JSON_THROW_ON_ERROR)]);
            }

            return [...$plan, 'dry_run' => false, 'changed' => $plan['counts']['would_change']];
        });
    }
}
