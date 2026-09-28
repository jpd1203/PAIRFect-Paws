@extends('admin.layouts.app')

@section('title', ($record->pet?->name ?? 'Pet') . ' Handover & Release - PAIRfect Paws Admin')

@section('content')

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
                <p>{{ strtoupper($record->code) }} &middot; Approved {{ $record->approved_at ? $record->approved_at->format('M j, Y') : 'Recently' }} &middot; {{ $record->pet?->species ?? 'Animal' }}, {{ $record->pet?->age_group ?? 'Age' }}</p>
            </div>
        </div>
    </div>

    <!-- Handover Pipeline -->
    <div class="mb-5">
        @include('admin.handover._pipeline', ['record' => $record])
    </div>

    <!-- Monitoring Status Banner -->
    @if ($record->monitoring_locked)
        <div class="modal-note caution flex items-start gap-3.5 mb-5">
            <i class="fa-solid fa-lock text-base mt-0.5 shrink-0"></i>
            <div>
                <strong class="text-text-dark">Post-adoption monitoring is not running yet.</strong>
                Check-ins, reports and flagged-case tracking stay switched off until {{ $record->pet?->name ?? 'the pet' }} is confirmed to be with {{ $record->adopter_name }}.
            </div>
        </div>
    @else
        <div class="modal-note flex items-start gap-3.5 mb-5">
            <i class="fa-solid fa-circle-check text-base mt-0.5 shrink-0"></i>
            <div>
                <strong>Post-adoption monitoring is active.</strong>
                Both staff release and adopter confirmation are on record &mdash; the first check-in is scheduled.
            </div>
        </div>
    @endif

    <!-- Main 2-Column Content Grid -->
    <div class="grid gap-5 lg:grid-cols-[1.6fr_1fr] lg:items-start">
        
        <!-- Left Column: Release Details & Adopter Confirmation -->
        <div class="space-y-5">
            @if ($record->released_at)
                @include('admin.handover._recorded_release', ['record' => $record])
                @include('admin.handover._adopter_confirmation', ['record' => $record])
            @else
                @include('admin.handover._release_form', ['record' => $record])
            @endif
        </div>

        <!-- Right Column: Adopter Profile Card & History Timeline -->
        <div class="space-y-5">
            
            <!-- Adopter Info Card -->
            <section class="bg-white border border-[#e2ddd7] rounded-card p-5 shadow-card">
                <h3 class="font-primary font-bold text-base text-text-dark mb-3">Adopter Details</h3>
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
            </section>

            <!-- Handover Log Timeline -->
            <section class="bg-white border border-[#e2ddd7] rounded-card p-5 shadow-card">
                <h3 class="font-primary font-bold text-base text-text-dark mb-4">Handover Log</h3>

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
                                $entryTime = isset($entry['at']) ? date('M j, Y · g:i A', strtotime($entry['at'])) : '';
                            @endphp
                            <li class="flex gap-3">
                                <div class="relative mt-1 flex w-3 justify-center">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $isLatest ? 'bg-primary' : 'bg-[#ddd]' }}"></span>
                                    @if (!$loop->last)
                                        <span class="absolute top-3.5 h-full w-px bg-[#e2ddd7]"></span>
                                    @endif
                                </div>
                                <div class="min-w-0 pb-1 flex-1">
                                    <p class="text-sm font-semibold leading-snug text-text-dark m-0">{{ $entry['label'] ?? '' }}</p>
                                    <p class="mt-0.5 text-xs text-[#777] m-0">
                                        {{ $entryTime }} &middot; <span class="font-medium text-text-muted">{{ $entry['actor'] ?? 'System' }}</span>
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

        </div>
    </div>

@endsection
