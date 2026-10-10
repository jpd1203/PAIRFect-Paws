<?php

namespace App\Console\Commands;

use App\Services\HandoverReminderService;
use Illuminate\Console\Command;

class ProcessHandoverScheduleReminders extends Command
{
    protected $signature = 'handovers:process-schedule-reminders';

    protected $description = 'Process schedule-based transfer reminders and staff follow-ups once per schedule';

    public function handle(HandoverReminderService $service): int
    {
        $this->info('Processed '.$service->process().' handover reminder/follow-up event(s).');

        return self::SUCCESS;
    }
}
