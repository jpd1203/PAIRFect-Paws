<?php

namespace App\Support;

/** Presentation only: never rewrite history or strip arbitrary staff notes. */
final class HandoverHistoryLabel
{
    private const CLEARANCE = [
        'address verified' => 'Delivery address verified',
        'address needs review' => 'Delivery address marked for further review',
        'readiness confirmed' => 'Adopter availability confirmed',
        'recipient unavailable' => 'Adopter reported unavailable',
    ];

    private const SCHEDULE = [
        'schedule.method_selected' => 'Adopter selected handover method',
        'schedule.proposed' => 'Handover schedule proposed',
        'schedule.confirmed' => 'Adopter confirmed handover schedule',
        'schedule.reschedule_requested' => 'Adopter requested a new handover schedule',
        'schedule.reschedule_approved' => 'Handover reschedule approved',
        'schedule.reschedule_declined' => 'Handover reschedule declined',
        'handover.24h_reminder' => '24-hour handover reminder recorded',
        'handover.2h_reminder' => '2-hour handover reminder recorded',
        'handover.receipt_reminder' => 'Receipt confirmation reminder recorded',
        'handover.delivery_follow_up' => 'Unconfirmed delivery flagged for staff follow-up',
        'handover.missed_pickup' => 'Missed or uncompleted pickup recorded',
    ];

    public static function forStaff(mixed $label): string
    {
        if (! is_string($label)) {
            return '';
        }
        // Match the exact formats emitted by the clearance/schedule/reminder
        // services, not a broad "replace underscores / remove versions" rule.
        if (preg_match('/\A(.+) \(clearance version [0-9]+\)\z/D', $label, $matches)
            && isset(self::CLEARANCE[$matches[1]])) {
            return self::CLEARANCE[$matches[1]];
        }
        if (preg_match('/\A(.+) \(schedule version [0-9]+\)\z/D', $label, $matches)
            && isset(self::SCHEDULE[$matches[1]])) {
            return self::SCHEDULE[$matches[1]];
        }

        return $label;
    }
}
