@extends('admin.layouts.app')

@section('title', 'Handover & Release - PAIRfect Paws Admin')

@section('notification-bell-in-header', true)
@section('content')
    <div class="main-content-header">
        <div class="heading-text">
            <h2>Handover &amp; Release</h2>
            <p>Approved applications waiting on verified physical transfer and release.</p>
        </div>

        @include('partials.notification-bell')
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 my-5 flex-wrap">
        <div class="filter-bar !my-0">
            <a href="{{ route('admin.handover.index', ['search' => $search]) }}" 
               class="filter-btn {{ $currentTab === 'all' ? 'filter-all active' : '' }}">
                All ({{ $stats['total_count'] }})
            </a>
            <a href="{{ route('admin.handover.index', ['tab' => 'needs_handover', 'search' => $search]) }}" 
               class="filter-btn {{ $currentTab === 'needs_handover' ? 'filter-all active' : '' }}">
                Needs Handover ({{ $stats['needs_handover'] }})
            </a>
            <a href="{{ route('admin.handover.index', ['tab' => 'awaiting', 'search' => $search]) }}" 
               class="filter-btn badge-pending {{ $currentTab === 'awaiting' ? 'active' : '' }}">
                Awaiting ({{ $stats['awaiting'] }})
            </a>
            <a href="{{ route('admin.handover.index', ['tab' => 'failed', 'search' => $search]) }}" 
               class="filter-btn badge-rejected {{ $currentTab === 'failed' ? 'active' : '' }}">
                Failed ({{ $stats['reported_failed'] }})
            </a>
            <a href="{{ route('admin.handover.index', ['tab' => 'monitoring', 'search' => $search]) }}" 
               class="filter-btn badge-completed {{ $currentTab === 'monitoring' ? 'active' : '' }}">
                Monitoring ({{ $stats['monitoring_active'] }})
            </a>
        </div>

        <form method="GET" action="{{ route('admin.handover.index') }}" class="relative w-full sm:w-72">
            <input type="hidden" name="tab" value="{{ $currentTab }}">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search pet, adopter, courier…" 
                   class="search-input w-full pr-8">
            <i class="fa-solid fa-magnifying-glass absolute right-3 top-3.5 text-[#9e9e9e] text-sm pointer-events-none"></i>
            @if ($search)
                <a href="{{ route('admin.handover.index', ['tab' => $currentTab]) }}" class="absolute right-8 top-3 text-[#777] hover:text-primary text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            @endif
        </form>
    </div>

    <!-- Handover Records List -->
    <div class="space-y-3 mt-4">
        @forelse ($records as $record)
            @php
                $badge = $record->status_badge;
                $isDelivery = ($record->release_method === 'delivery');
            @endphp
            <a href="{{ route('admin.handover.show', $record) }}" 
               class="flex items-center gap-4 bg-white border border-[#e2ddd7] rounded-card p-4 shadow-card hover:border-primary transition duration-150 block no-underline text-text-dark">
                
                <!-- Pet Photo -->
                <img src="{{ $record->photo_url }}" alt="{{ $record->pet?->name }}" 
                     class="h-16 w-16 shrink-0 rounded-xl object-cover border border-[#e2ddd7] bg-neutral-light">

                <div class="min-w-0 flex-1">
                    <!-- Title & Badges -->
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-bold text-text-dark font-primary m-0">{{ $record->pet?->name ?? 'Pet' }}</h3>
                        <span class="text-xs text-[#777]">
                            {{ $record->pet?->species ?? 'Animal' }} &middot; {{ strtoupper($record->code) }}
                        </span>
                        <span class="badge {{ $badge['class'] }}">
                            <i class="{{ $badge['icon'] }} mr-1.5 text-xs"></i>
                            {{ $badge['label'] }}
                        </span>
                    </div>

                    <!-- Adopter Name -->
                    <p class="mt-1 text-sm font-semibold text-text-dark m-0">{{ $record->adopter_name }}</p>

                    <!-- Meta / Details Row -->
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[#777]">
                        <span>Approved {{ $record->approved_at ? $record->approved_at->format('M j, Y') : 'Recently' }}</span>

                        @if ($record->released_at)
                            <span class="flex items-center gap-1">
                                <i class="{{ $isDelivery ? 'fa-solid fa-truck' : 'fa-solid fa-house-user' }} text-[#9e9e9e]"></i>
                                {{ $isDelivery ? (($record->courier ?: 'Courier') . ' · ' . ($record->tracking_number ?: 'In transit')) : 'Picked up at shelter' }}
                            </span>
                        @endif

                        @if ($record->follow_up_flagged_at && !$record->adopter_outcome)
                            <span class="font-bold text-status-processing-text">
                                Delivery receipt unconfirmed — staff follow-up
                            </span>
                        @endif
                        @if ($record->missed_pickup_notified_at && !$record->released_at)
                            <span class="font-bold text-status-processing-text">Missed / Uncompleted Pickup</span>
                        @endif
                    </div>
                </div>

                <!-- Right Arrow -->
                <i class="fa-solid fa-chevron-right h-5 w-5 shrink-0 text-[#9e9e9e]"></i>
            </a>
        @empty
            <div class="empty-state">
                <i class="fa-solid fa-truck-ramp-box"></i>
                <h3>No handover records found</h3>
                <p>Try switching tabs or adjusting your search keyword.</p>
            </div>
        @endforelse
    </div>

@endsection
