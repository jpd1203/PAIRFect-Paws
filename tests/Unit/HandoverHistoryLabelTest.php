<?php

namespace Tests\Unit;

use App\Support\HandoverHistoryLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HandoverHistoryLabelTest extends TestCase
{
    public static function knownLabels(): array
    {
        return [
            ['address verified (clearance version 3)', 'Delivery address verified'],
            ['address needs review (clearance version 4)', 'Delivery address marked for further review'],
            ['readiness confirmed (clearance version 5)', 'Adopter availability confirmed'],
            ['recipient unavailable (clearance version 6)', 'Adopter reported unavailable'],
            ['schedule.method_selected (schedule version 0)', 'Adopter selected handover method'],
            ['schedule.proposed (schedule version 2)', 'Handover schedule proposed'],
            ['schedule.confirmed (schedule version 2)', 'Adopter confirmed handover schedule'],
            ['schedule.reschedule_requested (schedule version 3)', 'Adopter requested a new handover schedule'],
            ['schedule.reschedule_approved (schedule version 4)', 'Handover reschedule approved'],
            ['schedule.reschedule_declined (schedule version 3)', 'Handover reschedule declined'],
            ['handover.24h_reminder (schedule version 2)', '24-hour handover reminder recorded'],
            ['handover.2h_reminder (schedule version 2)', '2-hour handover reminder recorded'],
            ['handover.receipt_reminder (schedule version 2)', 'Receipt confirmation reminder recorded'],
            ['handover.delivery_follow_up (schedule version 2)', 'Unconfirmed delivery flagged for staff follow-up'],
            ['handover.missed_pickup (schedule version 2)', 'Missed or uncompleted pickup recorded'],
        ];
    }

    #[DataProvider('knownLabels')]
    public function test_only_known_producer_formats_are_translated(string $stored, string $expected): void
    {
        $this->assertSame($expected, HandoverHistoryLabel::forStaff($stored));
    }

    public static function unrelatedLabels(): array
    {
        return array_map(fn ($label) => [$label], [
            'Transport investigation #8 opened (release attempt 2)',
            'Handover reopened — staff mentioned address verified (clearance version 3)',
            'address verified (clearance version 3) additional notes',
            'address verified (schedule version 3)',
            'schedule.unknown_action (schedule version 3)',
            "address verified (clearance version 3)\n",
            '<script>syntheticOnly()</script>', 'Adopter reported unavailable', '',
        ]);
    }

    #[DataProvider('unrelatedLabels')]
    public function test_unrelated_or_free_text_labels_are_not_reinterpreted(string $label): void
    {
        $this->assertSame($label, HandoverHistoryLabel::forStaff($label));
    }

    public function test_missing_legacy_labels_are_safe(): void
    {
        $this->assertSame('', HandoverHistoryLabel::forStaff(null));
        $this->assertSame('', HandoverHistoryLabel::forStaff([]));
    }
}
