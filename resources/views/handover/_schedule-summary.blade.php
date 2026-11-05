<section class="rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-bold text-text-dark font-primary m-0">Pet Handover Schedule</h3>
        @if($record->scheduled_method)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-muted/30 px-2.5 py-0.5 text-xs font-semibold text-primary border border-primary">
                <i class="{{ $record->scheduled_method === 'pickup' ? 'fa-solid fa-house-user' : 'fa-solid fa-truck' }} text-xs"></i>
                {{ $record->scheduled_method === 'pickup' ? 'Shelter Pickup' : 'Third-Party Delivery' }}
            </span>
        @endif
    </div>

    <p class="text-m text-text-dark font-bold m-0">{{ app(\App\Services\HandoverScheduleService::class)->label($record) }}</p>

    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-text-muted">
        <span><strong class="text-text-dark">Schedule Status:</strong> {{ ucfirst(str_replace('_', ' ', $record->schedule_status ?? 'unscheduled')) }}</span>
        @if($record->scheduled_method)
            <span><strong class="text-text-dark">Method:</strong> {{ $record->scheduled_method === 'pickup' ? 'Pickup at shelter' : 'Third-party delivery' }}</span>
        @endif
    </div>

    @if($record->reschedule_status === 'pending')
        <p class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2.5 m-0">
            <i class="fa-solid fa-clock mr-1 text-amber-600"></i>
            Reschedule request pending. {{ $record->schedule_confirmed_at ? 'Your current handover schedule remains in place until shelter staff approves a new schedule.' : 'Staff must agree a schedule before release.' }}
        </p>
    @elseif($record->reschedule_status)
        <p class="mt-2 text-sm text-text-muted m-0">Reschedule request: {{ ucfirst($record->reschedule_status) }}</p>
    @endif

    @if(!$record->adopter_outcome && $record->follow_up_flagged_at)
        <p class="modal-note caution mt-3" role="status">Delivery receipt unconfirmed. Shelter staff need to follow up with the adopter and courier.</p>
    @endif
    @if(!$record->released_at && $record->missed_pickup_notified_at)
        <p class="modal-note caution mt-3" role="status">Missed / Uncompleted Pickup. Please arrange a new handover schedule. The pet remains at the shelter.</p>
    @endif
</section>
