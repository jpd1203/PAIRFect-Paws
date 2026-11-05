<?php

namespace App\Console\Commands;

use App\Services\HandoverMailOutboxService;
use Illuminate\Console\Command;

class DispatchHandoverEmailOutbox extends Command
{
    protected $signature = 'handovers:dispatch-pending-email';

    protected $description = 'Retry durable handover mail intents; queueing does not confirm recipient delivery';

    public function handle(HandoverMailOutboxService $outbox): int
    {
        $this->info('Queued '.$outbox->dispatchPending().' handover email intent(s); provider delivery is not confirmed.');

        return self::SUCCESS;
    }
}
