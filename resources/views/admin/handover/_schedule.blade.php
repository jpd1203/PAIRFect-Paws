@include('handover._schedule-summary')
@if(!$record->released_at && !$record->adopter_outcome)
    <section class="rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
        <h3 class="text-base font-bold">Propose Handover Schedule</h3>
        <form method="POST" action="{{ route('admin.handover.schedule', $record) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
            <label for="schedule-method" class="form-label">Handover Method</label>
            <select id="schedule-method" name="method" class="form-select" required>
                <option value="pickup" @selected($record->scheduled_method === 'pickup')>Pickup at shelter</option>
                <option value="delivery" @selected($record->scheduled_method === 'delivery')>Third-party delivery</option>
            </select>
            <div class="grid gap-3 sm:grid-cols-3">
                <div><label for="schedule-date" class="form-label">Handover Date</label><input id="schedule-date" type="date" name="date" class="form-control" required></div>
                <div><label for="schedule-start" class="form-label">Start Time</label><input id="schedule-start" type="time" name="start_time" class="form-control" required></div>
                <div><label for="schedule-end" class="form-label">End Time</label><input id="schedule-end" type="time" name="end_time" class="form-control" required></div>
            </div>
            <p class="text-xs">All times are Asia/Manila. This is a proposed window, not an actual release.</p>
            <button type="submit" class="btn btn-primary">Propose Schedule</button>
        </form>
    </section>
    @if($record->reschedule_status === 'pending')
        <section class="rounded-card border bg-white p-5 shadow-card">
            <h3 class="font-bold">Handover Reschedule Request</h3>
            <p class="whitespace-pre-line">{{ $record->reschedule_reason }}</p>
            <form method="POST" action="{{ route('admin.handover.reschedule.review', $record) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                @foreach($record->reschedule_options ?? [] as $index => $option)
                    <label class="flex gap-2"><input type="radio" name="option_index" value="{{ $index }}"> Option {{ $index + 1 }}: {{ $option['date'] }} · {{ $option['start_time'] }} – {{ $option['end_time'] }} (Asia/Manila)</label>
                @endforeach
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-primary" name="decision" value="approved">Approve Selected Option</button>
                    <button class="btn btn-secondary" name="decision" value="declined">Decline Request</button>
                </div>
            </form>
        </section>
    @endif
    @if($record->application)
        <section class="rounded-card border bg-white p-5 shadow-card">
            <h3 class="font-bold">Identity Verification Status</h3>
            <p>Interview identity: {{ app(\App\Services\IdentityVerificationService::class)->isVerified($record->application) ? 'Verified' : 'Verification required' }}</p>
            <a class="btn btn-secondary" href="{{ route('admin.applications.identity', $record->application) }}">Review Interview Identity</a>
        </section>
        @if($record->scheduled_method === 'pickup' && $record->schedule_status === 'confirmed')
            <p class="text-sm">Final pickup identity: {{ app(\App\Services\IdentityVerificationService::class)->isVerified($record->application, 'pickup_handover', $record->reopen_count) ? 'Verified' : 'Pending' }}</p>
            @include('admin.application._identity-form', ['identityStage' => 'pickup_handover', 'identityApplication' => $record->application])
        @endif
    @endif
@endif
