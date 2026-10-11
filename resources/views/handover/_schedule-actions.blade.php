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
            <details class="group mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-2.5 font-semibold text-gray-900 hover:bg-gray-50">
                    <div><p class="text-m font-bold">Request Reschedule</p><p class="text-sm font-normal text-gray-500">Suggest up to three preferred schedules.</p></div>
                    <span class="text-gray-400 transition group-open:rotate-180">▾</span>
                </summary>
                <div class="border-t border-gray-200 bg-gray-50/40 px-4 py-3">
                    <p class="mb-3 text-sm leading-5 text-gray-600">Provide up to three preferred windows in Asia/Manila. A confirmed schedule remains active until staff approve a replacement.</p>
                    <form method="POST" action="{{ route('adopter.handover.reschedule', $record) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                        <div class="space-y-2">
                            @for($i = 0; $i < 3; $i++)
                                <fieldset class="rounded-lg border border-gray-200 bg-white p-3">
                                    <div class="mb-2 flex items-center justify-between">
                                        <legend class="text-sm font-semibold text-gray-900">Option {{ $i + 1 }}</legend>
                                        @if ($i === 0)<span class="text-xs font-semibold text-primary">Required</span>@else<span class="text-xs text-gray-400">Optional</span>@endif
                                    </div>

                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                        <div><label for="option-date-{{ $i }}" class="mb-1 block text-[11px] font-medium text-gray-600">Date</label><input class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" id="option-date-{{ $i }}" type="date" name="options[{{ $i }}][date]"></div>
                                        <div><label for="option-start-{{ $i }}" class="mb-1 block text-[11px] font-medium text-gray-600">Start Time</label><input class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" id="option-start-{{ $i }}" type="time" name="options[{{ $i }}][start_time]"></div>
                                        <div><label for="option-end-{{ $i }}" class="mb-1 block text-[11px] font-medium text-gray-600">End Time</label><input class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" id="option-end-{{ $i }}" type="time" name="options[{{ $i }}][end_time]"></div>
                                    </div>
                                </fieldset>
                            @endfor
                        </div>
                        <div>
                            <label for="handover-reschedule-reason" class="mb-1 block text-sm font-semibold text-gray-700">Reason <span class="font-normal text-gray-400">(optional)</span></label>
                            <textarea id="handover-reschedule-reason" name="reason" maxlength="1000" rows="2" placeholder="Reason for rescheduling..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"></textarea>
                        </div>
                        <div class="flex justify-end pt-1"><button type="submit" class="btn btn-primary">Send Reschedule Request</button></div>
                    </form>
                </div>
            </details>
        @endif
    </section>
@endif
