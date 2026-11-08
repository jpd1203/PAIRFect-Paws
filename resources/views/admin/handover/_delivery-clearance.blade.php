@php
    $clearance = app(\App\Services\HandoverDeliveryClearanceService::class);
    $delivery = $record->scheduled_method === 'delivery' || $record->release_method === 'delivery';
    $addressVerified = $clearance->addressIsVerified($record);
    $recipientReady = $clearance->readinessIsCurrent($record);
    $editable = $record->scheduled_method === 'delivery' && !$record->released_at && !$record->adopter_outcome && !$record->received_at;
    $confirmedDelivery = $clearance->scheduleIsConfirmed($record);
    $notExpired = $record->scheduled_end_at && \App\Support\ManilaTime::now()->lte(\App\Support\ManilaTime::at($record->scheduled_end_at));
    $interviewVerified = $record->application && app(\App\Services\IdentityVerificationService::class)->isVerified($record->application);
@endphp

<section class="rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card" aria-labelledby="deliveryClearanceHeading">
    <h3 id="deliveryClearanceHeading" class="font-primary font-bold text-base text-text-dark mb-3">Delivery Verification / Pre-Dispatch Readiness</h3>
    @if (!$delivery)
        <p class="text-sm text-text-muted">Delivery-address verification and pre-dispatch readiness are not required for shelter pickup. Pickup identity and schedule requirements still apply.</p>
    @else
        <h4 class="font-bold text-sm">Delivery destination</h4>
        <p class="text-sm whitespace-pre-line break-words">{{ $record->adopter_address ?: 'No delivery destination recorded. Resolve the approved application record with shelter staff.' }}</p>
        <p class="text-xs text-text-muted">This is the handover's approved-application snapshot, not the adopter's current profile address.</p>
        <dl class="grid gap-2 sm:grid-cols-2 text-sm my-3">
            <div><dt class="font-semibold">Verification status</dt><dd>{{ $addressVerified ? 'Verified for this destination' : 'Not verified for this destination' }}</dd></div>
            <div><dt class="font-semibold">Last reviewing staff</dt><dd>{{ $record->deliveryAddressVerifiedBy?->full_name ?? 'Not recorded' }}</dd></div>
            <div><dt class="font-semibold">Last review (Asia/Manila)</dt><dd>{{ \App\Support\ManilaTime::parseAndFormat($record->delivery_address_verified_at, 'M j, Y g:i A', 'Not recorded') }}</dd></div>
        </dl>
        @if ($editable)
            <form method="POST" action="{{ route('admin.handover.delivery.address', $record) }}" class="space-y-3 border-t py-3">
                @csrf
                <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                <input type="hidden" name="clearance_version" value="{{ $record->delivery_clearance_version }}">
                <input type="hidden" name="destination_fingerprint" value="{{ $clearance->destinationFingerprint($record) }}">
                <label class="form-label" for="deliveryAddressStatus">Staff review of the exact destination above</label>
                <div class="relative">
                    <select id="deliveryAddressStatus" name="status" class="form-select appearance-none pr-10" required>
                        <option value="">Select a review outcome</option>
                        <option value="verified">I reviewed and verified this delivery destination</option>
                        <option value="needs_review">Destination needs further review — block delivery</option>
                    </select>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-[#777]"></i>
                </div>
                <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-eye"></i>Record destination review</button>
            </form>
        @endif

        <h4 class="font-bold text-sm mt-4">Recipient readiness</h4>
        <p class="text-sm font-semibold">{{ $recipientReady ? 'Availability confirmed for the current delivery agreement' : 'Current readiness not established' }}</p>
        <dl class="grid gap-2 sm:grid-cols-2 text-sm my-3">
            <div><dt class="font-semibold">Contact method</dt><dd>{{ \App\Services\HandoverDeliveryClearanceService::CONTACT_METHODS[$record->delivery_contact_method] ?? 'Not recorded' }}</dd></div>
            <div><dt class="font-semibold">Contacted by</dt><dd>{{ $record->deliveryContactedBy?->full_name ?? 'Not recorded' }}</dd></div>
            <div><dt class="font-semibold">Contact time (Asia/Manila)</dt><dd>{{ \App\Support\ManilaTime::parseAndFormat($record->delivery_contacted_at, 'M j, Y g:i A', 'Not recorded') }}</dd></div>
            <div><dt class="font-semibold">Last recorded availability</dt><dd>{{ $record->delivery_recipient_available === null ? 'Not confirmed' : ($record->delivery_recipient_available ? 'Available (subject to current agreement checks)' : 'Not available — do not dispatch') }}</dd></div>
        </dl>
        @if ($record->delivery_readiness_notes)
            <p class="text-sm whitespace-pre-line break-words">{{ $record->delivery_readiness_notes }}</p>
        @endif
        @if ($editable)
            <form method="POST" action="{{ route('admin.handover.delivery.readiness', $record) }}" class="space-y-3 border-t py-3">
                @csrf
                <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                <input type="hidden" name="clearance_version" value="{{ $record->delivery_clearance_version }}">
                <input type="hidden" name="destination_fingerprint" value="{{ $clearance->destinationFingerprint($record) }}">
                <input type="hidden" name="recipient" value="adopter">
                <p class="text-xs text-text-muted">Record actual direct contact with the adopter, not an unanswered email or automated reminder. Third-party recipient authorization is not supported here.</p>
                <label for="deliveryContactMethod" class="form-label">Contact method</label>
                <div class="relative">
                    <select id="deliveryContactMethod" name="contact_method" class="form-select appearance-none pr-10" required>
                        <option value="">Select contact method</option>
                        @foreach (\App\Services\HandoverDeliveryClearanceService::CONTACT_METHODS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-[#777]"></i>
                </div>
                <label for="deliveryAvailable" class="form-label">Did the adopter explicitly confirm availability for this delivery?</label>
                <div class="relative">
                    <select id="deliveryAvailable" name="available" class="form-select appearance-none pr-10" required>
                        <option value="">Select confirmed outcome</option>
                        <option value="1">Yes — the adopter confirmed availability</option>
                        <option value="0">No — the adopter is not available</option>
                    </select>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-[#777]"></i>
                </div>
                <label for="deliveryReadinessNotes" class="form-label">Contact notes (required for other contact methods; no unnecessary sensitive details)</label>
                <textarea id="deliveryReadinessNotes" name="notes" maxlength="1000" rows="2" class="form-control"></textarea>
                <button class="btn btn-primary" type="submit" @disabled(!$addressVerified || !$confirmedDelivery || !$notExpired)><i class="fa-solid fa-eye"></i>Record contact and availability</button>
                <p class="text-xs text-text-muted">Verify the destination and obtain a confirmed, nonexpired delivery schedule first. Confirming availability creates a pre-dispatch notice; it does not record courier booking, release or prove email delivery.</p>
            </form>
        @endif

        @if (!$record->released_at && !$record->adopter_outcome)
            <h4 class="font-bold text-sm mt-4">Delivery release eligibility</h4>
            <ul class="text-sm list-disc pl-5 space-y-1">
                <li>Address verification: {{ $addressVerified ? 'complete' : 'required' }}</li>
                <li>Current recipient readiness: {{ $recipientReady ? 'confirmed' : 'required' }}</li>
                <li>Adopter-confirmed delivery schedule: {{ $confirmedDelivery ? 'confirmed' : 'required' }}</li>
                <li>Schedule end: {{ $notExpired ? 'not expired (early release remains allowed)' : 'establish a nonexpired window' }}</li>
                <li>Interview identity verification: {{ $interviewVerified ? 'complete' : 'required' }}</li>
                <li>Courier: select an approved provider, booking reference and HTTPS tracking link in the release form. The server validates all three on submission.</li>
            </ul>
        @else
            <p class="text-xs text-text-muted mt-3">Historical record: missing clearance is not fabricated and does not change an already recorded release or receipt.</p>
        @endif
    @endif
</section>
