<?php

namespace App\Console\Commands;

use App\Services\CurrentWeightSizeNormalizer;
use Illuminate\Console\Command;
use RuntimeException;

final class NormalizeCurrentWeightSize extends Command
{
    protected $signature = 'pets:normalize-current-size
        {--pet-ids= : Explicit comma-separated fictional pet IDs, maximum 500; no all-records mode}
        {--dataset= : Exact attestation pairfect-fictional-prototype}
        {--apply : Apply the reviewed plan; otherwise read-only}
        {--plan-hash= : Hash from the reviewed dry run}
        {--confirm-database= : Exact database name/path}
        {--backup-reference= : Reference to the verified backup/restore evidence}
        {--json : Output the complete report as JSON}';

    protected $description = 'Preview or explicitly normalize selected fictional pets using recorded weights only';

    public function handle(CurrentWeightSizeNormalizer $normalizer): int
    {
        try {
            $input = (string) $this->option('pet-ids');
            if (! preg_match('/\A[1-9][0-9]*(?:,[1-9][0-9]*)*\z/', $input)) {
                throw new RuntimeException('Explicit comma-separated positive pet IDs are required; ranges/all are not accepted.');
            }
            $ids = array_map('intval', explode(',', $input));
            $dataset = (string) $this->option('dataset');
            $report = $this->option('apply')
                ? $normalizer->apply($ids, $dataset, (string) $this->option('plan-hash'),
                    (string) $this->option('confirm-database'), (string) $this->option('backup-reference'))
                : $normalizer->plan($ids, $dataset);
            if (! $this->option('json')) {
                $this->info($report['dry_run'] ? 'DRY RUN: no records changed.' : 'Selected fictional size classifications updated.');
            }
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
