@extends('admin.layouts.app')

@section('title', 'Monitoring - PAIRfect Paws Admin')

@section('content')
    @php
        $now = \Carbon\Carbon::now('Asia/Manila');

        // Helper to extract report payload for view modal
        $buildReportPayload = function ($checkIn) {
            $survey = is_array($checkIn->survey_data) ? $checkIn->survey_data : [];
            $verification = is_array(data_get($survey, '_verification'))
                ? data_get($survey, '_verification')
                : [];
            $flagReasonText = collect($checkIn->flag_reasons ?? [])
                ->map(function ($reason) {
                    if (is_string($reason)) {
                        return $reason;
                    }
                    if (is_array($reason)) {
                        return $reason['message'] ?? $reason['reason'] ?? null;
                    }
                    return null;
                })
                ->filter()
                ->values()
                ->implode(' | ');
            $healthStatus = $checkIn->pet_current_status?->value
                ?? data_get($survey, 'pet_current_status')
                ?? 'Not reported';
            $concerns = $checkIn->concerns
                ?? data_get($survey, 'concerns')
                ?? 'No concerns reported.';

            return [
                'adopter' => $checkIn->user?->full_name ?: 'Unknown adopter',
                'pet' => $checkIn->pet?->name ?: 'Unknown pet',
                'milestone' => $checkIn->milestone_display,
                'due_date' => $checkIn->due_date->format('M j, Y'),
                'submitted_date' => $checkIn->submitted_date
                    ? \App\Support\ManilaTime::format($checkIn->submitted_date, 'M j, Y g:i A')
                    : 'Not submitted',
                'status' => $checkIn->status_display,
                'status_slug' => $checkIn->status_slug,
                'health' => $healthStatus,
                'eating' => $checkIn->eating_habits ?? data_get($survey, 'eating_habits') ?? 'Not reported',
                'behavior' => $checkIn->behavioral_observations ?? data_get($survey, 'behavioral_observations') ?? 'Not reported',
                'living' => $checkIn->living_conditions ?? data_get($survey, 'living_conditions') ?? 'Not reported',
                'vet' => $checkIn->vet_visit_details ?? data_get($survey, 'vet_visit_details') ?? 'Not reported',
                'concerns' => $concerns,
                'flag_reasons' => $flagReasonText ?: 'No flag reasons recorded.',
                'verification_method' => data_get($verification, 'method') ?: 'Not available',
                'challenge_id' => data_get($verification, 'capture_challenge_id') ?: 'Not available',
                'challenge_issued_at' => \App\Support\ManilaTime::parseAndFormat(
                    data_get($verification, 'challenge_issued_at'),
                    'M j, Y g:i A',
                ),
                'video_sha256' => data_get($verification, 'video_sha256') ?: 'Not available',
                'video_mime_type' => data_get($verification, 'video_mime_type') ?: 'Not available',
                'declared_duration_ms' => data_get($verification, 'declared_duration_ms'),
                'verified_duration_ms' => data_get($verification, 'verified_duration_ms'),
                'duration_verification_status' => data_get($verification, 'duration_verification_status') ?: 'Not available',
                'legacy_c2pa_status' => data_get($verification, 'c2pa_status'),
                'legacy_c2pa_reason' => data_get($verification, 'c2pa_reason_code'),
                'legacy_manifest_id' => data_get($verification, 'manifest_id'),
                'legacy_signed_at' => \App\Support\ManilaTime::parseAndFormat(
                    data_get($verification, 'signed_at'),
                    'M j, Y g:i A',
                    '',
                ),
                'video_url' => filled($checkIn->video_path) ? route('admin.monitoring.video', $checkIn) : null,
                'photo_url' => filled($checkIn->photo_path) ? route('admin.monitoring.photo', $checkIn) : null,
            ];
        };

        // Group check-ins by adoption application
        $adoptions = $checkIns
            ->groupBy('application_id')
            ->map(function ($logs, $appId) use ($buildReportPayload, $now) {
                $firstLog = $logs->first();
                $application = $firstLog->adoptionApplication;
                $user = $application?->user;
                $pet = $application?->pet;

                // Sort logs chronologically
                $sortedLogs = $logs->sortBy('scheduled_date')->values();

                // Compute overall adoption status
                $statuses = $sortedLogs->pluck('status_slug')->all();
                if (in_array('flagged', $statuses, true)) {
                    $overallStatus = 'flagged';
                } elseif (in_array('overdue', $statuses, true)) {
                    $overallStatus = 'overdue';
                } elseif (in_array('pending', $statuses, true)) {
                    $overallStatus = 'pending';
                } elseif (collect($statuses)->every(fn($s) => $s === 'completed')) {
                    $overallStatus = 'completed';
                } else {
                    $overallStatus = 'upcoming';
                }

                // Latest submitted report
                $latestSubmitted = $sortedLogs
                    ->whereNotNull('submitted_date')
                    ->sortByDesc('submitted_date')
                    ->first();

                // Next pending/overdue/upcoming check-in
                $nextCheckIn = $sortedLogs
                    ->whereNull('submitted_date')
                    ->first();

                // Active check-in for primary action button
                $primaryCheckIn = $sortedLogs->firstWhere('status_slug', 'flagged')
                    ?? $sortedLogs->firstWhere('status_slug', 'overdue')
                    ?? $sortedLogs->firstWhere('status_slug', 'pending')
                    ?? $latestSubmitted
                    ?? $nextCheckIn
                    ?? $sortedLogs->first();

                return (object) [
                    'id' => $appId ?: ('adopt-' . $firstLog->id),
                    'application' => $application,
                    'user' => $user,
                    'pet' => $pet,
                    'checkIns' => $sortedLogs,
                    'overall_status' => $overallStatus,
                    'latest_submitted' => $latestSubmitted,
                    'next_check_in' => $nextCheckIn,
                    'primary_check_in' => $primaryCheckIn,
                    'adopted_at' => $application?->adopted_at ?? $firstLog->created_at,
                ];
            })
            ->values();

        // Calculate counts based on adoption overall status
        $counts = [
            'all' => $adoptions->count(),
            'completed' => $adoptions->where('overall_status', 'completed')->count(),
            'pending' => $adoptions->where('overall_status', 'pending')->count(),
            'overdue' => $adoptions->where('overall_status', 'overdue')->count(),
            'flagged' => $adoptions->where('overall_status', 'flagged')->count(),
            'upcoming' => $adoptions->where('overall_status', 'upcoming')->count(),
        ];

        // Group by Adopter
        $adopterGroups = $adoptions
            ->groupBy(fn($ad) => $ad->user?->id ?? 'unknown')
            ->map(function ($items) {
                $user = $items->first()->user;
                $latestSubmitted = $items
                    ->pluck('latest_submitted')
                    ->filter()
                    ->sortByDesc('submitted_date')
                    ->first();

                return (object) [
                    'id' => $user?->id ?? 'unknown',
                    'user' => $user,
                    'adoptions' => $items,
                    'latest_submitted' => $latestSubmitted,
                ];
            })
            ->values();
    @endphp

    <div class="mx-auto">
        {{-- Header matching Magic Patterns --}}
        <div class="heading-text">
            <h2>Post-Adoption Monitoring</h2>
            <p>Track welfare check-ins for every adopted pet. Newest submissions appear first.</p>
        </div>


        @if (isset($errors) && $errors->any())
            <div class="mb-5 rounded-xl border border-status-danger-text bg-status-danger-bg p-4 text-sm text-status-danger-text" role="alert">
                <strong>The action could not be completed.</strong>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Filters & Controls Bar --}}
        <div class="filter-bar">

            <div class="flex flex-wrap items-center gap-2"
                role="tablist"
                aria-label="Filter by status"
                id="monitoringFilterTabs">

                <button type="button"
                        role="tab"
                        aria-selected="true"
                        data-filter-btn="all"
                        class="filter-btn filter-all active">
                    All ({{ $counts['all'] }})
                </button>

                <button type="button"
                        role="tab"
                        aria-selected="false"
                        data-filter-btn="completed"
                        class="filter-btn badge-completed">
                    Completed ({{ $counts['completed'] }})
                </button>

                <button type="button"
                        role="tab"
                        aria-selected="false"
                        data-filter-btn="pending"
                        class="filter-btn badge-pending">
                    Pending ({{ $counts['pending'] }})
                </button>

                <button type="button"
                        role="tab"
                        aria-selected="false"
                        data-filter-btn="overdue"
                        class="filter-btn badge-overdue">
                    Overdue ({{ $counts['overdue'] }})
                </button>

                <button type="button"
                        role="tab"
                        aria-selected="false"
                        data-filter-btn="flagged"
                        class="filter-btn badge-flagged">
                    Flagged ({{ $counts['flagged'] }})
                </button>

                @if ($counts['upcoming'] > 0)
                    <button type="button"
                            role="tab"
                            aria-selected="false"
                            data-filter-btn="upcoming"
                            class="filter-btn badge-upcoming">
                        Upcoming ({{ $counts['upcoming'] }})
                    </button>
                @endif

            </div>
        </div>

            <div class="flex flex-wrap items-center gap-3 mb-5">
            {{-- Search Box --}}
            <div class="relative flex-1 min-w-[240px]">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"></span>
                <input type="search" id="monitoringSearchInput" placeholder="Search pet or adopter..."class="search-input">
            </div>

            {{-- View By Toggle --}}
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-sm font-primary text-muted font-medium">View by</span>

                <div class="flex rounded-md bg-surface p-0.5 border border-line/60" role="radiogroup" aria-label="Group adoptions by">
                    <button type="button" id="viewByPetBtn"
                        class="view-by-btn active whitespace-nowrap rounded px-3 py-1 text-[12px] font-semibold transition-colors bg-white text-ink shadow-sm" data-view="pet">
                        Pet
                    </button>

                    <button type="button" id="viewByAdopterBtn"
                        class="view-by-btn whitespace-nowrap rounded px-3 py-1 text-[12px] font-semibold transition-colors text-muted hover:text-ink"data-view="adopter">
                        Adopter
                    </button>
                </div>
            </div>

            {{-- Sort Dropdown --}}
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-sm font-primary text-muted font-medium">Sort</span>

                <select id="monitoringSortSelect"
                    class="h-9 rounded-md border border-line bg-white px-2.5 text-sm font-medium text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 cursor-pointer"
                >
                    <option value="latest">Newest submission first</option>
                    <option value="due">Next due first</option>
                </select>
            </div>
        </div>
        </div>

        

        {{-- 1. PET VIEW (Default) --}}
        <div id="petViewContainer" class="overflow-hidden rounded-xl border border-line bg-white shadow-sm">
            {{-- Table Column Headers --}}
            <div class="hidden lg:grid monitoring-row-grid bg-neutral-light px-5 py-3 text-[13.5px] font-primary font-semibold text-text-muted border-b border-line uppercase tracking-wider" aria-hidden="true">
                <span>Adopted pet</span>
                <span>Check-ins</span>
                <span>Latest submission</span>
                <span>Next check-in</span>
                <span>Status</span>
                <span class="text-center">Action</span>
                <span></span>
            </div>

            {{-- Adoption Rows --}}
            <ul class="divide-y divide-line" id="petViewList">
                @forelse ($adoptions as $adoption)
                    @php
                        $primaryLog = $adoption->primary_check_in;
                        $primaryReport = $primaryLog ? $buildReportPayload($primaryLog) : null;
                        $petPhoto = $adoption->pet?->image_url;
                        $petName = $adoption->pet?->name ?: 'Unknown Pet';
                        $petBreed = $adoption->pet?->breed ?: ($adoption->pet?->species?->value ?: 'Pet');
                        $adopterName = $adoption->user?->full_name ?: 'Unknown Adopter';
                        $adopterEmail = $adoption->user?->email ?: '';
                        $searchText = strtolower("{$petName} {$petBreed} {$adopterName} {$adopterEmail}");
                        $latestSub = $adoption->latest_submitted;
                        $nextCheck = $adoption->next_check_in;

                        // Sort keys for JavaScript sorting
                        $latestTimestamp = $latestSub?->submitted_date ? $latestSub->submitted_date->timestamp : 0;
                        $nextDueTimestamp = $nextCheck?->scheduled_date ? $nextCheck->scheduled_date->timestamp : 9999999999;
                    @endphp

                    <li
                        class="adoption-item transition-colors duration-150 hover:bg-surface/30"
                        data-status="{{ $adoption->overall_status }}"
                        data-search="{{ $searchText }}"
                        data-latest-ts="{{ $latestTimestamp }}"
                        data-next-due-ts="{{ $nextDueTimestamp }}"
                        id="adoption-{{ $adoption->id }}"
                    >
                        {{-- Main Row --}}
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4 monitoring-row-grid">
                            {{-- Col 1: Adopted Pet Info --}}
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                @if ($petPhoto && !str_contains($petPhoto, 'rcpp-logo'))
                                    <img src="{{ $petPhoto }}" alt="{{ $petName }}" class="h-12 w-12 shrink-0 rounded-lg object-cover ring-1 ring-line">
                                @else
                                    <div class="h-12 w-12 shrink-0 rounded-lg bg-surface flex items-center justify-center text-brand ring-1 ring-line">
                                        <i class="fa-solid {{ ($adoption->pet?->species?->value ?? '') === 'Cat' ? 'fa-cat' : 'fa-dog' }} text-lg"></i>
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-2">
                                        <span class="font-semibold text-l text-ink truncate">{{ $petName }}</span>
                                        <span class="truncate text-xs text-muted">{{ $petBreed }}</span>
                                    </div>
                                    <span class="block truncate text-xs text-muted mt-0.5">
                                        {{ $adopterName }} &middot; Adopted {{ $adoption->adopted_at ? \Carbon\Carbon::parse($adoption->adopted_at)->format('M j, Y') : 'Recently' }}
                                    </span>
                                </div>
                            </div>

                            {{-- Col 2: Check-in Progress Bar (3D, 3W, 3M) --}}
                            <div class="shrink-0">
                                <ol class="flex items-start gap-1" aria-label="Check-in progress">
                                    @php
                                        $milestoneCodes = [
                                            '3_days' => '3D',
                                            '3_weeks' => '3W',
                                            '3_months' => '3M',
                                        ];
                                    @endphp
                                    @foreach ($adoption->checkIns as $checkIn)
                                        @php
                                            $mKey = is_string($checkIn->milestone) ? $checkIn->milestone : $checkIn->milestone?->value;
                                            $mCode = $milestoneCodes[$mKey] ?? 'CI';
                                            $barColor = match ($checkIn->status_slug) {
                                                'completed' => 'bg-completed-solid',
                                                'pending' => 'bg-pending-bar',
                                                'overdue' => 'bg-overdue-bar',
                                                'flagged' => 'bg-flagged-solid',
                                                default => 'bg-upcoming-bg',
                                            };
                                        @endphp
                                        <li class="flex flex-col items-center gap-1" title="{{ $checkIn->milestone_display }}: {{ $checkIn->status_display }}">
                                            <span class="h-1.5 w-6 rounded-full {{ $barColor }}" aria-hidden="true"></span>
                                            <span class="text-[12px] font-semibold leading-none text-muted">{{ $mCode }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>

                            {{-- Col 3: Latest Submission --}}
                            <div class="min-w-0">
                                @if ($latestSub)
                                    @php
                                        $isNew = $latestSub->submitted_date && $now->diffInDays($latestSub->submitted_date) <= 3;
                                    @endphp
                                    <p class="flex items-center gap-2 text-[14px] text-ink truncate font-medium">
                                        <span class="truncate">{{ $latestSub->milestone_display }}</span>
                                        @if ($isNew)
                                            <span class="rounded-full bg-brand px-1.5 py-0.5 text-[9px] font-bold text-white uppercase tracking-wider">New</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-xs text-muted mt-0.5">
                                        Submitted {{ $latestSub->submitted_date ? $latestSub->submitted_date->diffForHumans() : '' }}
                                    </p>
                                @else
                                    <p class="text-sm text-muted">No reports yet</p>
                                @endif
                            </div>

                            {{-- Col 4: Next Check-in --}}
                            <div class="min-w-0">
                                @if ($nextCheck)
                                    <p class="text-[14px] {{ $nextCheck->status_slug === 'overdue' ? 'font-bold text-overdue-fg' : 'font-medium text-ink' }} truncate">
                                        {{ $nextCheck->milestone_display }}
                                    </p>
                                    <p class="truncate text-xs text-muted mt-0.5">
                                        {{ $nextCheck->status_slug === 'overdue' ? 'Was due' : 'Due' }} {{ $nextCheck->due_date->format('M j, Y') }}
                                    </p>
                                @else
                                    <p class="text-sm font-medium text-completed-fg flex items-center gap-1.5">
                                        <i class="fa-solid fa-circle-check text-xs"></i> All done
                                    </p>
                                @endif
                            </div>

                            {{-- Col 5: Status Badge Pill --}}
                            <div>
                                @php
                                    $pillClasses = match ($adoption->overall_status) {
                                        'completed' => 'bg-completed-bg text-completed-fg',
                                        'pending' => 'bg-pending-bg text-pending-fg',
                                        'overdue' => 'bg-overdue-bg text-overdue-fg',
                                        'flagged' => 'bg-flagged-bg text-flagged-fg',
                                        default => 'bg-upcoming-bg text-upcoming-fg',
                                    };
                                @endphp
                                <span class="badge {{ $pillClasses }}">
                                    {{ ucfirst($adoption->overall_status === 'pending' ? 'Pending' : $adoption->overall_status) }}
                                </span>
                            </div>

                            {{-- Col 6: Primary Quick Action Button --}}
                            <div class="flex justify-center">
                                @if ($adoption->overall_status === 'flagged')
                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        data-report="{{ json_encode($primaryReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                                        onclick="openMonitoringViewModal(this)"
                                    >
                                        <i class="fa-solid fa-eye text-muted"></i> View Details
                                    </button>
                                @elseif ($adoption->overall_status === 'completed')
                                    <button
                                        type="button"
                                        class="whitespace-nowrap rounded-md border border-line bg-white px-3 py-1.5 text-xs font-semibold text-ink shadow-sm hover:bg-surface transition-colors flex items-center gap-1.5"
                                        data-report="{{ json_encode($primaryReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                                        onclick="openMonitoringViewModal(this)"
                                    >
                                        <i class="fa-solid fa-file-lines text-muted"></i> View Report
                                    </button>
                                @elseif ($adoption->overall_status === 'overdue' && $nextCheck)
                                    <button
                                        type="button"
                                        class="whitespace-nowrap rounded-md border border-overdue-bg bg-overdue-bg px-3 py-1.5 text-xs font-semibold text-overdue-fg hover:border-overdue-fg/30 transition-colors flex items-center gap-1.5"
                                        data-action="{{ route('admin.monitoring.flag', $nextCheck) }}"
                                        data-summary="{{ $adopterName }} - {{ $petName }} - {{ $nextCheck->milestone_display }}"
                                        onclick="openMonitoringFlagModal(this)"
                                    >
                                        <i class="fa-solid fa-flag text-xs"></i> Flag & Notify
                                    </button>
                                @elseif ($adoption->overall_status === 'pending' && $nextCheck)
                                    <button
                                        type="button"
                                        class="btn btn-yellow"
                                        data-action="{{ route('admin.monitoring.reminder', $nextCheck) }}"
                                        data-summary="{{ $adopterName }} - {{ $petName }} - {{ $nextCheck->milestone_display }}"
                                        onclick="openMonitoringReminderModal(this)"
                                    >
                                        <i class="fa-solid fa-bell text-xs"></i> Send Reminder
                                    </button>
                                @else
                                    <span class="text-xs text-muted font-medium">Not yet due</span>
                                @endif
                            </div>

                            {{-- Col 7: Chevron Accordion Toggle --}}
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="accordion-toggle-btn h-7 w-7 rounded flex items-center justify-center text-muted hover:text-ink hover:bg-surface transition-all"
                                    aria-expanded="false"
                                    aria-controls="drawer-{{ $adoption->id }}"
                                    onclick="toggleAdoptionDrawer('{{ $adoption->id }}')"
                                    title="View milestone breakdown"
                                >
                                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" id="chevron-{{ $adoption->id }}"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Expanded Accordion History Drawer --}}
                        <div id="drawer-{{ $adoption->id }}" class="hidden overflow-hidden bg-surface/60 border-t border-line/60 px-5 py-4 lg:pl-16">
                            <div class="overflow-x-auto rounded-lg bg-white border border-line p-3 shadow-sm">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-center font-semibold text-muted border-b border-line pb-2">
                                            <th class="pb-2 pr-4">Milestone</th>
                                            <th class="pb-2 pr-4">Due Date</th>
                                            <th class="pb-2 pr-4">Submitted Date</th>
                                            <th class="pb-2 pr-4">Status</th>
                                            <th class="pb-2 pr-4">Reminders</th>
                                            <th class="pb-2 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line/60">
                                        @foreach ($adoption->checkIns as $log)
                                            @php
                                                $logReport = $buildReportPayload($log);
                                                $logSummary = "{$adopterName} - {$petName} - {$log->milestone_display}";
                                                $badgeStyle = match ($log->status_slug) {
                                                    'completed' => 'bg-completed-bg text-completed-fg',
                                                    'pending' => 'bg-pending-bg text-pending-fg',
                                                    'overdue' => 'bg-overdue-bg text-overdue-fg',
                                                    'flagged' => 'bg-flagged-bg text-flagged-fg',
                                                    default => 'bg-upcoming-bg text-upcoming-fg',
                                                };
                                            @endphp
                                            <tr class="hover:bg-surface/30 transition-colors text-center" data-monitoring-status="{{ $log->status_slug }}">
                                                <td class="py-2.5 pr-4 font-semibold text-ink">
                                                    {{ $log->milestone_display }}
                                                </td>
                                                <td class="py-2.5 pr-4 text-muted">
                                                    {{ $log->due_date->format('M j, Y') }}
                                                </td>
                                                <td class="py-2.5 pr-4 text-muted">
                                                    @if ($log->submitted_date)
                                                        <span class="font-medium text-ink">
                                                            {{ \App\Support\ManilaTime::format($log->submitted_date, 'M j, Y g:i A') }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">Not submitted</span>
                                                    @endif
                                                </td>
                                                <td class="py-3 pr-4">
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $badgeStyle }}">
                                                        {{ $log->status_display }}
                                                    </span>
                                                </td>
                                                <td class="py-2.5 pr-4 text-muted">
                                                    {{ $log->reminders_sent }} sent
                                                </td>
                                                <td class="py-2.5 text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button
                                                            type="button"
                                                            class="btn btn-secondary btn-sm !text-xs !py-1 !px-2.5"
                                                            data-report="{{ json_encode($logReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                                                            onclick="openMonitoringViewModal(this)"
                                                        >
                                                            <i class="fa-solid fa-eye"></i> View
                                                        </button>

                                                        @if (!$log->submitted_date && $log->status_slug !== 'upcoming')
                                                            <button
                                                                type="button"
                                                                class="btn btn-yellow btn-sm !text-xs !py-1 !px-2.5"
                                                                data-action="{{ route('admin.monitoring.reminder', $log) }}"
                                                                data-summary="{{ $logSummary }}"
                                                                onclick="openMonitoringReminderModal(this)"
                                                            >
                                                                <i class="fa-solid fa-bell"></i> Remind
                                                            </button>
                                                        @endif

                                                        @if (!$log->is_flagged || $log->resolved_at)
                                                            <button
                                                                type="button"
                                                                class="btn btn-danger btn-sm !text-xs !py-1 !px-2.5"
                                                                data-action="{{ route('admin.monitoring.flag', $log) }}"
                                                                data-summary="{{ $logSummary }}"
                                                                onclick="openMonitoringFlagModal(this)"
                                                            >
                                                                <i class="fa-solid fa-flag"></i> Flag
                                                            </button>
                                                        @else
                                                            <a class="btn btn-secondary btn-sm !text-xs !py-1 !px-2.5" href="{{ route('admin.monitoring.flagged') }}">
                                                                <i class="fa-solid fa-magnifying-glass"></i> Review Flag
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="py-12 text-center text-muted">
                        No post-adoption monitoring records exist yet.
                    </li>
                @endforelse

                <li id="monitoringEmptyState"
                    class="hidden flex-col items-center justify-center px-6 py-16 text-center">
                    <i class="fa-solid fa-clipboard-question text-3xl text-muted mb-3"></i>
                    <p class="text-sm font-semibold text-ink">
                        No adoptions match your filters
                    </p>
                    <p class="mt-1 text-xs text-muted">
                        Try selecting a different status or clearing your search term.
                    </p>
                </li>
            </ul>
        </div>

        {{-- 2. ADOPTER VIEW (Grouped by Adopter) --}}
        <div id="adopterViewContainer" class="hidden space-y-4">
            @forelse ($adopterGroups as $group)
                @php
                    $uName = $group->user?->full_name ?: 'Unknown Adopter';
                    $uEmail = $group->user?->email ?: 'No email on record';
                    $initials = collect(explode(' ', $uName))
                        ->map(fn($part) => strtoupper(substr($part, 0, 1)))
                        ->take(2)
                        ->implode('');
                    $petCount = $group->adoptions->count();
                    $lastReportText = $group->latest_submitted
                        ? ('Last report ' . $group->latest_submitted->submitted_date->diffForHumans())
                        : 'No reports yet';
                    $groupLatestTs = $group->latest_submitted?->submitted_date ? $group->latest_submitted->submitted_date->timestamp : 0;
                    $groupNextDue = $group->adoptions->pluck('next_check_in')->filter()->sortBy('scheduled_date')->first();
                    $groupNextDueTs = $groupNextDue?->scheduled_date ? $groupNextDue->scheduled_date->timestamp : 9999999999;
                    $groupAllSearch = strtolower($uName . ' ' . $uEmail . ' ' . $group->adoptions->map(fn($a) => ($a->pet?->name ?? '') . ' ' . ($a->pet?->breed ?? ''))->implode(' '));
                @endphp

                <section class="adopter-group-card overflow-hidden rounded-xl border border-line bg-white shadow-sm"
                    data-adopter-name="{{ strtolower($uName) }}"
                    data-adopter-email="{{ strtolower($uEmail) }}"
                    data-search="{{ $groupAllSearch }}"
                    data-latest-ts="{{ $groupLatestTs }}"
                    data-next-due-ts="{{ $groupNextDueTs }}">
                    {{-- Adopter Group Header --}}
                    <header class="flex flex-wrap items-center gap-3 border-b border-line bg-surface px-5 py-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-xs font-bold text-ink ring-1 ring-line">
                            {{ $initials ?: 'AD' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-sm font-bold text-ink">{{ $uName }}</h2>
                            <p class="truncate text-xs text-muted">{{ $uEmail }}</p>
                        </div>
                        <p class="text-xs font-medium text-muted">
                            {{ $petCount }} {{ $petCount === 1 ? 'pet' : 'pets' }} &middot; {{ $lastReportText }}
                        </p>
                    </header>

                    {{-- Adopter Pets List --}}
                    <ul class="divide-y divide-line">
                        @foreach ($group->adoptions as $adoption)
                            @php
                                $primaryLog = $adoption->primary_check_in;
                                $primaryReport = $primaryLog ? $buildReportPayload($primaryLog) : null;
                                $petPhoto = $adoption->pet?->image_url;
                                $petName = $adoption->pet?->name ?: 'Unknown Pet';
                                $petBreed = $adoption->pet?->breed ?: ($adoption->pet?->species?->value ?: 'Pet');
                                $latestSub = $adoption->latest_submitted;
                                $nextCheck = $adoption->next_check_in;
                                $petSearchText = strtolower("{$petName} {$petBreed} {$uName} {$uEmail}");
                            @endphp

                            <li class="adopter-pet-row"
                                data-status="{{ $adoption->overall_status }}"
                                data-search="{{ $petSearchText }}"
                                id="adopter-pet-row-{{ $adoption->id }}">
                                <div class="px-5 py-4 monitoring-row-grid flex flex-wrap items-center gap-x-4 gap-y-3 hover:bg-surface/30 transition-colors">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    @if ($petPhoto && !str_contains($petPhoto, 'rcpp-logo'))
                                        <img src="{{ $petPhoto }}" alt="{{ $petName }}" class="h-12 w-12 shrink-0 rounded-lg object-cover ring-1 ring-line">
                                    @else
                                        <div class="h-12 w-12 shrink-0 rounded-lg bg-surface flex items-center justify-center text-brand ring-1 ring-line">
                                            <i class="fa-solid {{ ($adoption->pet?->species?->value ?? '') === 'Cat' ? 'fa-cat' : 'fa-dog' }}"></i>
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <span class="font-semibold text-l text-ink truncate block">{{ $petName }}</span>
                                        <span class="truncate text-xs text-muted block">{{ $petBreed }}</span>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <ol class="flex items-start gap-1">
                                        @foreach ($adoption->checkIns as $checkIn)
                                            @php
                                                $mKey = is_string($checkIn->milestone) ? $checkIn->milestone : $checkIn->milestone?->value;
                                                $mCode = $milestoneCodes[$mKey] ?? 'CI';
                                                $barColor = match ($checkIn->status_slug) {
                                                    'completed' => 'bg-completed-solid',
                                                    'pending' => 'bg-pending-bar',
                                                    'overdue' => 'bg-overdue-bar',
                                                    'flagged' => 'bg-flagged-solid',
                                                    default => 'bg-upcoming-bg',
                                                };
                                            @endphp
                                            <li class="flex flex-col items-center gap-1" title="{{ $checkIn->milestone_display }}: {{ $checkIn->status_display }}">
                                                <span class="h-1.5 w-6 rounded-full {{ $barColor }}"></span>
                                                <span class="text-[12px] font-semibold leading-none text-muted">{{ $mCode }}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>

                                <div class="min-w-0">
                                    @if ($latestSub)
                                        <p class="text-[14px] font-medium text-ink truncate">{{ $latestSub->milestone_display }}</p>
                                        <p class="text-xs text-muted truncate">{{ $latestSub->submitted_date ? $latestSub->submitted_date->diffForHumans() : '' }}</p>
                                    @else
                                        <p class="text-sm text-muted">No reports yet</p>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    @if ($nextCheck)
                                        <p class="text-[14px] font-medium {{ $nextCheck->status_slug === 'overdue' ? 'text-overdue-fg font-bold' : 'text-ink' }} truncate">
                                            {{ $nextCheck->milestone_display }}
                                        </p>
                                        <p class="text-xs text-muted truncate">{{ $nextCheck->due_date->format('M j') }}</p>
                                    @else
                                        <p class="text-sm font-medium text-completed-fg">All done</p>
                                    @endif
                                </div>

                                <div>
                                    <span class="badge {{ match ($adoption->overall_status) {
                                        'completed' => 'bg-completed-bg text-completed-fg',
                                        'pending' => 'bg-pending-bg text-pending-fg',
                                        'overdue' => 'bg-overdue-bg text-overdue-fg',
                                        'flagged' => 'bg-flagged-bg text-flagged-fg',
                                        default => 'bg-upcoming-bg text-upcoming-fg',
                                    } }}">
                                        {{ ucfirst($adoption->overall_status) }}
                                    </span>
                                </div>

                                <div class="flex justify-center">
                                    @if ($primaryReport)
                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            data-report="{{ json_encode($primaryReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                                            onclick="openMonitoringViewModal(this)"
                                        >
                                            <i class="fa-solid fa-eye text-muted"></i> View
                                        </button>
                                    @endif
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        class="accordion-toggle-btn h-7 w-7 rounded flex items-center justify-center text-muted hover:text-ink hover:bg-surface transition-all"
                                        aria-expanded="false"
                                        aria-controls="adopter-drawer-{{ $adoption->id }}"
                                        onclick="toggleMonitoringDrawer(
                                                'adopter-drawer-{{ $adoption->id }}',
                                                'adopter-chevron-{{ $adoption->id }}'
                                            )"
                                        title="View milestone breakdown"
                                    >
                                        <i
                                            class="fa-solid fa-chevron-down text-xs transition-transform duration-200"
                                            id="adopter-chevron-{{ $adoption->id }}"
                                        ></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Expanded Accordion History Drawer --}}
                            <div id="adopter-drawer-{{ $adoption->id }}" class="hidden overflow-hidden bg-surface/60 border-t border-line/60 px-5 py-4 lg:pl-16">
                                <div class="overflow-x-auto rounded-lg bg-white border border-line p-3 shadow-sm">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-center font-semibold text-muted border-b border-line">
                                                <th class="pb-2 pr-4">Milestone</th>
                                                <th class="pb-2 pr-4">Due Date</th>
                                                <th class="pb-2 pr-4">Submitted Date</th>
                                                <th class="pb-2 pr-4">Status</th>
                                                <th class="pb-2 pr-4">Reminders</th>
                                                <th class="pb-2 text-right">Actions</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-line/60">
                                            @foreach ($adoption->checkIns as $log)
                                                @php
                                                    $logReport = $buildReportPayload($log);
                                                    $logSummary = "{$uName} - {$petName} - {$log->milestone_display}";

                                                    $badgeStyle = match ($log->status_slug) {
                                                        'completed' => 'bg-completed-bg text-completed-fg',
                                                        'pending' => 'bg-pending-bg text-pending-fg',
                                                        'overdue' => 'bg-overdue-bg text-overdue-fg',
                                                        'flagged' => 'bg-flagged-bg text-flagged-fg',
                                                        default => 'bg-upcoming-bg text-upcoming-fg',
                                                    };
                                                @endphp

                                                <tr class="hover:bg-surface/30 transition-colors text-center" data-monitoring-status="{{ $log->status_slug }}">
                                                    <td class="py-2.5 pr-4 font-semibold text-ink">
                                                        {{ $log->milestone_display }}
                                                    </td>
                                                    <td class="py-2.5 pr-4 text-muted">
                                                        {{ $log->due_date->format('M j, Y') }}
                                                    </td>
                                                    <td class="py-2.5 pr-4 text-muted">
                                                        @if ($log->submitted_date)
                                                            <span class="font-medium text-ink">
                                                                {{ \App\Support\ManilaTime::format($log->submitted_date, 'M j, Y g:i A') }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400"> Not submitted</span>
                                                        @endif
                                                    </td>

                                                    <td class="py-3 pr-4">
                                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $badgeStyle }}">
                                                            {{ $log->status_display }}
                                                        </span>
                                                    </td>

                                                    <td class="py-2.5 pr-4 text-muted">
                                                        {{ $log->reminders_sent }} sent
                                                    </td>

                                                    <td class="py-2.5 text-right">
                                                        <div class="flex items-center justify-end gap-1.5">

                                                            {{-- View --}}
                                                            <button type="button" class="btn btn-secondary btn-sm !text-xs !py-1 !px-2.5"
                                                                data-report="{{ json_encode($logReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                                                                onclick="openMonitoringViewModal(this)"
                                                            >
                                                                <i class="fa-solid fa-eye"></i> View
                                                            </button>

                                                            {{-- Remind --}}
                                                            @if (!$log->submitted_date && $log->status_slug !== 'upcoming')
                                                                <button type="button" class="btn btn-yellow btn-sm !text-xs !py-1 !px-2.5" data-action="{{ route('admin.monitoring.reminder', $log) }}"
                                                                    data-summary="{{ $logSummary }}"
                                                                    onclick="openMonitoringReminderModal(this)"
                                                                >
                                                                    <i class="fa-solid fa-bell"></i> Remind
                                                                </button>
                                                            @endif

                                                            {{-- Flag --}}
                                                            @if (!$log->is_flagged || $log->resolved_at)
                                                                <button type="button" class="btn btn-danger btn-sm !text-xs !py-1 !px-2.5"
                                                                    data-action="{{ route('admin.monitoring.flag', $log) }}"
                                                                    data-summary="{{ $logSummary }}"
                                                                    onclick="openMonitoringFlagModal(this)"
                                                                >
                                                                    <i class="fa-solid fa-flag"></i> Flag
                                                                </button>
                                                            @else
                                                                <a class="btn btn-secondary btn-sm !text-xs !py-1 !px-2.5" href="{{ route('admin.monitoring.flagged') }}">
                                                                    <i class="fa-solid fa-magnifying-glass"></i>Review Flag
                                                                </a>
                                                            @endif

                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="py-12 text-center text-muted">No adopters found.</div>
        @endforelse

        <div id="adopterEmptyState" class="hidden flex-col items-center justify-center px-6 py-16 text-center rounded-xl border border-line bg-white shadow-sm">
            <i class="fa-solid fa-clipboard-question text-3xl text-muted mb-3"></i>
            <p class="text-sm font-semibold text-ink">
                No adoptions match your filters
            </p>
            <p class="mt-1 text-xs text-muted">
                Try selecting a different status or clearing your search term.
            </p>
        </div>
    </div>
</div>


    {{-- Reminder Modal --}}
    <div class="custom-modal-backdrop" id="monitoringReminderModal" role="dialog" aria-modal="true" aria-labelledby="monitoringReminderTitle">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2 id="monitoringReminderTitle">Send Welfare Check-in Reminder</h2>
                <small id="monitoringReminderSubheading" class="font-medium text-gray-500"></small>
            </div>
            <form id="monitoringReminderForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium leading-relaxed text-emerald-800">
                        The counter is updated only after the email is delivered. A check-in is automatically flagged after its second successful reminder.
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="monitoringCustomMessage">Custom Message (Optional)</label>
                        <textarea id="monitoringCustomMessage" name="custom_message" class="form-control remarks-textarea" rows="3" maxlength="1000" placeholder="Add an optional shelter note..."></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('monitoringReminderModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reminder</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Flag Case Modal --}}
    <div class="custom-modal-backdrop" id="monitoringFlagModal" role="dialog" aria-modal="true" aria-labelledby="monitoringFlagTitle">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2 id="monitoringFlagTitle">Flag Post-Adoption Case</h2>
                <small id="monitoringFlagSubheading" class="font-medium text-gray-500"></small>
            </div>
            <form id="monitoringFlagForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium leading-relaxed text-amber-900">
                        The reason is retained with the case and recorded in the administrative audit trail.
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="monitoringFlagReason">Reason for Flagging</label>
                        <textarea id="monitoringFlagReason" name="reason" class="form-control remarks-textarea" rows="3" required maxlength="2000" placeholder="Describe the welfare or follow-up concern..."></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('monitoringFlagModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Flag Case</button>
                </div>
            </form>
        </div>
    </div>

    {{-- View Report Modal --}}
    <div class="custom-modal-backdrop" id="monitoringViewModal" role="dialog" aria-modal="true" aria-labelledby="monitoringViewTitle">
        <div class="custom-modal custom-modal-wide">
            <div class="custom-modal-header">
                <h2 id="monitoringViewTitle">Post-Adoption Monitoring Report</h2>
                <small id="monitoringViewSubheading" class="font-medium text-gray-500"></small>
            </div>
            <div class="custom-modal-body space-y-3">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div>
                        <span class="block text-xs font-bold uppercase tracking-wide text-gray-500">Status</span>
                        <span id="monitoringViewStatus" class="badge mt-1"></span>
                    </div>
                    <div class="text-right text-xs text-gray-600">
                        <div>Due: <strong id="monitoringViewDue"></strong></div>
                        <div>Submitted: <strong id="monitoringViewSubmitted"></strong></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-lg border border-cream-200 bg-cream-50 p-3">
                        <span class="block text-xs font-bold text-gray-500">Pet Status</span>
                        <strong id="monitoringViewHealth" class="text-gray-800"></strong>
                    </div>
                    <div class="rounded-lg border border-cream-200 bg-cream-50 p-3">
                        <span class="block text-xs font-bold text-gray-500">Veterinary Notes</span>
                        <strong id="monitoringViewVet" class="text-gray-800"></strong>
                    </div>
                </div>

                <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-3 text-sm">
                    <div><span class="block text-xs font-bold text-gray-500">Eating Habits</span><p id="monitoringViewEating" class="mt-0.5 text-xs text-gray-700"></p></div>
                    <div><span class="block text-xs font-bold text-gray-500">Behavioral Observations</span><p id="monitoringViewBehavior" class="mt-0.5 text-xs text-gray-700"></p></div>
                    <div><span class="block text-xs font-bold text-gray-500">Living Conditions</span><p id="monitoringViewLiving" class="mt-0.5 text-xs text-gray-700"></p></div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                    <span class="block text-xs font-bold text-amber-800">Reported Concerns</span>
                    <p id="monitoringViewConcerns" class="mt-0.5 text-xs leading-relaxed text-amber-900"></p>
                    <span class="mt-3 block text-xs font-bold text-amber-800">Flag Reasons</span>
                    <p id="monitoringViewFlagReasons" class="mt-0.5 text-xs leading-relaxed text-amber-900"></p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700">
                    <strong class="block text-gray-800">Capture verification</strong>
                    <span class="block">Method: <span id="monitoringViewVerificationMethod"></span></span>
                    <span class="block break-all">Challenge: <span id="monitoringViewChallenge"></span></span>
                    <span class="block">Challenge issued: <span id="monitoringViewChallengeIssued"></span></span>
                    <span class="mt-2 block font-semibold text-gray-800">Video evidence</span>
                    <span class="block">Type: <span id="monitoringViewVideoMime"></span></span>
                    <span class="block">Duration check: <span id="monitoringViewDurationStatus"></span></span>
                    <span class="block">Browser duration: <span id="monitoringViewDeclaredDuration"></span></span>
                    <span class="block">Server duration: <span id="monitoringViewVerifiedDuration"></span></span>
                    <span class="block break-all">SHA-256: <span id="monitoringViewVideoHash"></span></span>
                    <div id="monitoringViewLegacyC2pa" class="mt-2 hidden border-t border-gray-200 pt-2">
                        <span class="block font-semibold text-gray-800">Legacy photo C2PA evidence</span>
                        <span class="block">Status: <span id="monitoringViewC2paStatus"></span></span>
                        <span class="block">Reason: <span id="monitoringViewC2paReason"></span></span>
                        <span class="block break-all">Manifest: <span id="monitoringViewManifest"></span></span>
                        <span class="block">Signed: <span id="monitoringViewSigned"></span></span>
                    </div>
                </div>

                <div id="monitoringViewVideoContainer" class="hidden rounded-lg border border-gray-200 bg-black p-2">
                    <video id="monitoringViewVideo" class="max-h-[420px] w-full rounded" controls playsinline preload="metadata"></video>
                    <a id="monitoringViewVideoLink" class="btn btn-secondary mt-2 flex w-full justify-center" href="#" target="_blank" rel="noopener">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Welfare Video
                    </a>
                </div>

                <a id="monitoringViewPhoto" class="btn btn-secondary hidden w-full justify-center" href="#" target="_blank" rel="noopener">
                    <i class="fa-solid fa-camera"></i> View Legacy Welfare Photo
                </a>
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeMonitoringViewModal()">Close</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // State
        let currentFilter = 'all';
        let currentView = 'pet';
        let currentSort = 'latest';
        let searchQuery = '';

        // DOM Elements
        const filterTabs = document.querySelectorAll('#monitoringFilterTabs .filter-btn');
        const searchInput = document.getElementById('monitoringSearchInput');
        const viewByPetBtn = document.getElementById('viewByPetBtn');
        const viewByAdopterBtn = document.getElementById('viewByAdopterBtn');
        const sortSelect = document.getElementById('monitoringSortSelect');
        const petViewContainer = document.getElementById('petViewContainer');
        const adopterViewContainer = document.getElementById('adopterViewContainer');
        const emptyState = document.getElementById('monitoringEmptyState');

        // Toggle Filter Tabs
        filterTabs.forEach(button => {
            button.addEventListener('click', () => {
                currentFilter = button.dataset.filterBtn;
                updateTabStyles();
                applyFilters();
            });
        });

        function updateTabStyles() {
            filterTabs.forEach(button => {
                const filter = button.dataset.filterBtn;
                if (filter === currentFilter) {
                    button.classList.add('active');
                    button.setAttribute('aria-selected', 'true');
                } else {
                    button.classList.remove('active');
                    button.setAttribute('aria-selected', 'false');
                }
            });
        }

        // Search Input Handler
        searchInput?.addEventListener('input', (e) => {
            searchQuery = e.target.value.trim().toLowerCase();
            applyFilters();
        });

        // View By Toggle Handler
        viewByPetBtn?.addEventListener('click', () => {
            currentView = 'pet';
            viewByPetBtn.className = 'view-by-btn active whitespace-nowrap rounded px-3 py-1 text-xs font-semibold transition-colors bg-white text-ink shadow-sm';
            viewByAdopterBtn.className = 'view-by-btn whitespace-nowrap rounded px-3 py-1 text-[12px] font-semibold transition-colors text-muted hover:text-ink';
            petViewContainer.classList.remove('hidden');
            document.getElementById('adopterViewContainer').classList.add('hidden');
            applyFilters();
        });

        viewByAdopterBtn?.addEventListener('click', () => {
            currentView = 'adopter';
            viewByAdopterBtn.className = 'view-by-btn active whitespace-nowrap rounded px-3 py-1 text-xs font-semibold transition-colors bg-white text-ink shadow-sm';
            viewByPetBtn.className = 'view-by-btn whitespace-nowrap rounded px-3 py-1 text-[12px] font-semibold transition-colors text-muted hover:text-ink';
            document.getElementById('adopterViewContainer').classList.remove('hidden');
            petViewContainer.classList.add('hidden');
            applyFilters();
        });

        // Sort Handler
        sortSelect?.addEventListener('change', (e) => {
            currentSort = e.target.value;

            sortItems();
            applyFilters();
        });

        function sortItems() {
            if (currentView === 'pet') {
                const list = document.getElementById('petViewList');
                if (!list) return;

                const items = Array.from(
                    list.querySelectorAll('.adoption-item')
                );

                items.sort((a, b) => {
                    if (currentSort === 'due') {
                        const dueA = Number(a.dataset.nextDueTs);
                        const dueB = Number(b.dataset.nextDueTs);

                        // Items without a due date go to the bottom
                        const safeDueA = dueA > 0 ? dueA : Infinity;
                        const safeDueB = dueB > 0 ? dueB : Infinity;

                        return safeDueA - safeDueB;
                    }

                    // NEWEST SUBMISSION FIRST
                    const latestA = Number(a.dataset.latestTs);
                    const latestB = Number(b.dataset.latestTs);

                    // Items without a submission go to the bottom
                    const safeLatestA = latestA > 0 ? latestA : 0;
                    const safeLatestB = latestB > 0 ? latestB : 0;

                    return safeLatestB - safeLatestA;
                });

                items.forEach(item => list.appendChild(item));

                // Keep empty state at the bottom
                if (emptyState) {
                    list.appendChild(emptyState);
                }
            }

            // Adopter View
            else {
                const container = document.getElementById('adopterViewContainer');
                if (!container) return;

                const cards = Array.from(
                    container.querySelectorAll('.adopter-group-card')
                );

                cards.sort((a, b) => {
                    if (currentSort === 'due') {
                        const dueA = Number(a.dataset.nextDueTs);
                        const dueB = Number(b.dataset.nextDueTs);

                        const safeDueA = dueA > 0 ? dueA : Infinity;
                        const safeDueB = dueB > 0 ? dueB : Infinity;

                        return safeDueA - safeDueB;
                    }

                    // NEWEST SUBMISSION FIRST
                    const latestA = Number(a.dataset.latestTs);
                    const latestB = Number(b.dataset.latestTs);

                    const safeLatestA = latestA > 0 ? latestA : 0;
                    const safeLatestB = latestB > 0 ? latestB : 0;

                    return safeLatestB - safeLatestA;
                });

                cards.forEach(card => container.appendChild(card));

                const adopterEmpty = document.getElementById('adopterEmptyState');
                if (adopterEmpty) {
                    container.appendChild(adopterEmpty);
                }
            }
        }

        // Apply Search and Filters
        function applyFilters() {
            let visibleCount = 0;

            if (currentView === 'pet') {
                const items = document.querySelectorAll('#petViewList .adoption-item');
                items.forEach(item => {
                    const status = item.dataset.status || '';
                    const text = item.dataset.search || '';

                    const matchesStatus = (currentFilter === 'all') || (status === currentFilter);
                    const matchesSearch = !searchQuery || text.includes(searchQuery);

                    if (matchesStatus && matchesSearch) {
                        item.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('hidden');
                    }
                });

                if (visibleCount === 0) {
                    emptyState?.classList.remove('hidden');
                    emptyState?.classList.add('flex');
                } else {
                    emptyState?.classList.add('hidden');
                    emptyState?.classList.remove('flex');
                }
            } else {
                const cards = document.querySelectorAll('#adopterViewContainer .adopter-group-card');
                const adopterEmpty = document.getElementById('adopterEmptyState');

                cards.forEach(card => {
                    const petRows = card.querySelectorAll('.adopter-pet-row');
                    let matchingPetsInCard = 0;

                    petRows.forEach(row => {
                        const status = row.dataset.status || '';
                        const text = row.dataset.search || '';

                        const matchesStatus = (currentFilter === 'all') || (status === currentFilter);
                        const matchesSearch = !searchQuery || text.includes(searchQuery);

                        if (matchesStatus && matchesSearch) {
                            row.classList.remove('hidden');
                            matchingPetsInCard++;
                        } else {
                            row.classList.add('hidden');
                        }
                    });

                    // Card is shown if at least one pet matches the status filter and search query
                    if (matchingPetsInCard > 0) {
                        card.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        card.classList.add('hidden');
                    }
                });

                if (visibleCount === 0) {
                    adopterEmpty?.classList.remove('hidden');
                    adopterEmpty?.classList.add('flex');
                } else {
                    adopterEmpty?.classList.add('hidden');
                    adopterEmpty?.classList.remove('flex');
                }
            }
        }

        // Accordion Drawer Toggle
        function toggleAdoptionDrawer(adoptionId) {
            const drawer = document.getElementById(`drawer-${adoptionId}`);
            const chevron = document.getElementById(`chevron-${adoptionId}`);
            if (!drawer) return;

            const isExpanded = !drawer.classList.contains('hidden');
            if (isExpanded) {
                drawer.classList.add('hidden');
                chevron?.classList.remove('rotate-180');
            } else {
                drawer.classList.remove('hidden');
                chevron?.classList.add('rotate-180');
            }
        }
        // function toggleAdopterDrawer(adoptionId) {
        //     const drawer = document.getElementById(`adopter-drawer-${adoptionId}`);
        //     const chevron = document.getElementById(`adopter-chevron-${adoptionId}`);

        //     if (!drawer) return;

        //     const isExpanded = !drawer.classList.contains('hidden');

        //     if (isExpanded) {
        //         drawer.classList.add('hidden');
        //         chevron?.classList.remove('rotate-180');
        //     } else {
        //         drawer.classList.remove('hidden');
        //         chevron?.classList.add('rotate-180');
        //     }
        // }

        function toggleMonitoringDrawer(drawerId, chevronId) {
            const drawer = document.getElementById(drawerId);
            const chevron = document.getElementById(chevronId);

            if (!drawer) return;

            const isExpanded = !drawer.classList.contains('hidden');

            if (isExpanded) {
                drawer.classList.add('hidden');
                chevron?.classList.remove('rotate-180');
            } else {
                drawer.classList.remove('hidden');
                chevron?.classList.add('rotate-180');
            }
        }

        function openMonitoringReminderModal(button) {
            document.getElementById('monitoringReminderSubheading').textContent = button.dataset.summary || '';
            document.getElementById('monitoringReminderForm').action = button.dataset.action;
            document.getElementById('monitoringCustomMessage').value = '';
            openModal('monitoringReminderModal');
        }

        function openMonitoringFlagModal(button) {
            document.getElementById('monitoringFlagSubheading').textContent = button.dataset.summary || '';
            document.getElementById('monitoringFlagForm').action = button.dataset.action;
            document.getElementById('monitoringFlagReason').value = '';
            openModal('monitoringFlagModal');
        }

        function setMonitoringText(id, value) {
            document.getElementById(id).textContent = value || 'Not reported';
        }

        function openMonitoringViewModal(button) {
            let data = {};

            try {
                data = JSON.parse(button.dataset.report || '{}');
            } catch (error) {
                window.PAIRfectAdmin?.showToast('The monitoring report could not be displayed.', 'error');
                return;
            }

            setMonitoringText('monitoringViewSubheading', `${data.adopter} \u00b7 ${data.pet} \u00b7 ${data.milestone}`);
            setMonitoringText('monitoringViewDue', data.due_date);
            setMonitoringText('monitoringViewSubmitted', data.submitted_date);
            setMonitoringText('monitoringViewHealth', data.health);
            setMonitoringText('monitoringViewVet', data.vet);
            setMonitoringText('monitoringViewEating', data.eating);
            setMonitoringText('monitoringViewBehavior', data.behavior);
            setMonitoringText('monitoringViewLiving', data.living);
            setMonitoringText('monitoringViewConcerns', data.concerns);
            setMonitoringText('monitoringViewFlagReasons', data.flag_reasons);
            setMonitoringText('monitoringViewVerificationMethod', data.verification_method);
            setMonitoringText('monitoringViewChallenge', data.challenge_id);
            setMonitoringText('monitoringViewChallengeIssued', data.challenge_issued_at);
            setMonitoringText('monitoringViewVideoMime', data.video_mime_type);
            setMonitoringText('monitoringViewDurationStatus', formatMonitoringVerificationStatus(data.duration_verification_status));
            setMonitoringText('monitoringViewDeclaredDuration', formatMonitoringDuration(data.declared_duration_ms));
            setMonitoringText('monitoringViewVerifiedDuration', formatMonitoringDuration(data.verified_duration_ms));
            setMonitoringText('monitoringViewVideoHash', data.video_sha256);
            setMonitoringText('monitoringViewC2paStatus', data.legacy_c2pa_status);
            setMonitoringText('monitoringViewC2paReason', data.legacy_c2pa_reason);
            setMonitoringText('monitoringViewManifest', data.legacy_manifest_id);
            setMonitoringText('monitoringViewSigned', data.legacy_signed_at);

            const legacyC2pa = document.getElementById('monitoringViewLegacyC2pa');
            const hasLegacyC2pa = Boolean(data.legacy_c2pa_status || data.legacy_c2pa_reason
                || data.legacy_manifest_id || data.legacy_signed_at);
            legacyC2pa.classList.toggle('hidden', !hasLegacyC2pa);

            const status = document.getElementById('monitoringViewStatus');
            status.textContent = data.status || 'Unknown';
            status.className = `badge badge-${data.status_slug || 'upcoming'} mt-1`;

            const videoContainer = document.getElementById('monitoringViewVideoContainer');
            const video = document.getElementById('monitoringViewVideo');
            const videoLink = document.getElementById('monitoringViewVideoLink');
            video.pause();
            video.removeAttribute('src');
            video.load();
            if (data.video_url) {
                video.src = data.video_url;
                video.load();
                videoLink.href = data.video_url;
                videoContainer.classList.remove('hidden');
            } else {
                videoLink.href = '#';
                videoContainer.classList.add('hidden');
            }

            const photo = document.getElementById('monitoringViewPhoto');
            if (data.photo_url) {
                photo.href = data.photo_url;
                photo.classList.remove('hidden');
                photo.classList.add('flex');
            } else {
                photo.href = '#';
                photo.classList.add('hidden');
                photo.classList.remove('flex');
            }

            openModal('monitoringViewModal');
        }

        function formatMonitoringDuration(milliseconds) {
            const duration = Number(milliseconds);

            return Number.isFinite(duration) && duration > 0
                ? `${(duration / 1000).toFixed(2)} seconds`
                : 'Not available';
        }

        function formatMonitoringVerificationStatus(value) {
            if (!value || value === 'Not available') return 'Not available';

            const label = String(value).replaceAll('_', ' ');

            return label.charAt(0).toUpperCase() + label.slice(1);
        }

        function closeMonitoringViewModal() {
            const video = document.getElementById('monitoringViewVideo');
            video.pause();
            closeModal('monitoringViewModal');
        }

        document.getElementById('monitoringViewModal')?.addEventListener('click', (event) => {
            if (event.target === event.currentTarget) {
                document.getElementById('monitoringViewVideo').pause();
            }
        });
    </script>
@endpush

