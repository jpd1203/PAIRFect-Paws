@php
    $clearance = $uiClearance;
    $addressVerified = $uiAddressVerified;
    $recipientReady = $uiRecipientReady;
    $editable = $record->scheduled_method === 'delivery' && $uiUnreleased;
    $confirmedDelivery = $clearance->scheduleIsConfirmed($record);
    $notExpired = $record->scheduled_end_at && !$uiExpired;
    $readinessAttempt = old('recipient') === 'adopter';
    $addressErrors = !$readinessAttempt && ($errors->has('delivery_address') || (old('clearance_version') !== null && $errors->any()));
    $readinessErrors = $readinessAttempt && $errors->any();
    $readinessLabel = $recipientReady ? 'Availability confirmed for the current delivery agreement'
        : ($record->delivery_recipient_available === false && $record->delivery_contacted_at ? 'Adopter reported unavailable (last recorded contact)' : 'Current readiness not established');
    $readinessPrerequisites = [];
    if (!$addressVerified) $readinessPrerequisites[] = 'Verify the current delivery destination using Record destination review above.';
    if (!$confirmedDelivery) $readinessPrerequisites[] = 'Establish a delivery schedule and have the adopter confirm it on Handover Status.';
    elseif (!$notExpired) $readinessPrerequisites[] = 'The confirmed delivery window has expired. Establish a new schedule and obtain adopter confirmation.';
    $requirements = [
        'Address verified' => $addressVerified, 'Adopter availability confirmed' => $recipientReady,
        'Schedule confirmed' => $confirmedDelivery, 'Nonexpired schedule' => (bool) $notExpired,
        'Interview identity verified' => $uiInterviewVerified,
        'Courier and tracking information' => filled($record->courier) && filled($record->tracking_number) && filled($record->tracking_url),
    ];
@endphp
@if($uiDelivery)
<section class="rounded-card border border-[#e2ddd7] bg-white p-4 sm:p-5 shadow-card space-y-4 min-w-0" aria-labelledby="deliveryClearanceHeading">
    <h3 id="deliveryClearanceHeading" class="font-bold text-base m-0">Delivery Readiness</h3>
    <div id="deliveryReadiness" class="scroll-mt-20" tabindex="-1">
    <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm m-0">
        <div><dt class="inline">Destination:</dt> <dd class="inline font-semibold {{ $addressVerified ? 'text-completed-fg' : 'text-amber-800' }}">{{ $addressVerified ? 'Verified for this destination' : 'Verification required' }}</dd></div>
        <div><dt class="inline">Adopter Availability:</dt> <dd class="inline font-semibold {{ $recipientReady ? 'text-completed-fg' : 'text-amber-800' }}">{{ $recipientReady ? 'Confirmed' : ($record->delivery_recipient_available === false ? 'Unavailable' : 'Confirmation required') }}</dd></div>
    </dl>
    @if(session('delivery_readiness_recorded'))<p class="modal-note mt-3" role="status">{{ session('success') }}</p>@endif
    </div>
    @if(!$recipientReady && $record->delivery_recipient_available === false)
        <p class="modal-note caution text-sm m-0" role="status">Adopter reported unavailable. Delivery release remains blocked.</p>
    @elseif(!$recipientReady && $record->delivery_recipient_available === true)
        <p class="modal-note caution text-sm m-0">The previous availability confirmation no longer covers the current agreement. Contact the adopter again.</p>
    @endif
    <details id="deliveryVerificationDetails" class="text-sm">
        <summary class="cursor-pointer font-semibold">View Verification Details</summary>
        <div class="mt-3 space-y-3 break-words">
            <p class="m-0 font-semibold">Approved delivery address</p>
            <p class="whitespace-pre-line m-0">{{ $record->adopter_address ?: 'No delivery destination recorded. Resolve the approved application record with shelter staff.' }}</p>
            <p class="text-xs text-text-muted m-0">Approved application snapshot, not the current profile address.</p>
            <dl class="grid sm:grid-cols-2 gap-3 text-xs m-0">
                <div><dt>Last reviewing staff</dt><dd class="m-0 font-semibold">{{ $record->deliveryAddressVerifiedBy?->full_name ?? 'Not recorded' }}</dd></div>
                <div><dt>Last review (Asia/Manila)</dt><dd class="m-0">{{ \App\Support\ManilaTime::parseAndFormat($record->delivery_address_verified_at, 'M j, Y g:i A', 'Not recorded') }}</dd></div>
                <div><dt>Last contacting staff</dt><dd class="m-0 font-semibold">{{ $record->deliveryContactedBy?->full_name ?? 'Not recorded' }}</dd></div>
                <div><dt>Last contact (Asia/Manila)</dt><dd class="m-0">{{ \App\Support\ManilaTime::parseAndFormat($record->delivery_contacted_at, 'M j, Y g:i A', 'Not recorded') }}</dd></div>
                <div><dt>Contact method</dt><dd class="m-0">{{ \App\Services\HandoverDeliveryClearanceService::CONTACT_METHODS[$record->delivery_contact_method] ?? 'Not recorded' }}</dd></div>
                <div><dt>Last availability record</dt><dd class="m-0">{{ $readinessLabel }}</dd></div>
            </dl>
            @if($record->delivery_readiness_notes)<p class="whitespace-pre-line m-0">{{ $record->delivery_readiness_notes }}</p>@endif
        </div>
    </details>
    @if($editable)
        <details id="deliveryAddressReview" @if(!$addressVerified || $addressErrors) open @endif class="border-t border-[#e2ddd7] pt-3">
            <summary class="cursor-pointer font-semibold text-sm">{{ $addressVerified ? 'Update Verification' : 'Verify delivery destination' }}</summary>
            <p class="text-sm whitespace-pre-line break-words mt-3">{{ $record->adopter_address ?: 'No approved delivery address recorded.' }}</p>
            <form method="POST" action="{{ route('admin.handover.delivery.address', $record) }}" class="space-y-3">@csrf
                <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                <input type="hidden" name="clearance_version" value="{{ $record->delivery_clearance_version }}">
                <input type="hidden" name="destination_fingerprint" value="{{ $clearance->destinationFingerprint($record) }}">
                <label for="deliveryAddressStatus" class="form-label">Destination review outcome</label>
                <select id="deliveryAddressStatus" name="status" class="form-select" required aria-invalid="{{ $addressErrors ? 'true' : 'false' }}" aria-describedby="deliveryAddressError">
                    <option value="">Select a review outcome...</option>
                    <option value="verified" @selected($addressErrors && old('status') === 'verified')>I reviewed and verified this delivery destination</option>
                    <option value="needs_review" @selected($addressErrors && old('status') === 'needs_review')>Destination needs further review — block delivery</option>
                </select>
                <p id="deliveryAddressError" class="text-xs text-red-700 m-0" role="alert">{{ $addressErrors ? ($errors->first('status') ?: $errors->first('delivery_address') ?: $errors->first()) : '' }}</p>
                <button type="submit" class="btn btn-primary">Record destination review</button>
            </form>
        </details>
        <details id="deliveryContactUpdate" @if(!$recipientReady || $readinessErrors) open @endif class="border-t border-[#e2ddd7] pt-3 scroll-mt-20">
            <summary class="cursor-pointer font-semibold text-sm">{{ $recipientReady ? 'Update contact and availability' : 'Confirm adopter availability' }}</summary>
            @if($readinessErrors)
                <div class="modal-note caution mt-3" role="alert" id="deliveryReadinessErrors"><p class="font-semibold m-0">Contact and availability were not recorded. Review the following:</p><ul class="list-disc pl-5 m-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('admin.handover.delivery.readiness', $record) }}" class="space-y-3 mt-3 min-w-0">@csrf
                <input type="hidden" name="schedule_version" value="{{ $record->schedule_version }}">
                <input type="hidden" name="clearance_version" value="{{ $record->delivery_clearance_version }}">
                <input type="hidden" name="destination_fingerprint" value="{{ $clearance->destinationFingerprint($record) }}">
                <input type="hidden" name="recipient" value="adopter">
                <p class="text-xs text-text-muted">Record actual direct contact with the adopter, not an unanswered email or automated reminder. Third-party recipient authorization is not supported here.</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="min-w-0">
                        <label for="deliveryContactMethod" class="form-label">Contact method</label>
                        <select id="deliveryContactMethod" name="contact_method" class="form-select" required aria-invalid="{{ $readinessAttempt && $errors->has('contact_method') ? 'true' : 'false' }}" aria-describedby="deliveryContactMethodError">
                            <option value="">Select contact method</option>
                            @foreach(\App\Services\HandoverDeliveryClearanceService::CONTACT_METHODS as $key => $label)<option value="{{ $key }}" @selected($readinessAttempt && old('contact_method') === $key)>{{ $label }}</option>@endforeach
                        </select>
                        <p id="deliveryContactMethodError" class="text-xs text-red-700 m-0">{{ $readinessAttempt ? $errors->first('contact_method') : '' }}</p>
                    </div>
                    <div class="min-w-0">
                        <label for="deliveryAvailable" class="form-label">Adopter available for this delivery?</label>
                        <select id="deliveryAvailable" name="available" class="form-select" required aria-invalid="{{ $readinessAttempt && $errors->has('available') ? 'true' : 'false' }}" aria-describedby="deliveryAvailableError">
                            <option value="">Select confirmed outcome</option>
                            <option value="1" @selected($readinessAttempt && (string) old('available') === '1')>Yes — the adopter confirmed availability</option>
                            <option value="0" @selected($readinessAttempt && (string) old('available') === '0')>No — the adopter is not available</option>
                        </select>
                        <p id="deliveryAvailableError" class="text-xs text-red-700 m-0">{{ $readinessAttempt ? $errors->first('available') : '' }}</p>
                    </div>
                </div>
                <div>
                    <label for="deliveryReadinessNotes" class="form-label">Contact notes (required for Other)</label>
                    <textarea id="deliveryReadinessNotes" name="notes" rows="2" maxlength="1000" class="form-control" aria-invalid="{{ $readinessAttempt && $errors->has('notes') ? 'true' : 'false' }}" aria-describedby="deliveryReadinessNotesError">{{ $readinessAttempt ? old('notes') : '' }}</textarea>
                    <p id="deliveryReadinessNotesError" class="text-xs text-red-700 m-0">{{ $readinessAttempt ? $errors->first('notes') : '' }}</p>
                </div>
                @if($readinessPrerequisites)<div id="deliveryReadinessPrerequisites" class="modal-note caution"><p class="font-semibold m-0">Before you can record contact and availability:</p><ul class="list-disc pl-5 m-0">@foreach($readinessPrerequisites as $prerequisite)<li>{{ $prerequisite }}</li>@endforeach</ul></div>@endif
                <button type="submit" class="btn btn-primary" @disabled(!$addressVerified || !$confirmedDelivery || !$notExpired) @if($readinessPrerequisites) aria-describedby="deliveryReadinessPrerequisites" @endif>Record contact and availability</button>
                <p class="text-xs text-text-muted">Availability records a pre-dispatch notice, not release or proof of email delivery.</p>
            </form>
        </details>
    @endif
    @if($uiUnreleased)
        <div class="border-t border-[#e2ddd7] pt-3 text-sm space-y-2">
            <p class="font-semibold m-0">Release requirements: {{ count(array_filter($requirements)) }} of {{ count($requirements) }} on record</p>
            <ul class="list-disc pl-5 m-0">@foreach($requirements as $label => $complete)@if(!$complete)<li>{{ $label }}{{ $label === 'Courier and tracking information' ? ' — enter approved provider, booking reference and HTTPS link in the release form.' : ' required.' }}</li>@endif @endforeach</ul>
            <details><summary class="cursor-pointer font-semibold">View All Requirements</summary><ul class="list-none pl-0 mt-2">@foreach($requirements as $label => $complete)<li><i aria-hidden="true" class="fa-solid {{ $complete ? 'fa-check text-completed-fg' : 'fa-clock text-amber-700' }} mr-1"></i>{{ $label }}: {{ $complete ? 'Complete' : 'Required' }}</li>@endforeach</ul></details>
            <p class="text-xs text-text-muted m-0">Verification alone does not authorize release. All safeguards are checked again on submission; early release remains allowed for a confirmed, nonexpired window.</p>
        </div>
    @endif
</section>
@elseif($record->scheduled_method === 'pickup')
    <p class="text-xs text-text-muted">Delivery verification is not required for shelter pickup.</p>
@endif
