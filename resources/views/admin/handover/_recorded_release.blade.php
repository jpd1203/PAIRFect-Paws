@php
    $isDelivery = ($record->release_method === 'delivery');
    $dateTime = ($record->release_date && $record->release_time) 
        ? $record->release_date->format('M j, Y') . ' · ' . date('g:i A', strtotime($record->release_time))
        : ($record->released_at ? $record->released_at->format('M j, Y · g:i A') : '—');
@endphp

<div class="bg-white border border-[#e2ddd7] rounded-card p-6 shadow-card">
    <h3 class="font-primary font-bold text-base text-text-dark mb-4">Recorded Release</h3>

    <div class="flex flex-col sm:flex-row sm:items-start gap-6">
        <dl class="min-w-0 flex-1 space-y-3.5 m-0 p-0">
            <!-- Method -->
            <div class="flex items-start gap-3">
                <i class="{{ $isDelivery ? 'fa-solid fa-truck' : 'fa-solid fa-house-user' }} mt-0.5 text-[#9e9e9e] text-sm"></i>
                <div class="min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wide text-[#777]">Release method</dt>
                    <dd class="text-sm font-semibold text-text-dark m-0 mt-0.5">
                        {{ $isDelivery ? ('Third-party delivery · ' . ($record->courier ?: 'Courier')) : 'Pickup at shelter' }}
                    </dd>
                </div>
            </div>

            <!-- Date & Time -->
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-calendar-check mt-0.5 text-[#9e9e9e] text-sm"></i>
                <div class="min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wide text-[#777]">Date &amp; time released</dt>
                    <dd class="text-sm font-semibold text-text-dark m-0 mt-0.5">{{ $dateTime }}</dd>
                </div>
            </div>

            <!-- Released By -->
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-user-check mt-0.5 text-[#9e9e9e] text-sm"></i>
                <div class="min-w-0">
                    <dt class="text-[11px] font-bold uppercase tracking-wide text-[#777]">Released by</dt>
                    <dd class="text-sm font-semibold text-text-dark m-0 mt-0.5">{{ $record->staff_name ?: 'Staff' }}</dd>
                </div>
            </div>

            @if ($isDelivery && $record->tracking_number)
                <!-- Tracking -->
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-barcode mt-0.5 text-[#9e9e9e] text-sm"></i>
                    <div class="min-w-0">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-[#777]">Tracking reference</dt>
                        <dd class="text-sm font-semibold font-mono text-text-dark m-0 mt-0.5">{{ $record->tracking_number }}</dd>
                    </div>
                </div>
            @endif
            @if ($isDelivery && $record->tracking_url)
                <div>
                    <a href="{{ $record->tracking_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                        <i class="fa-solid fa-location-dot mr-1.5" aria-hidden="true"></i> Open Live Tracking
                        <i class="fa-solid fa-arrow-up-right-from-square ml-1.5" aria-hidden="true"></i>
                    </a>
                </div>
            @endif
        </dl>

        @if ($record->proof_url)
            <figure class="shrink-0 m-0">
                <img src="{{ $record->proof_url }}" alt="Proof of handover" 
                     class="h-28 w-36 rounded-xl border border-[#e2ddd7] object-cover shadow-sm bg-neutral-light">
                <figcaption class="mt-1 text-center text-[11px] font-semibold text-[#777]">
                    <i class="fa-solid fa-camera mr-1"></i> Proof of handover
                </figcaption>
            </figure>
        @endif
    </div>
</div>
