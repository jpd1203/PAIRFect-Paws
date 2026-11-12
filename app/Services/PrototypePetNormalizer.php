<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Pet;
use App\Support\PrototypePetFixtures;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit fictional-fixture repair only; never an ordinary pet editing/review path. */
final class PrototypePetNormalizer
{
    public function __construct(private PetSizeClassifier $classifier) {}

    public function plan(bool $lock = false): array
    {
        $query = Pet::withoutGlobalScopes()->orderBy('id');
        $pets = ($lock ? $query->lockForUpdate() : $query)->get();
        $identified = $pets->mapWithKeys(fn (Pet $pet) => [$pet->id => PrototypePetFixtures::identify($pet)]);
        $counts = $identified->filter()->countBy('key');
        $records = [];
        $skipped = [];
        foreach ($pets as $pet) {
            $fixture = $identified[$pet->id];
            if ($fixture === null || $counts[$fixture['key']] !== 1) {
                $skipped[] = ['id' => $pet->id, 'reason' => $fixture === null ? 'unrecognized_fixture' : 'ambiguous_fixture'];

                continue;
            }
            // This explicit test-data operation intentionally uses the pure policy calculation,
            // not an ordinary-save attestation or a global bypass of historical safeguards.
            $values = $fixture['measurements'];
            $desired = array_replace($values, $this->classifier->classify($values['species'], $values['weight_kg'], $values['age']));
            unset($desired['species']); // Identity must already agree; never convert an unknown animal.
            $changes = [];
            foreach ($desired as $field => $value) {
                $before = $pet->getAttribute($field);
                if ($before !== $value) {
                    $changes[$field] = ['before' => $before, 'after' => $value];
                }
            }
            $records[] = ['id' => $pet->id, 'name' => $pet->name, 'fixture' => $fixture['key'],
                'version' => (int) $pet->version, 'snapshot' => hash('sha256', json_encode($pet->getAttributes(), JSON_THROW_ON_ERROR)),
                'changes' => $changes, 'legacy_physical_size_preserved' => $pet->legacy_physical_size];
        }
        $payload = ['database' => DB::getDatabaseName(), 'driver' => DB::getDriverName(),
            'rule_version' => PetSizeClassifier::VERSION, 'records' => $records, 'skipped' => $skipped];

        return ['dry_run' => true, 'plan_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'would_change' => count(array_filter($records, fn ($row) => $row['changes'] !== [])), ...$payload];
    }

    public function apply(string $planHash, string $confirmation): array
    {
        // Guard before reading pets or starting a transaction, including direct service calls.
        $this->assertDisposable($confirmation);
        if (! preg_match('/\A[a-f0-9]{64}\z/', $planHash)) {
            throw new RuntimeException('A valid hash from the prior dry-run report is required.');
        }

        return DB::transaction(function () use ($planHash): array {
            $plan = $this->plan(lock: true);
            if (! hash_equals($plan['plan_hash'], $planHash)) {
                throw new RuntimeException('The dry-run plan is stale. Nothing changed; generate and review a new plan.');
            }
            $applied = [];
            foreach ($plan['records'] as $row) {
                if ($row['changes'] === []) {
                    continue;
                }
                $changes = array_map(fn ($change) => $change['after'], $row['changes']);
                $changes['version'] = $row['version'] + 1;
                $changes['updated_at'] = now();
                // Deliberate, manifest-bound prototype normalization. The ordinary model
                // guard stays enabled everywhere; legacy_physical_size is never overwritten.
                if (DB::table('pets')->where('id', $row['id'])->where('version', $row['version'])->update($changes) !== 1) {
                    throw new RuntimeException('A fixture revision changed; the entire normalization was rolled back.');
                }
                AuditLog::create(['user_id' => null, 'action' => 'Normalized Prototype Pet Fixture',
                    'entity_name' => 'Pet', 'entity_id' => $row['id'],
                    'notes' => json_encode(['fixture' => $row['fixture'], 'plan_hash' => $planHash,
                        'changes' => $row['changes'], 'version' => ['before' => $row['version'], 'after' => $changes['version']],
                        'legacy_physical_size_preserved' => $row['legacy_physical_size_preserved']], JSON_THROW_ON_ERROR)]);
                $applied[] = ['id' => $row['id'], 'fixture' => $row['fixture'], 'changes' => $row['changes'], 'version' => $changes['version']];
            }

            return ['dry_run' => false, 'plan_hash' => $planHash, 'applied' => $applied,
                'changed' => count($applied), 'skipped' => $plan['skipped']];
        });
    }

    private function assertDisposable(string $confirmation): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Apply is restricted to explicitly disposable testing databases; production/local environments are refused.');
        }
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        $driver = config("database.connections.{$connection}.driver");
        $sqlite = $driver === 'sqlite' && $database === ':memory:';
        $mysql = $driver === 'mysql' && config("database.connections.{$connection}.host") === '127.0.0.1'
            && is_string($database) && preg_match('/\Apairfect_phase15c_[a-z0-9_]+\z/', $database)
            && env('PAIRFECT_DISPOSABLE_MYSQL_DB') === $database;
        if ((! $sqlite && ! $mysql) || $confirmation !== $database || config("database.connections.{$connection}.url")) {
            throw new RuntimeException('Refusing an unverified target. Use :memory: SQLite or an explicitly marked loopback pairfect_phase15c_* disposable MySQL database, with exact confirmation.');
        }
    }
}
