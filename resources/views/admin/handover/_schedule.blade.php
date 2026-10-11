@include('handover._schedule-summary')
@if(!$record->released_at && !$record->adopter_outcome)
    <section class="bg-white border border-[#e2ddd7] rounded-card shadow-card overflow-hidden">
        <div class="border-b border-[#e2ddd7] px-6 py-4 bg-secondary-bg">
            <h3 class="text-base font-bold text-text-dark font-primary m-0">Propose Handover Schedule</h3>
            <p class="mt-0.5 text-xs text-[#777] m-0">All times are Asia/Manila. This is a proposed window, not an actual release.</p>
        </div>
        <form method="POST" action="{{ route('admin.handover.schedule', $record) }}" class="space-y-3">
            @csrf
            <div class="px-6 py-4">
                <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                <label for="schedule-method" class="form-label">Handover Method</label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e2ddd7] bg-white p-3.5 transition has-[:checked]:border-primary has-[:checked]:bg-primary-muted/25 has-[:checked]:ring-1 has-[:checked]:ring-primary">
                        <input type="radio" name="method" value="pickup" {{ $record->scheduled_method === 'pickup' ? 'checked' : '' }} class="mt-1 h-4 w-4 shrink-0 accent-primary" required>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark"><i class="fa-solid fa-house-user text-primary"></i> Pickup at shelter</span>
                            <span class="mt-0.5 block text-xs text-[#777]">Adopter collects the pet in person</span>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e2ddd7] bg-white p-3.5 transition has-[:checked]:border-primary has-[:checked]:bg-primary-muted/25 has-[:checked]:ring-1 has-[:checked]:ring-primary">
                        <input type="radio" name="method" value="delivery" {{ $record->scheduled_method === 'delivery' ? 'checked' : '' }} class="mt-1 h-4 w-4 shrink-0 accent-primary" required>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark"><i class="fa-solid fa-truck text-primary"></i> Third-party delivery</span>
                            <span class="mt-0.5 block text-xs text-[#777]">Pet is transported through an approved pet transport or courier service.</span>
                        </div>
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-3 mt-4">
                    <div><label for="schedule-date" class="form-label">Handover Date</label><input id="schedule-date" type="date" name="date" class="form-control" required></div>
                    <div><label for="schedule-start" class="form-label">Start Time</label><input id="schedule-start" type="time" name="start_time" class="form-control" required></div>
                    <div><label for="schedule-end" class="form-label">End Time</label><input id="schedule-end" type="time" name="end_time" class="form-control" required></div>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-[#e2ddd7] bg-secondary-bg px-6 py-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-truck-fast mr-1.5"></i> Propose Schedule
                </button>
            </div>
        </form>
    </section>
    @if($record->reschedule_status === 'pending')
        <section class="bg-white border border-[#e2ddd7] rounded-card shadow-card overflow-hidden" id="releaseFormSection">
            <div class="border-b border-[#e2ddd7] px-6 py-4 bg-secondary-bg">
                <h3 class="text-base font-bold text-text-dark font-primary m-0">Release Details</h3>
                <p class="mt-0.5 text-xs text-[#777] m-0">Record how and when {{ $record->pet?->name ?? 'the pet' }} physically left the shelter.</p>
            </div>

            @if($record->reschedule_reason)
                <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Reason</p>
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $record->reschedule_reason }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.handover.reschedule.review', $record) }}">
                @csrf
                <div class="px-6 py-4">
                    <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">

                    <div class="space-y-2">
                        @foreach($record->reschedule_options ?? [] as $index => $option)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-primary-muted/25 has-[:checked]:ring-1 has-[:checked]:ring-primary">
                                <input type="radio" name="option_index" value="{{ $index }}" class="mt-1 h-4 w-4 shrink-0 accent-primary">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Option {{ $index + 1 }}</p>
                                    <p class="mt-0.5 text-sm text-gray-700">
                                        {{ \Carbon\Carbon::parse($option['date'])->format('F j, Y') }} ·
                                        {{ \Carbon\Carbon::parse($option['start_time'])->format('g:iA') }} -
                                        {{ \Carbon\Carbon::parse($option['end_time'])->format('g:iA') }}
                                        <span class="text-gray-400">(Asia/Manila)</span>
                                    </p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-[#e2ddd7] bg-secondary-bg px-6 py-4">
                    <button class="btn btn-secondary h-10 inline-flex items-center justify-center gap-2" name="decision" value="declined"><i class="fa-solid fa-xmark"></i> Decline Request</button>
                    <button class="btn btn-primary h-10 inline-flex items-center justify-center gap-2" name="decision" value="approved"><i class="fa-solid fa-check"></i> Approve Selected Option</button>
                </div>
            </form>
        </section>
    @endif
    @if($record->application)
        <section class="bg-white border border-[#e2ddd7] rounded-card shadow-card overflow-hidden" id="releaseFormSection">
            <div class="border-b border-[#e2ddd7] px-6 py-4 bg-secondary-bg">
                <h3 class="text-base font-bold text-text-dark font-primary m-0">Identity Verification Status</h3>
                <p class="mt-0.5 text-xs text-[#777] m-0">Interview identity: {{ app(\App\Services\IdentityVerificationService::class)->isVerified($record->application) ? 'Verified' : 'Verification required' }}</p>
            </div>
            <div class="space-y-5 p-6">
                <a class="btn btn-primary" href="{{ route('admin.applications.identity', $record->application) }}"><i class="fa-solid fa-eye"></i>Review Interview Identity</a>
            </div>
        </section>
        @if($record->scheduled_method === 'pickup' && $record->schedule_status === 'confirmed')
            <p class="text-sm">Final pickup identity: {{ app(\App\Services\IdentityVerificationService::class)->isVerified($record->application, 'pickup_handover', $record->reopen_count) ? 'Verified' : 'Pending' }}</p>
            @include('admin.application._identity-form', ['identityStage' => 'pickup_handover', 'identityApplication' => $record->application])
        @endif
    @endif
@endif
