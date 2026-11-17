@include('handover._schedule-summary')
@if(!$record->released_at && !$record->adopter_outcome)
    <details id="scheduleProposal" class="bg-white border border-[#e2ddd7] rounded-card shadow-card p-4 sm:p-5 scroll-mt-20" @if(!$uiConfirmed || $uiExpired || ($errors->any() && (old('method') !== null || old('decision') !== null))) open @endif>
        <summary class="cursor-pointer text-base font-bold">{{ $uiConfirmed && !$uiExpired ? 'Propose another schedule' : 'Propose Handover Schedule' }}</summary>
        <p class="mt-2 text-xs text-[#777]">All times are Asia/Manila. A new proposal requires adopter confirmation before release.</p>
        <form method="POST" action="{{ route('admin.handover.schedule', $record) }}" class="space-y-3">
            @csrf
            <div class="px-6 py-2">
                <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                <div class="flex items-center justify-between mb-4">
                    <label for="schedule-method" class="form-label m-0">Handover Method</label>
                    @if($record->scheduled_method)
                        @php
                            $isPickupAdmin = $record->scheduled_method === 'pickup';
                        @endphp
                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $isPickupAdmin ? 'bg-completed-bg text-completed-fg border-completed-fg' : 'bg-primary-muted/30 text-primary border-primary' }} border">
                            <i class="{{ $isPickupAdmin ? 'fa-solid fa-house-user' : 'fa-solid fa-truck' }} text-[10px]"></i> Adopter preferred: {{ $isPickupAdmin ? 'Pickup at shelter' : 'Third-party delivery' }}
                        </span>
                    @endif
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e2ddd7] bg-white p-3.5 transition has-[:checked]:border-completed-fg has-[:checked]:bg-completed-bg/30 has-[:checked]:ring-1 has-[:checked]:ring-completed-fg">
                        <input type="radio" name="method" value="pickup" @checked(old('method', $record->scheduled_method ?? 'pickup') === 'pickup') class="mt-1 h-4 w-4 shrink-0 accent-completed-solid" required>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark"><i class="fa-solid fa-house-user text-completed-fg"></i> Pickup at shelter</span>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#e2ddd7] bg-white p-3.5 transition has-[:checked]:border-primary has-[:checked]:bg-primary-muted/25 has-[:checked]:ring-1 has-[:checked]:ring-primary">
                        <input type="radio" name="method" value="delivery" @checked(old('method', $record->scheduled_method) === 'delivery') class="mt-1 h-4 w-4 shrink-0 accent-primary" required>
                        <div>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-text-dark"><i class="fa-solid fa-truck text-primary"></i> Third-party delivery</span>
                        </div>
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-3 mt-4">
                    <div><label for="schedule-date" class="form-label">Handover Date</label><input id="schedule-date" type="date" name="date" value="{{ old('date') }}" class="form-control" required aria-invalid="{{ $errors->has('date') ? 'true' : 'false' }}" aria-describedby="scheduleDateError">@error('date')<p id="scheduleDateError" class="text-xs text-red-700" role="alert">{{ $message }}</p>@enderror</div>
                    <div><label for="schedule-start" class="form-label">Start Time</label><input id="schedule-start" type="time" name="start_time" value="{{ old('start_time') }}" class="form-control" required aria-invalid="{{ $errors->has('start_time') ? 'true' : 'false' }}" aria-describedby="scheduleStartError">@error('start_time')<p id="scheduleStartError" class="text-xs text-red-700" role="alert">{{ $message }}</p>@enderror</div>
                    <div><label for="schedule-end" class="form-label">End Time</label><input id="schedule-end" type="time" name="end_time" value="{{ old('end_time') }}" class="form-control" required aria-invalid="{{ $errors->has('end_time') ? 'true' : 'false' }}" aria-describedby="scheduleEndError">@error('end_time')<p id="scheduleEndError" class="text-xs text-red-700" role="alert">{{ $message }}</p>@enderror</div>
                </div>
                @error('schedule_version')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                @error('method')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-[#e2ddd7] bg-secondary-bg px-6 py-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-truck-fast mr-1.5"></i> Propose Schedule
                </button>
            </div>
        </form>
    </details>
    @if($record->reschedule_status === 'pending')
        <section class="bg-white border border-[#e2ddd7] rounded-card shadow-card overflow-hidden scroll-mt-20" id="rescheduleReviewSection">
            <div class="border-b border-[#e2ddd7] px-6 py-4 bg-secondary-bg">
                <h3 class="text-base font-bold text-text-dark font-primary m-0">Review reschedule request</h3>
            </div>

            @if($record->reschedule_reason)
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
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
                    <button class="btn btn-secondary h-[42px] inline-flex items-center justify-center gap-2" name="decision" value="declined"><i class="fa-solid fa-xmark"></i> Decline Request</button>
                    <button class="btn btn-primary inline-flex items-center justify-center gap-2" name="decision" value="approved"><i class="fa-solid fa-check"></i> Approve Selected Option</button>
                </div>
            </form>
        </section>
    @endif
    @if($record->application)
        <section class="bg-white border border-[#e2ddd7] rounded-card shadow-card overflow-hidden scroll-mt-20" id="identityVerificationSection">
            <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold m-0">Identity Verification</h3>
                    <p class="text-sm mt-1 mb-0">Interview identity: <strong>{{ $uiInterviewVerified ? 'Verified' : 'Verification required' }}</strong></p>
                    @if(!$uiInterviewVerified)<p class="text-xs text-amber-800 mt-1 mb-0">Complete interview identity verification before release.</p>@endif
                </div>
                <a class="btn btn-secondary shrink-0" href="{{ route('admin.applications.identity', ['application' => $record->application, 'from' => 'handover', 'handover' => $record->id]) }}">View Verification</a>
            </div>
        </section>
        @if($record->scheduled_method === 'pickup' && $record->schedule_status === 'confirmed')
            <details id="pickupIdentityDetails" class="rounded-card border border-[#e2ddd7] bg-white p-4" @if(!$uiPickupVerified || ($errors->any() && old('stage') === 'pickup_handover')) open @endif>
                <summary class="cursor-pointer font-semibold text-sm">Final pickup identity: {{ $uiPickupVerified ? 'Verified' : 'Pending' }}</summary>
                @include('admin.application._identity-form', ['identityStage' => 'pickup_handover', 'identityApplication' => $record->application])
            </details>
        @endif
    @endif
@endif
