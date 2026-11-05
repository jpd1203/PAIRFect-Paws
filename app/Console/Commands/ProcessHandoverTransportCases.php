<?php

namespace App\Console\Commands;

use App\Services\HandoverTransportIncidentService;
use Illuminate\Console\Command;

class ProcessHandoverTransportCases extends Command
{
    protected $signature = 'handovers:process-transport-cases';

    protected $description = 'Recover unresolved delivery cases and record due staff follow-up alerts without changing handover outcomes';

    public function handle(HandoverTransportIncidentService $service): int
    {
        $this->info('Recorded '.$service->process().' transport case follow-up event(s). No receipt or delivery finding was inferred.');

        return self::SUCCESS;
    }
}
