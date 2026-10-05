@extends('layouts.app')

@section('title', ($record->pet?->name ?? 'Pet') . ' Handover Status - PAIRfect Paws')

@section('notification-bell-in-header', true)
@section('content')

    <div class="nonsticky-header custom-scrollbar">
        <div class="main-content-header">
            <div class="heading-text">
                <h2>Handover Status</h2>
                <p>Track the physical transfer and delivery progress of {{ $record->pet?->name ?? 'your pet' }}.</p>
            </div>

            @include('partials.notification-bell')
        </div>
    
        <div class="content-area">

            <div class="max-w-[860px] mx-auto space-y-5 py-3">

                <!-- Sub Navigation Tabs -->
                <div class="flex items-center gap-2 border-b border-[#e2ddd7] pb-3 flex-wrap">
                    <a href="{{ route('adopter.handover.status', $record) }}" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-shield-cat mr-1"></i> Handover Status
                    </a>

                    @if (!$record->adopter_outcome)
                        <a href="{{ route('adopter.confirm', $record) }}" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-clipboard-check mr-1"></i> Confirm Receipt
                        </a>
                    @endif
                </div>

                <!-- Pet Profile Header Card -->
                <section class="flex items-center gap-4 rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
                    <img src="{{ $record->photo_url }}" alt="{{ $record->pet?->name }}"
                        class="h-20 w-20 shrink-0 rounded-xl object-cover border border-[#e2ddd7] bg-[#fdf9f2] shadow-sm">

                    <div class="min-w-0">
                        <span class="text-xs font-bold uppercase tracking-widest text-primary">
                            {{ strtoupper($record->code) }}
                        </span>
                        <h2 class="mt-0.5 text-2xl font-bold tracking-tight text-text-dark font-primary m-0">
                            {{ $record->pet?->name ?? 'Pet' }}
                        </h2>
                        <p class="mt-1 text-sm text-[#777] m-0">
                            {{ $record->pet?->species ?? 'Pet' }} &middot; {{ $record->pet?->age_group ?? 'Adult' }} &middot; Approved {{ $record->approved_at ? $record->approved_at->format('M j, Y') : 'Recently' }}
                        </p>
                    </div>
                </section>

                @php
                    $outcome = $record->adopter_outcome;
                    $isReleased = !empty($record->released_at);
                    $days = $record->days_waiting ?? 0;
                    $isUrgent = ($isReleased && !$outcome && $days >= 2);
                @endphp

                <!-- Dynamic Alert Banner -->
                @if ($outcome === 'received')
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 rounded-card border border-status-success-text/30 bg-status-success-bg p-5 shadow-card">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white border border-status-success-text/20 text-status-success-text shadow-sm">
                            <i class="fa-solid fa-circle-check text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold text-text-dark font-primary m-0">
                                Welcome home, {{ $record->pet?->name }}!
                            </h3>
                            <p class="mt-1 text-sm text-text-muted m-0">
                                Your adoption is complete and post-adoption check-ins have started.
                            </p>
                        </div>
                        <a href="{{ route('monitoring.index') }}" class="btn btn-primary btn-sm shrink-0">
                            View check-ins <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
                        </a>
                    </div>
                @elseif ($outcome === 'not_received')
                    <div class="flex items-start gap-4 rounded-card border border-status-danger-text/30 bg-status-danger-bg p-5 shadow-card">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white border border-status-danger-text/20 text-status-danger-text shadow-sm mt-0.5">
                            <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold text-text-dark font-primary m-0">
                                We know {{ $record->pet?->name }} hasn't arrived
                            </h3>
                            <p class="mt-1 text-sm text-text-muted m-0">
                                The shelter is arranging a new handover and will call you at {{ $record->adopter_phone }}.
                            </p>
                        </div>
                    </div>
                @elseif ($isReleased)
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 rounded-card border {{ $isUrgent ? 'border-status-processing-text/40 bg-status-processing-bg' : 'border-primary/30 bg-primary-muted/20' }} p-5 shadow-card">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white border {{ $isUrgent ? 'border-status-processing-text/30 text-status-processing-text' : 'border-primary/30 text-primary' }} shadow-sm">
                            <i class="{{ $isUrgent ? 'fa-solid fa-clock' : 'fa-solid fa-truck-fast' }} text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold text-text-dark font-primary m-0">
                                @if ($isUrgent)
                                    Still waiting on your confirmation for {{ $record->pet?->name }}
                                @else
                                    {{ $record->pet?->name }} has been released via {{ $record->release_method_label }}
                                @endif
                            </h3>
                            <p class="mt-1 text-sm text-text-muted m-0">
                                @if ($isUrgent)
                                    It has been {{ $days }} days since release. Confirm receipt so your adoption can be completed.
                                @else
                                    Confirm once your pet is with you &mdash; this is the last step of your adoption.
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('adopter.confirm', $record) }}" class="btn btn-primary btn-sm shrink-0">
                            Confirm receipt now <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
                        </a>
                    </div>
                @else
                    <div class="flex items-center gap-4 rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-light text-[#777]">
                            <i class="fa-solid fa-hourglass-start text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold text-text-dark font-primary m-0">
                                Approved &mdash; waiting on a handover schedule
                            </h3>
                            <p class="mt-1 text-sm text-[#777] m-0">
                                The shelter is arranging when and how {{ $record->pet?->name }} will be released to you.
                            </p>
                        </div>
                    </div>
                @endif

                <!-- YOUR PROGRESS Section -->
                <section aria-label="Adoption progress" class="rounded-card border border-[#e2ddd7] bg-white p-6 shadow-card">
                    <h3 class="text-base font-bold text-text-dark font-primary m-0 mb-4">
                        Your Progress
                    </h3>

                    <ol class="space-y-4 m-0 p-0 list-none">
                        <!-- Step 1: Application approved -->
                        <li class="flex items-center gap-3.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-status-success-text text-white text-xs font-bold shadow-sm">
                                <i class="fa-solid fa-check text-[11px]"></i>
                            </span>
                            <p class="flex-1 text-sm font-bold text-text-dark m-0">
                                Application approved
                            </p>
                        </li>

                        <!-- Step 2: Handover scheduled -->
                        <li class="flex items-center gap-3.5">
                            @if ($isReleased)
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-status-success-text text-white text-xs font-bold shadow-sm">
                                    <i class="fa-solid fa-check text-[11px]"></i>
                                </span>
                                <p class="flex-1 text-sm font-bold text-text-dark m-0">
                                    Handover scheduled
                                </p>
                            @else
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-white text-xs font-bold shadow-sm">
                                    2
                                </span>
                                <p class="flex-1 text-sm font-bold text-text-dark m-0">
                                    Handover scheduled
                                </p>
                            @endif
                        </li>

                        <!-- Step 3: You confirm receipt -->
                        <li class="flex items-center gap-3.5">
                            @if ($outcome === 'received')
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-status-success-text text-white text-xs font-bold shadow-sm">
                                    <i class="fa-solid fa-check text-[11px]"></i>
                                </span>
                                <p class="flex-1 text-sm font-bold text-text-dark m-0">
                                    You confirmed receipt
                                </p>
                            @elseif ($isReleased)
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-white text-xs font-bold shadow-sm">
                                    3
                                </span>
                                <p class="flex-1 text-sm font-bold text-text-dark m-0">
                                    You confirm receipt
                                </p>
                            @else
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-neutral-light text-[#777] text-xs font-bold">
                                    3
                                </span>
                                <p class="flex-1 text-sm font-medium text-[#777] m-0">
                                    You confirm receipt
                                </p>
                            @endif
                        </li>

                        <!-- Step 4: Check-ins begin -->
                        <li class="flex items-center gap-3.5">
                            @if ($outcome === 'received')
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-status-success-text text-white text-xs font-bold shadow-sm">
                                    <i class="fa-solid fa-check text-[11px]"></i>
                                </span>
                                <p class="flex-1 text-sm font-bold text-text-dark m-0">
                                    Check-ins begin
                                </p>
                            @else
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-neutral-light text-[#9e9e9e] text-xs font-bold">
                                    <i class="fa-solid fa-lock text-[10px]"></i>
                                </span>
                                <p class="flex-1 text-sm font-medium text-[#9e9e9e] m-0">
                                    Check-ins begin
                                </p>
                            @endif
                        </li>
                    </ol>
                </section>

                <!-- RELEASE DETAILS Section -->
                @php
                    $isDelivery = ($record->release_method === 'delivery');
                    $dateTime = ($record->release_date && $record->release_time)
                        ? $record->release_date->format('M j, Y') . ' · ' . date('g:i A', strtotime($record->release_time))
                        : ($record->released_at ? $record->released_at->format('M j, Y · g:i A') : 'Scheduled');
                @endphp

                <section class="rounded-card border border-[#e2ddd7] bg-white p-6 shadow-card">
                    <h3 class="text-base font-bold text-text-dark font-primary m-0 mb-4">
                        Release Details
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-6 items-start">

                        <!-- Left: Metadata with Icons -->
                        <div class="space-y-4">

                            <!-- Release Method -->
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-truck mt-0.5 text-[#9e9e9e] text-base w-5"></i>
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#777] block">
                                        RELEASE METHOD
                                    </span>
                                    <span class="text-sm font-bold text-text-dark block mt-0.5">
                                        {{ $isDelivery ? ('Third-party delivery · ' . ($record->courier ?: 'Grab Pet Transport')) : 'Shelter pickup' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Date & Time Released -->
                            <div class="flex items-start gap-3">
                                <i class="fa-regular fa-clock mt-0.5 text-[#9e9e9e] text-base w-5"></i>
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#777] block">
                                        DATE &amp; TIME RELEASED
                                    </span>
                                    <span class="text-sm font-bold text-text-dark block mt-0.5">
                                        {{ $dateTime }}
                                    </span>
                                </div>
                            </div>

                            <!-- Released By -->
                            <div class="flex items-start gap-3">
                                <i class="fa-regular fa-user mt-0.5 text-[#9e9e9e] text-base w-5"></i>
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#777] block">
                                        RELEASED BY
                                    </span>
                                    <span class="text-sm font-bold text-text-dark block mt-0.5">
                                        {{ $record->staff_name ?: 'Marco Uy' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Tracking Reference -->
                            @if ($record->tracking_number)
                                <div class="flex items-start gap-3">
                                    <i class="fa-solid fa-hashtag mt-0.5 text-[#9e9e9e] text-base w-5"></i>
                                    <div>
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#777] block">
                                            TRACKING REFERENCE
                                        </span>
                                        <span class="text-sm font-bold font-mono text-text-dark block mt-0.5">
                                            {{ $record->tracking_number }}
                                        </span>
                                    </div>
                                </div>
                            @endif

                        </div>

                        <!-- Right: Proof of Handover Photo -->
                        @if ($record->photo_url)
                            <div class="flex flex-col items-center sm:items-end justify-center">
                                <figure class="w-full sm:w-56 m-0">
                                    <img src="{{ $record->photo_url }}" alt="Pet photo"
                                        class="w-full h-36 rounded-xl object-cover border border-[#e2ddd7] shadow-sm bg-neutral-light">
                                    <figcaption class="mt-1.5 text-center text-xs text-[#777] font-medium">
                                        Pet photo
                                    </figcaption>
                                </figure>
                            </div>
                        @endif

                    </div>
                </section>

            </div>

        </div>
    </div>

@endsection
