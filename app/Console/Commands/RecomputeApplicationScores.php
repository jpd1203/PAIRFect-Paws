<?php

namespace App\Console\Commands;

use App\Models\AdoptionApplication;
use App\Services\Matching\ApplicationMatchService;
use Illuminate\Console\Command;

class RecomputeApplicationScores extends Command
{
    protected $signature = 'matching:recompute';

    protected $description = 'Recompute pending application compatibility after profile or matching configuration changes';

    public function handle(ApplicationMatchService $scores): int
    {
        $count = 0;
        AdoptionApplication::whereNotIn('status', ApplicationMatchService::TERMINAL)
            ->with(['user.adopterProfile', 'pet.assessmentRecords'])
            ->chunkById(100, function ($applications) use ($scores, &$count) {
                $scores->refreshMany($applications);
                $count += $applications->count();
            });
        $this->info("Checked {$count} pending application scores.");

        return self::SUCCESS;
    }
}
