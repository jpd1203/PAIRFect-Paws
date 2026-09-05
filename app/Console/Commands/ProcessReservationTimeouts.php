<?php

namespace App\Console\Commands;

use App\Services\ReservationQueueService;
use Illuminate\Console\Command;

class ProcessReservationTimeouts extends Command
{
    protected $signature = 'reservations:process-timeouts';

    protected $description = 'Flag overdue interviews and promote the next waitlisted applicant after administrative review.';

    public function handle(ReservationQueueService $queue): int
    {
        $result = $queue->processTimeouts();
        $this->info("Flagged {$result['flagged']} application(s); promoted {$result['promoted']} waitlisted applicant(s).");

        return self::SUCCESS;
    }
}
