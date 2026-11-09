@php
    $isDelivery = ($record->release_method === 'delivery');
    $dateTime = ($record->release_date && $record->release_time) 
        ? $record->release_date->format('M j, Y') . ' · ' . date('g:i A', strtotime($record->release_time))
        : ($record->released_at ? $record->released_at->format('M j, Y · g:i A') : '—');
@endphp

<div class="bg-white border border-[#e2ddd7] rounded-card p-6 shadow-card space-y-4">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e2ddd7] pb-4">
        <div>
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-clipboard-check text-primary text-base"></i>
                <h3 class="font-primary font-bold text-base text-text-dark m-0">Recorded Release</h3>
            </div>
            <p class="text-xs text-[#777] m-0 mt-1">Official physical custody transfer record confirmed by shelter staff.</p>
        </div>
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-completed-bg px-3 py-1 text-xs font-semibold text-completed-fg border border-completed-fg">
                <i class="fa-solid fa-circle-check text-xs"></i> Release Completed
            </span>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-start gap-6">
        <dl class="min-w-0 flex-1 grid gap-3 sm:grid-cols-2 m-0 p-0">
            <!-- Method -->
            <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3.5 space-y-1">
                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777]">
                    <i class="{{ $isDelivery ? 'fa-solid fa-truck text-primary' : 'fa-solid fa-house-user text-completed-fg' }}"></i>
                    <dt>Release method</dt>
                </div>
                <dd class="text-sm font-semibold text-text-dark m-0">
                    {{ $isDelivery ? ('Third-party delivery · ' . ($record->courier ?: 'Courier')) : 'Pickup at shelter' }}
                </dd>
            </div>

            <!-- Date & Time -->
            <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3.5 space-y-1">
                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777]">
                    <i class="fa-solid fa-calendar-check text-[#9e9e9e]"></i>
                    <dt>Date &amp; time released</dt>
                </div>
                <dd class="text-sm font-semibold text-text-dark m-0">{{ $dateTime }}</dd>
            </div>

            <!-- Released By -->
            <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3.5 space-y-1">
                <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777]">
                    <i class="fa-solid fa-user-check text-[#9e9e9e]"></i>
                    <dt>Release handled by</dt>
                </div>
                <dd class="text-sm font-semibold text-text-dark m-0">{{ $record->staff_name ?: 'Staff' }}</dd>
            </div>

            @if ($record->release_recorded_by_user_id)
                <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3.5 space-y-1">
                    <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777]">
                        <i class="fa-solid fa-clipboard-user text-[#9e9e9e]"></i>
                        <dt>Recorded in system by</dt>
                    </div>
                    <dd class="text-sm font-semibold text-text-dark m-0">{{ $record->releaseRecordedBy?->full_name ?: 'Former staff account' }}</dd>
                </div>
            @endif

            @if ($isDelivery && $record->tracking_number)
                <!-- Tracking Tile -->
                <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3.5 space-y-1 {{ $record->tracking_url ? '' : 'sm:col-span-2' }}">
                    <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777]">
                        <i class="fa-solid fa-barcode text-[#9e9e9e]"></i>
                        <dt>Tracking reference</dt>
                    </div>
                    <dd class="text-sm font-bold font-mono text-text-dark m-0">{{ $record->tracking_number }}</dd>
                </div>
            @endif

            @if ($isDelivery && $record->tracking_url)
                <div class="rounded-xl border border-primary/20 bg-primary-muted/15 p-3.5 flex flex-col justify-center">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-primary mb-1">Live Courier Tracking</span>
                    <a href="{{ $record->tracking_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm flex items-center justify-center gap-1.5 no-underline">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <span>Open Live Tracking</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs ml-1" aria-hidden="true"></i>
                    </a>
                </div>
            @endif
        </dl>

        @if ($record->proof_path || $record->legacyPublicProofPath())
            <figure class="shrink-0 m-0 rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3 text-center">
                <img src="{{ route('admin.handover.release-proof', $record) }}" alt="Proof of handover"
                     class="h-32 w-40 rounded-lg border border-[#e2ddd7] object-cover shadow-xs bg-white">
                <figcaption class="mt-2 text-center text-xs font-bold text-text-dark flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-camera text-primary"></i>
                    <span>Proof of handover</span>
                </figcaption>
            </figure>
        @endif
    </div>
</div>
