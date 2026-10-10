<section class="rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
    <h3 class="text-base font-bold">Pet Handover Schedule</h3>
    <p>{{ app(\App\Services\HandoverScheduleService::class)->label($record) }}</p>
    <p class="font-semibold">Schedule: {{ ucfirst(str_replace('_', ' ', $record->schedule_status ?? 'unscheduled')) }}</p>
    @if($record->reschedule_status === 'pending')
        <p class="text-sm">Reschedule request pending. {{ $record->schedule_confirmed_at ? 'Your current handover schedule remains in place until shelter staff approves a new schedule.' : 'Staff must agree a schedule before release.' }}</p>
    @elseif($record->reschedule_status)
        <p class="text-sm">Reschedule request: {{ ucfirst($record->reschedule_status) }}</p>
    @endif
    @if(!$record->adopter_outcome && $record->follow_up_flagged_at)
        <p class="modal-note caution" role="status">Delivery receipt unconfirmed. Shelter staff need to follow up with the adopter and courier.</p>
    @endif
    @if(!$record->released_at && $record->missed_pickup_notified_at)
        <p class="modal-note caution" role="status">Missed / Uncompleted Pickup. Please arrange a new handover schedule. The pet remains at the shelter.</p>
    @endif
</section>
