@php
    $clearance = app(\App\Services\HandoverDeliveryClearanceService::class);
    $delivery = $record->scheduled_method === 'delivery' || $record->release_method === 'delivery';
    $addressVerified = $clearance->addressIsVerified($record);
    $recipientReady = $clearance->readinessIsCurrent($record);
    $editable = $record->scheduled_method === 'delivery' && !$record->released_at && !$record->adopter_outcome && !$record->received_at;
    $confirmedDelivery = $clearance->scheduleIsConfirmed($record);
    $notExpired = $record->scheduled_end_at && \App\Support\ManilaTime::now()->lte(\App\Support\ManilaTime::at($record->scheduled_end_at));
    $interviewVerified = $record->application && app(\App\Services\IdentityVerificationService::class)->isVerified($record->application);
    $allCleared = $addressVerified && $recipientReady;
@endphp

<section class="rounded-card border border-[#e2ddd7] bg-white p-6 shadow-card space-y-5" aria-labelledby="deliveryClearanceHeading">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#e2ddd7] pb-4 min-w-0">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-truck-ramp-box text-primary text-base"></i>
                <h3 id="deliveryClearanceHeading" class="font-primary font-bold text-base text-text-dark m-0">Delivery Verification / Pre-Dispatch Readiness</h3>
            </div>
            <p class="text-xs text-[#777] m-0 mt-1">Review destination snapshot and confirm adopter readiness before authorizing courier release.</p>
        </div>
        @if ($delivery)
            <div class="shrink-0">
                @if ($allCleared)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-completed-bg px-3 py-1 text-xs font-semibold text-completed-fg border border-completed-fg shrink-0 whitespace-nowrap">
                        <i class="fa-solid fa-circle-check text-xs"></i> Pre-Dispatch Cleared
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 border border-amber-300 shrink-0 whitespace-nowrap">
                        <i class="fa-solid fa-clock text-xs text-amber-600"></i> Clearance Incomplete
                    </span>
                @endif
            </div>
        @endif
    </div>

    @if (!$delivery)
        <div class="rounded-xl border border-dashed border-[#e2ddd7] bg-[#FAF8F5] p-5 text-center">
            <i class="fa-solid fa-house-user text-completed-fg text-2xl mb-1.5 block"></i>
            <p class="text-sm text-text-muted m-0">Delivery-address verification and pre-dispatch readiness are not required for shelter pickup. Pickup identity and schedule requirements still apply.</p>
        </div>
    @else
        <!-- Panel 1: Delivery Destination -->
        <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-4.5 space-y-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white border border-[#e2ddd7] text-primary shadow-xs">
                        <i class="fa-solid fa-location-dot text-xs"></i>
                    </span>
                    <h4 class="font-primary font-bold text-sm text-text-dark m-0">Delivery destination</h4>
                </div>
                <div class="shrink-0">
                    @if ($addressVerified)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-completed-bg px-2.5 py-0.5 text-xs font-semibold text-completed-fg border border-completed-fg shrink-0 whitespace-nowrap">
                            <i class="fa-solid fa-circle-check text-xs"></i> Verified for this destination
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 border border-amber-300 shrink-0 whitespace-nowrap">
                            <i class="fa-solid fa-triangle-exclamation text-xs text-amber-600"></i> Not verified for this destination
                        </span>
                    @endif
                </div>
            </div>

            <!-- Address Snapshot Box -->
            <div class="rounded-lg bg-white border border-[#e2ddd7] p-3.5 shadow-xs">
                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777] mb-1">
                    <i class="fa-solid fa-map-pin text-primary"></i>
                    <span>Application Destination Address</span>
                </div>
                <p class="text-sm font-semibold text-text-dark whitespace-pre-line break-words m-0">{{ $record->adopter_address ?: 'No delivery destination recorded. Resolve the approved application record with shelter staff.' }}</p>
                <p class="text-xs text-text-muted mt-1.5 m-0">This is the handover's approved-application snapshot, not the adopter's current profile address.</p>
            </div>

            <!-- Metadata Cards: 2x2 Grid matching Panel 2 -->
            <dl class="grid gap-3 sm:grid-cols-2 text-sm m-0 p-0">
                <div class="rounded-lg bg-white border border-[#e2ddd7] p-3 flex flex-col justify-between min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777] flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-[#9e9e9e]"></i>
                        <span>Verification status</span>
                    </dt>
                    <dd class="text-xs font-semibold mt-2 m-0 flex items-center gap-1.5 {{ $addressVerified ? 'text-completed-fg' : 'text-amber-800' }}">
                        <i class="{{ $addressVerified ? 'fa-solid fa-circle-check text-completed-fg' : 'fa-solid fa-triangle-exclamation text-amber-600' }}"></i>
                        <span>{{ $addressVerified ? 'Verified for this destination' : 'Not verified for this destination' }}</span>
                    </dd>
                </div>

                <div class="rounded-lg bg-white border border-[#e2ddd7] p-3 flex flex-col justify-between min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777] flex items-center gap-1.5">
                        <i class="fa-solid fa-user-shield text-[#9e9e9e]"></i>
                        <span>Last reviewing staff</span>
                    </dt>
                    <dd class="text-xs font-semibold text-text-dark mt-2 m-0 flex items-center gap-1.5">
                        <i class="fa-solid fa-user text-[#9e9e9e]"></i>
                        <span>{{ $record->deliveryAddressVerifiedBy?->full_name ?? 'Not recorded' }}</span>
                    </dd>
                </div>

                <div class="rounded-lg bg-white border border-[#e2ddd7] p-3 flex flex-col justify-between min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777] flex items-center gap-1.5">
                        <i class="fa-regular fa-clock text-[#9e9e9e]"></i>
                        <span>Last review (Asia/Manila)</span>
                    </dt>
                    <dd class="text-xs font-semibold text-text-dark mt-2 m-0 flex items-center gap-1.5">
                        <i class="fa-regular fa-calendar text-[#9e9e9e]"></i>
                        <span>{{ \App\Support\ManilaTime::parseAndFormat($record->delivery_address_verified_at, 'M j, Y g:i A', 'Not recorded') }}</span>
                    </dd>
                </div>

                <div class="rounded-lg bg-white border border-[#e2ddd7] p-3 flex flex-col justify-between min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777] flex items-center gap-1.5">
                        <i class="fa-solid fa-map-location-dot text-[#9e9e9e]"></i>
                        <span>Address snapshot type</span>
                    </dt>
                    <dd class="text-xs font-semibold text-text-dark mt-2 m-0 flex items-center gap-1.5">
                        <i class="fa-solid fa-file-contract text-[#9e9e9e]"></i>
                        <span>Approved application snapshot</span>
                    </dd>
                </div>
            </dl>

            @if ($editable)
                <form method="POST" action="{{ route('admin.handover.delivery.address', $record) }}" class="border-t border-[#e2ddd7] pt-3.5 space-y-2">
                    @csrf
                    <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                    <input type="hidden" name="clearance_version" value="{{ $record->delivery_clearance_version }}">
                    <input type="hidden" name="destination_fingerprint" value="{{ $clearance->destinationFingerprint($record) }}">
                    <label class="form-label text-xs font-bold text-text-dark m-0" for="deliveryAddressStatus">
                        <i class="fa-solid fa-stamp text-primary mr-1"></i> Staff review of the exact destination above
                    </label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <div class="relative flex-1">
                            <select id="deliveryAddressStatus" name="status" class="form-select text-xs appearance-none pr-9 py-2 rounded-lg" required>
                                <option value="">Select a review outcome...</option>
                                <option value="verified">I reviewed and verified this delivery destination</option>
                                <option value="needs_review">Destination needs further review — block delivery</option>
                            </select>
                            <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#777]"></i>
                        </div>
                        <button class="btn btn-primary btn-sm shrink-0 flex items-center gap-1.5 justify-center" type="submit">
                            <i class="fa-solid fa-stamp"></i>
                            <span>Record destination review</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <!-- Panel 2: Recipient Readiness -->
        <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-4.5 space-y-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white border border-[#e2ddd7] text-primary shadow-xs">
                        <i class="fa-solid fa-user-check text-xs"></i>
                    </span>
                    <h4 class="font-primary font-bold text-sm text-text-dark m-0">Recipient readiness</h4>
                </div>
                <div class="shrink-0">
                    @if ($recipientReady)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-completed-bg px-2.5 py-0.5 text-xs font-semibold text-completed-fg border border-completed-fg shrink-0 whitespace-nowrap">
                            <i class="fa-solid fa-circle-check text-xs"></i> Availability confirmed for current agreement
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 border border-amber-300 shrink-0 whitespace-nowrap">
                            <i class="fa-solid fa-triangle-exclamation text-xs text-amber-600"></i> Current readiness not established
                        </span>
                    @endif
                </div>
            </div>

            <!-- Readiness Status Box: matching Application Destination Address box -->
            <div class="rounded-lg bg-white border border-[#e2ddd7] p-3.5 shadow-xs">
                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777] mb-1">
                    <i class="fa-solid fa-user-check text-primary"></i>
                    <span>Adopter Availability Status</span>
                </div>
                <p class="text-sm font-semibold {{ $recipientReady ? 'text-completed-fg' : 'text-text-dark' }} whitespace-pre-line break-words m-0">
                    {{ $recipientReady ? 'Availability confirmed for the current delivery agreement' : 'Current readiness not established' }}
                </p>
                <p class="text-xs text-text-muted mt-1.5 m-0">Direct adopter contact is required before dispatch to confirm they will be available to receive the pet.</p>
            </div>

            <!-- Metadata Cards: 2x2 Grid mirroring Panel 1 -->
            <dl class="grid gap-3 sm:grid-cols-2 text-sm m-0 p-0">
                <div class="rounded-lg bg-white border border-[#e2ddd7] p-3 flex flex-col justify-between min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777] flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-[#9e9e9e]"></i>
                        <span>Readiness status</span>
                    </dt>
                    <dd class="text-xs font-semibold mt-2 m-0 flex items-center gap-1.5 {{ $recipientReady ? 'text-completed-fg' : 'text-amber-800' }}">
                        <i class="{{ $recipientReady ? 'fa-solid fa-circle-check text-completed-fg' : 'fa-solid fa-triangle-exclamation text-amber-600' }}"></i>
                        <span>{{ $recipientReady ? 'Availability confirmed' : 'Current readiness not established' }}</span>
                    </dd>
                </div>

                <div class="rounded-lg bg-white border border-[#e2ddd7] p-3 flex flex-col justify-between min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777] flex items-center gap-1.5">
                        <i class="fa-solid fa-user-shield text-[#9e9e9e]"></i>
                        <span>Last contacting staff</span>
                    </dt>
                    <dd class="text-xs font-semibold text-text-dark mt-2 m-0 flex items-center gap-1.5">
                        <i class="fa-solid fa-user text-[#9e9e9e]"></i>
                        <span>{{ $record->deliveryContactedBy?->full_name ?? 'Not recorded' }}</span>
                    </dd>
                </div>

                <div class="rounded-lg bg-white border border-[#e2ddd7] p-3 flex flex-col justify-between min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777] flex items-center gap-1.5">
                        <i class="fa-regular fa-clock text-[#9e9e9e]"></i>
                        <span>Last contact (Asia/Manila)</span>
                    </dt>
                    <dd class="text-xs font-semibold text-text-dark mt-2 m-0 flex items-center gap-1.5">
                        <i class="fa-regular fa-calendar text-[#9e9e9e]"></i>
                        <span>{{ \App\Support\ManilaTime::parseAndFormat($record->delivery_contacted_at, 'M j, Y g:i A', 'Not recorded') }}</span>
                    </dd>
                </div>

                <div class="rounded-lg bg-white border border-[#e2ddd7] p-3 flex flex-col justify-between min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777] flex items-center gap-1.5">
                        <i class="fa-solid fa-phone text-[#9e9e9e]"></i>
                        <span>Contact method</span>
                    </dt>
                    <dd class="text-xs font-semibold text-text-dark mt-2 m-0 flex items-center gap-1.5">
                        <i class="fa-solid fa-headset text-[#9e9e9e]"></i>
                        <span>{{ \App\Services\HandoverDeliveryClearanceService::CONTACT_METHODS[$record->delivery_contact_method] ?? 'Not recorded' }}</span>
                    </dd>
                </div>
            </dl>

            @if ($record->delivery_readiness_notes)
                <div class="rounded-lg bg-amber-50/70 border border-amber-200/80 p-3 text-xs">
                    <span class="font-bold text-amber-900 block mb-0.5"><i class="fa-solid fa-note-sticky mr-1 text-amber-700"></i> Contact notes:</span>
                    <p class="text-text-dark whitespace-pre-line break-words m-0">{{ $record->delivery_readiness_notes }}</p>
                </div>
            @endif

            @if ($editable)
                <form method="POST" action="{{ route('admin.handover.delivery.readiness', $record) }}" class="border-t border-[#e2ddd7] pt-3.5 space-y-3">
                    @csrf
                    <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                    <input type="hidden" name="clearance_version" value="{{ $record->delivery_clearance_version }}">
                    <input type="hidden" name="destination_fingerprint" value="{{ $clearance->destinationFingerprint($record) }}">
                    <input type="hidden" name="recipient" value="adopter">

                    <p class="text-xs text-text-muted m-0"><i class="fa-solid fa-circle-info mr-1 text-primary"></i> Record actual direct contact with the adopter, not an unanswered email or automated reminder. Third-party recipient authorization is not supported here.</p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="deliveryContactMethod" class="form-label text-xs font-bold text-text-dark">Contact method</label>
                            <div class="relative">
                                <select id="deliveryContactMethod" name="contact_method" class="form-select text-xs appearance-none pr-9 py-2 rounded-lg" required>
                                    <option value="">Select contact method</option>
                                    @foreach (\App\Services\HandoverDeliveryClearanceService::CONTACT_METHODS as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#777]"></i>
                            </div>
                        </div>

                        <div>
                            <label for="deliveryAvailable" class="form-label text-xs font-bold text-text-dark">Did the adopter explicitly confirm availability for this delivery?</label>
                            <div class="relative">
                                <select id="deliveryAvailable" name="available" class="form-select text-xs appearance-none pr-9 py-2 rounded-lg" required>
                                    <option value="">Select confirmed outcome</option>
                                    <option value="1">Yes — the adopter confirmed availability</option>
                                    <option value="0">No — the adopter is not available</option>
                                </select>
                                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#777]"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="deliveryReadinessNotes" class="form-label text-xs font-bold text-text-dark">Contact notes (required for other contact methods; no unnecessary sensitive details)</label>
                        <textarea id="deliveryReadinessNotes" name="notes" maxlength="1000" rows="2" class="form-control text-xs rounded-lg" placeholder="Enter contact details or confirmation observations..."></textarea>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pt-1">
                        <button class="btn btn-primary btn-sm flex items-center gap-1.5 justify-center" type="submit" @disabled(!$addressVerified || !$confirmedDelivery || !$notExpired)>
                            <i class="fa-solid fa-eye"></i>
                            <span>Record contact and availability</span>
                        </button>
                        <p class="text-[11px] text-[#777] m-0">Verify the destination and obtain a confirmed, nonexpired delivery schedule first. Confirming availability creates a pre-dispatch notice; it does not record courier booking, release or prove email delivery.</p>
                    </div>
                </form>
            @endif
        </div>

        <!-- Panel 3: Delivery Release Eligibility Checklist -->
        @if (!$record->released_at && !$record->adopter_outcome)
            <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-4.5 space-y-3.5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white border border-[#e2ddd7] text-primary shadow-xs">
                            <i class="fa-solid fa-list-check text-xs"></i>
                        </span>
                        <h4 class="font-primary font-bold text-sm text-text-dark m-0">Delivery release eligibility</h4>
                    </div>
                    <div class="shrink-0">
                        @if ($allCleared && $confirmedDelivery && $notExpired && $interviewVerified)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-completed-bg px-2.5 py-0.5 text-xs font-semibold text-completed-fg border border-completed-fg shrink-0 whitespace-nowrap">
                                <i class="fa-solid fa-circle-check text-xs"></i> Ready for courier dispatch
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 border border-amber-300 shrink-0 whitespace-nowrap">
                                <i class="fa-solid fa-triangle-exclamation text-xs text-amber-600"></i> Requirements pending
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-2.5 sm:grid-cols-2 text-xs">
                    <!-- Item 1: Address verification -->
                    <div class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $addressVerified ? 'border-completed-fg/30 bg-completed-bg/20' : 'border-amber-200 bg-amber-50/30' }}">
                        <i class="{{ $addressVerified ? 'fa-solid fa-circle-check text-completed-fg mt-0.5' : 'fa-solid fa-circle-xmark text-amber-600 mt-0.5' }}"></i>
                        <div>
                            <span class="font-bold text-text-dark block">Address verification</span>
                            <span class="{{ $addressVerified ? 'text-completed-fg font-semibold' : 'text-amber-800' }}">{{ $addressVerified ? 'complete' : 'required' }}</span>
                        </div>
                    </div>

                    <!-- Item 2: Recipient readiness -->
                    <div class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $recipientReady ? 'border-completed-fg/30 bg-completed-bg/20' : 'border-amber-200 bg-amber-50/30' }}">
                        <i class="{{ $recipientReady ? 'fa-solid fa-circle-check text-completed-fg mt-0.5' : 'fa-solid fa-circle-xmark text-amber-600 mt-0.5' }}"></i>
                        <div>
                            <span class="font-bold text-text-dark block">Current recipient readiness</span>
                            <span class="{{ $recipientReady ? 'text-completed-fg font-semibold' : 'text-amber-800' }}">{{ $recipientReady ? 'confirmed' : 'required' }}</span>
                        </div>
                    </div>

                    <!-- Item 3: Adopter-confirmed schedule -->
                    <div class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $confirmedDelivery ? 'border-completed-fg/30 bg-completed-bg/20' : 'border-amber-200 bg-amber-50/30' }}">
                        <i class="{{ $confirmedDelivery ? 'fa-solid fa-circle-check text-completed-fg mt-0.5' : 'fa-solid fa-circle-xmark text-amber-600 mt-0.5' }}"></i>
                        <div>
                            <span class="font-bold text-text-dark block">Adopter-confirmed delivery schedule</span>
                            <span class="{{ $confirmedDelivery ? 'text-completed-fg font-semibold' : 'text-amber-800' }}">{{ $confirmedDelivery ? 'confirmed' : 'required' }}</span>
                        </div>
                    </div>

                    <!-- Item 4: Schedule end window -->
                    <div class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $notExpired ? 'border-completed-fg/30 bg-completed-bg/20' : 'border-amber-200 bg-amber-50/30' }}">
                        <i class="{{ $notExpired ? 'fa-solid fa-circle-check text-completed-fg mt-0.5' : 'fa-solid fa-circle-xmark text-amber-600 mt-0.5' }}"></i>
                        <div>
                            <span class="font-bold text-text-dark block">Schedule end window</span>
                            <span class="{{ $notExpired ? 'text-completed-fg font-semibold' : 'text-amber-800' }}">{{ $notExpired ? 'not expired (early release allowed)' : 'nonexpired window required' }}</span>
                        </div>
                    </div>

                    <!-- Item 5: Identity verification -->
                    <div class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $interviewVerified ? 'border-completed-fg/30 bg-completed-bg/20' : 'border-amber-200 bg-amber-50/30' }}">
                        <i class="{{ $interviewVerified ? 'fa-solid fa-circle-check text-completed-fg mt-0.5' : 'fa-solid fa-circle-xmark text-amber-600 mt-0.5' }}"></i>
                        <div>
                            <span class="font-bold text-text-dark block">Interview identity verification</span>
                            <span class="{{ $interviewVerified ? 'text-completed-fg font-semibold' : 'text-amber-800' }}">{{ $interviewVerified ? 'complete' : 'required' }}</span>
                        </div>
                    </div>

                    <!-- Item 6: Courier requirements -->
                    @php
                        $courierConfigured = !empty($record->courier) && !empty($record->tracking_reference);
                    @endphp
                    <div class="flex items-start gap-2.5 p-2.5 rounded-lg border {{ $courierConfigured ? 'border-completed-fg/30 bg-completed-bg/20' : 'border-amber-200 bg-amber-50/30' }}">
                        <i class="{{ $courierConfigured ? 'fa-solid fa-circle-check text-completed-fg mt-0.5' : 'fa-solid fa-circle-xmark text-amber-600 mt-0.5' }}"></i>
                        <div>
                            <span class="font-bold text-text-dark block">Courier & tracking link</span>
                            <span class="{{ $courierConfigured ? 'text-completed-fg font-semibold' : 'text-amber-800' }}">{{ $courierConfigured ? 'configured' : 'required at release' }}</span>
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-[#777] m-0 flex items-center gap-1.5 pt-1 border-t border-[#e2ddd7]">
                    <i class="fa-solid fa-circle-info text-primary text-xs shrink-0"></i>
                    <span>Select an approved courier provider, booking reference, and valid HTTPS tracking link in the release form below. The server validates all three upon submission.</span>
                </p>
            </div>
        @else
            <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3 text-xs text-[#777] flex items-center gap-2.5">
                <i class="fa-solid fa-clock-rotate-left text-primary text-sm shrink-0"></i>
                <span>Historical record: missing clearance is not fabricated and does not change an already recorded release or receipt.</span>
            </div>
        @endif
    @endif
</section>
