<?php

namespace App\Console\Commands;

use App\Models\Pet;
use App\Services\PetSizeClassifier;
use Illuminate\Console\Command;

/** Read-only by design: there is deliberately no apply/backfill option. */
class PetSizeImpact extends Command
{
    protected $signature = 'pets:size-impact {--json : Output the dry-run report as JSON}';

    protected $description = 'Preview adult weight-based size classifications without changing pets or matching records';

    public function handle(PetSizeClassifier $classifier): int
    {
        $rows = [];
        $statuses = [];
        foreach (Pet::withoutGlobalScopes()->orderBy('id')->cursor() as $pet) {
            $species = $pet->getRawOriginal('species');
            $proposed = $classifier->classify($species, $pet->weight_kg, $pet->age);
            $status = $proposed['size_classification_status'];
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            $rows[] = [
                'pet_id' => $pet->id, 'species' => $species, 'age_months' => $pet->age,
                'weight_kg' => $pet->weight_kg, 'stored_size' => $pet->physical_size,
                'legacy_size' => $pet->legacy_physical_size, 'current_status' => $pet->size_classification_status,
                'proposed_size' => $proposed['physical_size'], 'proposed_status' => $status,
                'label_would_change' => $pet->physical_size !== $proposed['physical_size'],
                'matching_size_current' => $pet->hasClassifiedPhysicalSize(),
            ];
        }
        $report = ['dry_run' => true, 'rule_version' => PetSizeClassifier::VERSION,
            'total' => count($rows), 'proposed_status_counts' => $statuses, 'pets' => $rows];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->info('DRY RUN ONLY: no size, age, weight, history or matching data was written. Rule '.PetSizeClassifier::VERSION);
            $this->table(['Pet ID', 'Stored size', 'Proposed size', 'Status'], array_map(fn ($row) => [
                $row['pet_id'], $row['stored_size'] ?? 'Unknown', $row['proposed_size'] ?? 'Unavailable', $row['proposed_status'],
            ], $rows));
        }

        return self::SUCCESS;
    }
}
