<?php

namespace App\Services;

use App\Enums\PetCurrentStatus;
use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;

class FlagEvaluationService
{
    /**
     * Evaluate a just-submitted welfare report for flag-worthy conditions.
     *
     * Flag if:
     *   - pet_current_status = Poor
     *   - pet_current_status = Fair AND (behavioral_observations or concerns is non-empty)
     */
    public function evaluate(int $logId): void
    {
        $log = PostAdoptionLog::findOrFail($logId);

        if ($log->flagged_for_review) {
            return; // Already flagged, nothing to do
        }

        $shouldFlag = false;

        if ($log->pet_current_status === PetCurrentStatus::Poor) {
            $shouldFlag = true;
        } elseif ($log->pet_current_status === PetCurrentStatus::Fair) {
            $hasConcerns = filled($log->behavioral_observations) || filled($log->concerns);
            if ($hasConcerns) {
                $shouldFlag = true;
            }
        }

        if ($shouldFlag) {
            $log->update(['flagged_for_review' => true]);

            AuditLogService::log(
                null,
                'Post-Adoption Log Flagged',
                'PostAdoptionLog',
                $log->id,
                "Auto-flagged based on welfare report: status={$log->pet_current_status->value}."
            );
        }
    }

    /**
     * Scan overdue, unsubmitted logs and flag those with 2+ reminders sent.
     * Called by the SendCheckinReminders Artisan command.
     */
    public function flagMissedSubmissions(): void
    {
        $overdueLogs = PostAdoptionLog::whereNull('submitted_date')
            ->where('flagged_for_review', false)
            ->where('reminders_sent', '>=', 2)
            ->get();

        foreach ($overdueLogs as $log) {
            $log->update(['flagged_for_review' => true]);

            AuditLogService::log(
                null,
                'Post-Adoption Log Flagged (Missed Submission)',
                'PostAdoptionLog',
                $log->id,
                "Auto-flagged: {$log->reminders_sent} reminders sent with no submission."
            );
        }
    }
}
