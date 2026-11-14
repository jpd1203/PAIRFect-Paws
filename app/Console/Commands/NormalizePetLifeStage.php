<?php

namespace App\Console\Commands;

use App\Services\PetLifeStageNormalizer;
use Illuminate\Console\Command;
use RuntimeException;

final class NormalizePetLifeStage extends Command
{
    protected $signature = 'pets:normalize-life-stage
        {--pet-ids= : Explicit comma-separated fictional pet IDs, maximum 500}
        {--dataset= : Exact attestation pairfect-fictional-prototype}
        {--apply : Apply a previously reviewed plan; default is read-only}
        {--plan-hash= : Exact hash from the reviewed dry run}
        {--confirm-database= : Exact target database name/path}
        {--backup-reference= : Verified backup/restore evidence reference}
        {--json : Print report as JSON}';

    protected $description = 'Preview/normalize selected fictional life stages using recorded species and age only';

    public function handle(PetLifeStageNormalizer $normalizer): int
    {
        try {
            $input = (string) $this->option('pet-ids');
            if (! preg_match('/\A[1-9][0-9]*(?:,[1-9][0-9]*)*\z/', $input)) {
                throw new RuntimeException('Explicit positive comma-separated pet IDs are required; no all-records mode.');
            }
            $ids = array_map('intval', explode(',', $input));
            $dataset = (string) $this->option('dataset');
            $report = $this->option('apply')
                ? $normalizer->apply($ids, $dataset, (string) $this->option('plan-hash'),
                    (string) $this->option('confirm-database'), (string) $this->option('backup-reference'))
                : $normalizer->plan($ids, $dataset);
            if (! $this->option('json')) {
                $this->info($report['dry_run'] ? 'DRY RUN: no records changed.' : 'Selected fictional life stages normalized.');
            }
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
