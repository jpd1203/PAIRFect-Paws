<?php

namespace App\Console\Commands;

use App\Models\ResearchCheckIn;
use App\Services\ResearchWelfareCaseService;
use App\Support\ResearchMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EvaluateResearchWelfareReports extends Command
{
    protected $signature = 'research:evaluate-welfare {--database= : Exact research database name} {--apply : Save first-time evaluations (default is read-only)}';

    protected $description = 'Evaluate previously submitted research welfare reports without sending notifications';

    public function handle(ResearchWelfareCaseService $cases): int
    {
        if (! ResearchMode::ready()) {
            $this->error('Research Mode on an isolated research database is required.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        $actual = $connection->getDriverName() === 'mysql'
            ? $connection->selectOne('SELECT DATABASE() AS name', [], false)->name : $connection->getDatabaseName();
        $allowed = $connection->getDriverName() === 'mysql' && str_starts_with((string) $actual, 'pairfect_research_');
        $isolatedTest = app()->environment('testing') && $actual === $connection->getDatabaseName();
        if ((! $allowed && ! $isolatedTest) || $actual !== $this->option('database')
            || ! Schema::hasColumn('research_checkins', 'welfare_evaluated_at')) {
            $this->error('Confirm the exact research database with --database and apply the research welfare migration first.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $reviewed = $flagged = 0;
        foreach (ResearchCheckIn::query()->afterReceipt()->whereNull('welfare_evaluated_at')->lazyById(100) as $report) {
            $reviewed++;
            $flagged += (int) ($apply ? $cases->backfill($report->id)
                : (! $report->resolved_at && ! $report->resolution_outcome
                    && array_diff($cases->surveyReasons($report), collect($report->welfare_case['flag_reasons'] ?? [])->pluck('message')->all()) !== []));
        }
        $this->info(sprintf('%s: %d reports reviewed; %d reports %s new welfare reasons. No notifications sent.',
            $apply ? 'Applied' : 'Dry run', $reviewed, $flagged, $apply ? 'received' : 'would receive'));

        return self::SUCCESS;
    }
}
