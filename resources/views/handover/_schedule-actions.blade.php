@include('handover._schedule-summary')
@if(!$record->released_at && !$record->adopter_outcome)
    <section class="rounded-card border bg-white p-5 shadow-card">
        @if($record->schedule_status !== 'confirmed')
            <div class="mb-5 pb-5 {{ in_array($record->schedule_status, ['proposed'], true) ? 'border-b border-gray-200' : '' }}">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h4 class="text-sm font-bold text-text-dark font-primary m-0">Handover Method Preference</h4>
                        <p class="text-xs text-[#777] m-0 mt-0.5">Select how you would like to receive {{ $record->pet?->name ?? 'your pet' }}.</p>
                    </div>
                    @if($record->scheduled_method)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                            <i class="fa-solid fa-circle-check text-[10px]"></i> Selected: {{ $record->scheduled_method === 'pickup' ? 'Pickup' : 'Delivery' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200">
                            <i class="fa-solid fa-clock text-[10px]"></i> Selection required
                        </span>
                    @endif
                </div>

                <form method="POST" action="{{ route('adopter.handover.schedule.method', $record) }}">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e2ddd7] bg-white p-3.5 transition hover:bg-gray-50/50 has-[:checked]:border-primary has-[:checked]:bg-primary-muted/25 has-[:checked]:ring-1 has-[:checked]:ring-primary">
                            <input type="radio" name="method" value="pickup" {{ $record->scheduled_method === 'pickup' ? 'checked' : '' }} class="mt-1 h-4 w-4 shrink-0 accent-primary" required>
                            <div>
                                <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark"><i class="fa-solid fa-house-user text-primary"></i> Pickup at shelter</span>
                                <span class="mt-0.5 block text-xs text-[#777]">I will collect {{ $record->pet?->name ?? 'the pet' }} in person from the shelter.</span>
                            </div>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e2ddd7] bg-white p-3.5 transition hover:bg-gray-50/50 has-[:checked]:border-primary has-[:checked]:bg-primary-muted/25 has-[:checked]:ring-1 has-[:checked]:ring-primary">
                            <input type="radio" name="method" value="delivery" {{ $record->scheduled_method === 'delivery' ? 'checked' : '' }} class="mt-1 h-4 w-4 shrink-0 accent-primary" required>
                            <div>
                                <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark"><i class="fa-solid fa-truck text-primary"></i> Third-party delivery</span>
                                <span class="mt-0.5 block text-xs text-[#777]">Deliver {{ $record->pet?->name ?? 'the pet' }} to my residential address via pet courier.</span>
                            </div>
                        </label>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-check mr-1"></i> {{ $record->scheduled_method ? 'Update Preferred Method' : 'Save Preferred Method' }}
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="mb-5 pb-5 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-text-dark font-primary m-0">Handover Method</h4>
                        <p class="text-xs text-[#777] m-0 mt-0.5">Method is confirmed for this scheduled window.</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                        <i class="{{ $record->scheduled_method === 'pickup' ? 'fa-solid fa-house-user' : 'fa-solid fa-truck' }} text-emerald-600"></i>
                        {{ $record->scheduled_method === 'pickup' ? 'Pickup at shelter' : 'Third-party delivery' }}
                    </span>
                </div>
            </div>
        @endif

        @if($record->schedule_status === 'proposed')
            <div class="mb-4 rounded-xl border border-primary/20 bg-primary-muted/15 p-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h4 class="text-sm font-bold text-text-dark font-primary m-0">Confirm Proposed Schedule</h4>
                        <p class="text-xs text-text-muted mt-0.5">Shelter staff proposed a handover window. Confirm if you accept this time.</p>
                    </div>
                    <form method="POST" action="{{ route('adopter.handover.schedule.confirm', $record) }}" class="m-0">
                        @csrf
                        <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                        <button class="btn btn-primary btn-sm" type="submit">
                            <i class="fa-solid fa-check mr-1"></i> Confirm Schedule
                        </button>
                    </form>
                </div>
            </div>
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
