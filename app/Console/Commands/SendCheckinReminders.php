<?php

namespace App\Console\Commands;

use App\Mail\CheckInReminderMail;
use App\Models\PostAdoptionLog;
use App\Services\FlagEvaluationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendCheckinReminders extends Command
{
    protected $signature   = 'checkins:send-reminders';
    protected $description = 'Send check-in reminder emails for upcoming/overdue milestones and auto-flag missed submissions.';

    public function handle(FlagEvaluationService $flagService): int
    {
        $this->info('Running check-in reminder job…');

        // Query all unsubmitted, non-flagged logs
        $logs = PostAdoptionLog::with(['adoptionApplication.user'])
            ->whereNull('submitted_date')
            ->where('flagged_for_review', false)
            ->get();

        $reminded = 0;

        foreach ($logs as $log) {
            $scheduled = Carbon::parse($log->scheduled_date);
            $daysUntil = Carbon::now()->diffInDays($scheduled, false); // negative if past

            // Send reminder if within the next 3 days OR already past
            if ($daysUntil <= 3) {
                $adopter = $log->adoptionApplication?->user;
                if ($adopter) {
                    Mail::to($adopter)->send(new CheckInReminderMail($log));
                    $log->increment('reminders_sent');
                    $reminded++;
                }
            }
        }

        $this->info("Sent {$reminded} reminder(s).");

        // After sending reminders, flag any log with 2+ reminders still outstanding
        $flagService->flagMissedSubmissions();

        $this->info('Flag evaluation complete.');

        return Command::SUCCESS;
    }
}
