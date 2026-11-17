@extends('admin.layouts.app')

@section('title', ($record->pet?->name ?? 'Pet') . ' Handover & Release - PAIRfect Paws Admin')

@section('content')

    @php
        // Read-only presentation of existing safeguards; release is still validated server-side.
        $uiClearance = app(\App\Services\HandoverDeliveryClearanceService::class);
        $uiIdentity = app(\App\Services\IdentityVerificationService::class);
        $uiIncidentService = app(\App\Services\HandoverTransportIncidentService::class);
        $uiDelivery = $record->scheduled_method === 'delivery' || $record->release_method === 'delivery';
        $uiUnreleased = !$record->released_at && !$record->adopter_outcome && !$record->received_at;
        $uiApproved = $record->application?->status === \App\Enums\ApplicationStatus::Approved;
        $uiConfirmed = in_array($record->scheduled_method, ['pickup', 'delivery'], true)
            && $record->schedule_status === 'confirmed' && $record->scheduled_start_at && $record->scheduled_end_at
            && $record->schedule_confirmed_at && $record->user_id
            && (int) $record->schedule_confirmed_by_user_id === (int) $record->user_id;
        $uiExpired = $record->scheduled_end_at && \App\Support\ManilaTime::now()->gt(\App\Support\ManilaTime::at($record->scheduled_end_at));
        $uiAddressVerified = $uiClearance->addressIsVerified($record);
        $uiRecipientReady = $uiClearance->readinessIsCurrent($record);
        $uiInterviewVerified = $record->application && $uiIdentity->isVerified($record->application);
        $uiPickupVerified = $record->application && $uiIdentity->isVerified($record->application, 'pickup_handover', $record->reopen_count);
        $uiOpenCases = $record->transportIncidents->where('status', '!=', 'resolved');
        $uiOverdueReceipt = $uiIncidentService->isOverdue($record);
        $uiOverdueFollowUp = $uiOpenCases->contains(fn ($case) => $case->next_follow_up_at?->lte(now()));
        $uiTransportAlert = $uiOpenCases->isNotEmpty() || $uiOverdueReceipt
            || $record->adopter_outcome === 'not_received'
            || ($record->follow_up_flagged_at && !$record->received_at && $record->released_at);
        $uiReleaseBlockers = [];
        if (!$uiApproved) $uiReleaseBlockers[] = 'An approved application is required.';
        if (!$uiConfirmed) $uiReleaseBlockers[] = 'Obtain adopter confirmation of the handover schedule.';
        if ($uiExpired) $uiReleaseBlockers[] = 'The handover window has expired. Propose a new schedule and obtain adopter confirmation.';
        if (!$uiInterviewVerified) $uiReleaseBlockers[] = 'Complete interview identity verification.';
        if ($record->scheduled_method === 'pickup' && !$uiPickupVerified) $uiReleaseBlockers[] = 'Complete final pickup identity verification.';
        if ($record->scheduled_method === 'delivery' && !$uiAddressVerified) $uiReleaseBlockers[] = 'Verify the current delivery destination.';
        if ($record->scheduled_method === 'delivery' && !$uiRecipientReady) $uiReleaseBlockers[] = 'Confirm adopter availability for the current agreement.';
        $uiActionTarget = 'releaseFormSection';
        $uiActionLabel = 'View release form';
        if ($uiTransportAlert) {
            $uiNextAction = $uiOverdueReceipt ? 'Overdue delivery receipt. Contact the adopter and courier and review the investigation.'
                : ($record->adopter_outcome === 'not_received' ? 'Adopter reported the pet was not received. Review the investigation.'
                : ($uiOverdueFollowUp ? 'Review the transport concern: an investigation follow-up is overdue.' : 'Review the transport concern and arrange the required follow-up.'));
            $uiActionTarget = 'transportInvestigations'; $uiActionLabel = 'Review investigation & follow-up';
        } elseif ($record->adopter_outcome === 'received') {
            $uiNextAction = 'Receipt confirmed. Post-adoption monitoring is active.';
            $uiActionTarget = 'adopterConfirmationSection'; $uiActionLabel = 'View receipt confirmation';
        } elseif ($record->released_at) {
            $uiNextAction = 'Awaiting adopter receipt confirmation.';
            $uiActionTarget = 'adopterConfirmationSection'; $uiActionLabel = 'View confirmation and reminders';
        } elseif (!$uiApproved) {
            $uiNextAction = 'Review application approval before handover.';
            $uiActionTarget = 'releaseFormSection'; $uiActionLabel = 'View release requirements';
        } elseif ($uiExpired) {
            $uiNextAction = ($uiDelivery ? 'Delivery' : 'Pickup').' window expired. Propose a new schedule and obtain adopter confirmation before release.';
            $uiActionTarget = 'scheduleProposal'; $uiActionLabel = 'Propose a new schedule';
        } elseif ($record->reschedule_status === 'pending') {
            $uiNextAction = "Review the adopter's reschedule request.";
            $uiActionTarget = 'rescheduleReviewSection'; $uiActionLabel = 'Review request';
        } elseif (!$uiConfirmed) {
            $uiNextAction = $record->schedule_status === 'proposed'
                ? 'Awaiting adopter confirmation of the proposed schedule.' : 'Propose a handover schedule.';
            $uiActionTarget = 'scheduleProposal'; $uiActionLabel = $record->schedule_status === 'proposed' ? 'View or update proposal' : 'Propose schedule';
        } elseif (!$uiInterviewVerified) {
            $uiNextAction = 'Complete interview identity verification.';
            $uiActionTarget = 'identityVerificationSection'; $uiActionLabel = 'View verification';
        } elseif ($uiDelivery && !$uiAddressVerified) {
            $uiNextAction = 'Verify the delivery destination.';
            $uiActionTarget = 'deliveryAddressReview'; $uiActionLabel = 'Review destination';
        } elseif ($uiDelivery && !$uiRecipientReady) {
            $uiNextAction = $record->delivery_recipient_available === false ? 'Adopter unavailable. Arrange a suitable delivery agreement and confirm availability.' : 'Confirm adopter availability.';
            $uiActionTarget = 'deliveryReadiness'; $uiActionLabel = 'Record contact and availability';
        } elseif (!$uiDelivery && !$uiPickupVerified) {
            $uiNextAction = 'Complete final pickup identity verification.';
            $uiActionTarget = 'pickupIdentityDetails'; $uiActionLabel = 'Verify pickup identity';
        } else {
            $uiNextAction = "Record the pet's release.";
        }
    @endphp
    <div data-handover-page data-validation-errors="{{ json_encode($errors->messages()) }}" data-failed-form="{{ old('_ui_form', '') }}" class="min-w-0">

    <div class="heading-text mb-5">
        <div class="flex items-center gap-2 text-sm text-[#777] mb-2 font-medium">
            <a href="{{ route('admin.handover.index') }}" class="hover:text-primary transition no-underline text-[#777]">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Handover Queue
            </a>
        </div>
        <div class="flex flex-wrap items-center gap-4 mt-2">
            <img src="{{ $record->photo_url }}" alt="{{ $record->pet?->name }}" 
                 class="h-16 w-16 rounded-xl object-cover border border-[#e2ddd7] bg-neutral-light shadow-card">
            <div>
                <h2>{{ $record->pet?->name ?? 'Pet' }} &rarr; {{ $record->adopter_name }}</h2>
                <p>{{ strtoupper($record->code) }} &middot; Approved {{ $record->approved_at ? \App\Support\ManilaTime::format($record->approved_at, 'M j, Y') : 'Recently' }} &middot; {{ $record->pet?->species ?? 'Animal' }}, {{ $record->pet?->age_group ?? 'Age' }}</p>
            </div>
            <span class="badge {{ $record->status_badge['class'] }} whitespace-normal">{{ $record->status_badge['label'] }}</span>
        </div>
    </div>

    <!-- Handover Pipeline -->
    <div class="mb-5">
        @include('admin.handover._pipeline', ['record' => $record])
    </div>

    @include('admin.handover._next-action')

    <!-- Main 2-Column Content Grid -->
    <div class="grid gap-5 lg:grid-cols-3 lg:items-start">
        
        <!-- Left Column: Release Details & Adopter Confirmation -->
        <div class="space-y-5 min-w-0 lg:col-span-2">
            @if($errors->any())
                <div class="modal-note caution" role="alert" tabindex="-1" data-handover-errors>
                    <p class="font-semibold m-0">Changes were not saved. Review the following:</p>
                    <ul class="list-disc pl-5 m-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif
            @if(session('success'))<div class="modal-note">{{ session('success') }}</div>@endif
            <h3 class="font-bold text-base m-0">Schedule and verification</h3>
            @include('admin.handover._schedule')
            @include('admin.handover._delivery-clearance')
            @if ($record->released_at)
                @include('admin.handover._recorded_release', ['record' => $record])
                @include('admin.handover._adopter_confirmation', ['record' => $record])
            @else
                @include('admin.handover._release_form', ['record' => $record])
            @endif
            @include('admin.handover._transport-investigations')
        </div>

        <!-- Right Column: Adopter Profile Card & History Timeline -->
        <div class="space-y-5 min-w-0">
            <h3 class="font-bold text-base m-0">Additional information / history</h3>
            
            <!-- Adopter Info Card -->
            <details class="bg-white border border-[#e2ddd7] rounded-card p-4 shadow-card">
                <summary class="cursor-pointer font-primary font-bold text-base text-text-dark">Adopter Details</summary>
                <div class="mt-3">
                <p class="text-base font-bold text-text-dark m-0">{{ $record->adopter_name }}</p>

                <ul class="mt-3 space-y-2.5 text-sm text-text-muted list-none p-0">
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-phone mt-1 text-[#9e9e9e] text-xs w-4"></i>
                        <span>{{ $record->adopter_phone ?: 'No phone provided' }}</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-envelope mt-1 text-[#9e9e9e] text-xs w-4"></i>
                        <span class="break-all">{{ $record->adopter_email ?: 'No email provided' }}</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-location-dot mt-1 text-[#9e9e9e] text-xs w-4"></i>
                        <span>{{ $record->adopter_address ?: 'No address provided' }}</span>
                    </li>
                    <!-- <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-route mt-1 text-[#9e9e9e] text-xs w-4"></i>
                        <span>{{ $record->adopter_distance ?: 'Distance unspecified' }}</span>
                    </li> -->
                </ul>
                </div>
            </details>

            <!-- Handover Log Timeline -->
            <details id="handoverLog" class="bg-white border border-[#e2ddd7] rounded-card p-4 shadow-card">
                <summary class="cursor-pointer font-primary font-bold text-base text-text-dark">Handover Log ({{ count($record->history ?? []) }})</summary>
                <div class="mt-4">

                @php
                    $history = array_reverse($record->history ?? []);
                @endphp

                @if (count($history) === 0)
                    <p class="text-xs text-[#777] italic m-0">No activity recorded yet.</p>
                @else
                    <ol class="space-y-4 m-0 p-0 list-none">
                        @foreach ($history as $idx => $entry)
                            @php
                                $isLatest = ($idx === 0);
                                $entryTime = \App\Support\ManilaTime::parseAndFormat($entry['at'] ?? null, 'M j, Y · g:i A', 'Time not available');
                            @endphp
                            <li class="flex gap-3">
                                <div class="relative mt-1 flex w-3 justify-center">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $isLatest ? 'bg-primary' : 'bg-[#ddd]' }}"></span>
                                    @if (!$loop->last)
                                        <span class="absolute top-3.5 h-full w-px bg-[#e2ddd7]"></span>
                                    @endif
                                </div>
                                <div class="min-w-0 pb-1 flex-1">
                                    <p class="text-sm font-semibold leading-snug text-text-dark m-0">{{ \App\Support\HandoverHistoryLabel::forStaff($entry['label'] ?? '') }}</p>
                                    <p class="mt-0.5 text-xs text-[#777] m-0">
                                        {{ $entryTime }} &middot; <span class="font-medium text-text-muted">{{ $entry['actor'] ?? 'System' }}</span>
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
                </div>
            </details>

        </div>
    </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/admin/handover-disclosure.js') }}" defer></script>
@endpush
