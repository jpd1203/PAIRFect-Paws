<?php

namespace App\Console\Commands;

use App\Services\PrototypePetNormalizer;
use Illuminate\Console\Command;
use RuntimeException;

final class NormalizePrototypePets extends Command
{
    protected $signature = 'prototype:pets-normalize
        {--json : Print the complete report as JSON}
        {--apply : Apply a previously reviewed plan on a disposable testing database only}
        {--plan-hash= : Exact hash from the prior dry-run report}
        {--confirm-disposable= : Exact disposable database name}';

    protected $description = 'Dry-run narrowly designated fictional pet measurements; never resets or deletes data';

    public function handle(PrototypePetNormalizer $normalizer): int
    {
        try {
            $report = $this->option('apply')
                ? $normalizer->apply((string) $this->option('plan-hash'), (string) $this->option('confirm-disposable'))
                : $normalizer->plan();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info($report['dry_run'] ? 'DRY RUN: no data changed.' : 'Disposable prototype normalization completed.');
            $this->line('Plan hash: '.$report['plan_hash']);
            foreach ($report['records'] ?? $report['applied'] as $row) {
                $this->line(json_encode($row, JSON_THROW_ON_ERROR));
            }
            $this->line('Skipped: '.json_encode($report['skipped'], JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }
}
