@include('handover._schedule-summary')
@if(!$record->released_at && !$record->adopter_outcome)
    <section class="rounded-card border bg-white p-5 shadow-card">
        @if($record->schedule_status === 'proposed')
            <form method="POST" action="{{ route('adopter.handover.schedule.confirm', $record) }}" class="mb-4">
                @csrf
                <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                <button class="btn btn-primary" type="submit">Confirm Schedule</button>
            </form>
        @endif
        @if($record->reschedule_status !== 'pending' && in_array($record->schedule_status, ['proposed', 'confirmed'], true))
            <details>
                <summary class="font-bold cursor-pointer">Request Reschedule</summary>
                <p class="text-sm">Provide up to three preferred windows in Asia/Manila. A confirmed schedule remains active until staff approve a replacement.</p>
                <form method="POST" action="{{ route('adopter.handover.reschedule', $record) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                    @for($i = 0; $i < 3; $i++)
                        <fieldset class="grid gap-2 sm:grid-cols-3">
                            <legend class="font-semibold">Option {{ $i + 1 }}</legend>
                            <div><label class="form-label" for="option-date-{{ $i }}">Date</label><input class="form-control" id="option-date-{{ $i }}" type="date" name="options[{{ $i }}][date]"></div>
                            <div><label class="form-label" for="option-start-{{ $i }}">Start Time</label><input class="form-control" id="option-start-{{ $i }}" type="time" name="options[{{ $i }}][start_time]"></div>
                            <div><label class="form-label" for="option-end-{{ $i }}">End Time</label><input class="form-control" id="option-end-{{ $i }}" type="time" name="options[{{ $i }}][end_time]"></div>
                        </fieldset>
                    @endfor
                    <label class="form-label" for="handover-reschedule-reason">Reason (optional)</label>
                    <textarea class="form-control" id="handover-reschedule-reason" name="reason" maxlength="1000" rows="2"></textarea>
                    <button class="btn btn-primary" type="submit">Send Reschedule Request</button>
                </form>
            </details>
        @endif
    </section>
@endif
