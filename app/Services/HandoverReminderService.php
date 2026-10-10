<?php

namespace App\Services;

use App\Models\Handover;
use Illuminate\Support\Facades\DB;

class HandoverReminderService
{
    public function __construct(private HandoverScheduleService $schedules, private HandoverNotificationService $notifications) {}

    public function process(): int
    {
        $count = 0;
        Handover::query()->where('schedule_status', 'confirmed')->whereNull('adopter_outcome')
            ->whereNull('received_at')->whereNotNull('scheduled_start_at')->whereNotNull('scheduled_end_at')
            ->chunkById(100, function ($handovers) use (&$count): void {
                foreach ($handovers as $handover) {
                    $count += DB::transaction(function () use ($handover): int {
                        $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
                        if ($current->schedule_status !== 'confirmed' || ! $current->schedule_confirmed_at || ! $current->scheduled_start_at || ! $current->scheduled_end_at || $current->adopter_outcome || $current->received_at) {
                            return 0;
                        }
                        $events = 0;
                        $now = now();
                        $start = $current->scheduled_start_at;
                        $end = $current->scheduled_end_at;
                        $pet = $current->pet?->name ?? 'your pet';
                        $delivery = $current->scheduled_method === 'delivery';
                        if ($now->lt($start) && $now->gte($start->copy()->subHours(24)) && $now->lt($start->copy()->subHours(2))) {
                            $events += $this->once($current, 'schedule_24h_reminder_sent_at', '24h_reminder', 'Upcoming handover reminder',
                                $pet.' is scheduled for '.($delivery ? 'delivery' : 'pickup').': '.$this->schedules->label($current).($delivery ? '. Please make sure you are available to receive your pet.' : '. Please bring the required identification.'));
                        }
                        if ($now->lt($start) && $now->gte($start->copy()->subHours(2))) {
                            $events += $this->once($current, 'schedule_2h_reminder_sent_at', '2h_reminder', 'Your handover begins soon',
                                $this->schedules->label($current).($delivery ? '. Please be available. If your pet has been released, courier tracking is available from your protected Handover Status page.' : '. Please bring the required identification.'));
                        }
                        if ($delivery && $current->released_at && $now->gte($end->copy()->addHour())) {
                            $events += $this->once($current, 'receipt_reminder_sent_at', 'receipt_reminder', 'Please confirm receipt of your pet',
                                $pet."'s scheduled delivery window has ended. Please confirm in PAIRfect Paws whether your pet has arrived.");
                        }
                        if ($delivery && $current->released_at && $now->gte($end->copy()->addHours(3))) {
                            $events += $this->once($current, 'follow_up_flagged_at', 'delivery_follow_up', 'Delivery receipt unconfirmed',
                                "Handover {$current->code}: the delivery window ended more than three hours ago without receipt confirmation. Contact the adopter and courier.", true);
                        }
                        if (! $delivery && $current->scheduled_method === 'pickup' && ! $current->released_at && $now->gte($end->copy()->addHour())) {
                            $missed = $this->once($current, 'missed_pickup_notified_at', 'missed_pickup', 'Missed / Uncompleted Pickup',
                                "You missed the scheduled pickup window for {$pet}. Please request a new handover schedule.");
                            $events += $missed;
                            if ($missed) {
                                $this->notifications->staffEvent($current, 'missed_pickup', 'Missed / Uncompleted Pickup', "Handover {$current->code}: the pickup window ended without release. The pet remains at the shelter; arrange a new schedule.");
                            }
                        }

                        return $events;
                    });
                }
            });

        return $count;
    }

    private function once(Handover $handover, string $marker, string $kind, string $title, string $body, bool $staff = false): int
    {
        if ($handover->$marker) {
            return 0;
        }
        $updated = Handover::query()->whereKey($handover->id)->where('schedule_version', $handover->schedule_version)
            ->whereNull($marker)->whereNull('adopter_outcome')->whereNull('received_at')->update([$marker => now()]);
        if ($updated !== 1) {
            return 0;
        }
        $handover->$marker = now();
        $handover->recordHistory('handover.'.$kind.' (schedule version '.$handover->schedule_version.')', 'System');
        $handover->save();
        AuditLogService::log(null, 'handover.'.$kind, 'Handover', $handover->id, 'Schedule version '.$handover->schedule_version.'.');
        if ($staff) {
            $this->notifications->staffEvent($handover, $kind, $title, $body);
        } else {
            $this->schedules->notice($handover, $kind, $title, $body);
        }

        return 1;
    }
}
