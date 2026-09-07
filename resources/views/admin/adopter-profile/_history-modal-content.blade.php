@php
    $hasPlacement = $selectedPlacement !== null;
@endphp

<div
    class="custom-modal-header flex items-start justify-between gap-4"
    @if ($hasPlacement) data-selected-application-id="{{ $selectedPlacement->id }}" @endif
>
    <div>
        <h2 id="profileHistoryTitle">Adoption History</h2>
        <small>
            Applicant: {{ $adopter->full_name }}
            @if ($hasPlacement)
                &middot; Pet: {{ $selectedPlacement->pet?->name ?? 'Unavailable pet record' }}
            @endif
        </small>
    </div>
    <div class="flex items-center gap-3">
        @if ($hasPlacement)
            <span class="badge {{ $monitoringMetrics['header_badge_class'] }}">
                {{ $monitoringMetrics['header_badge_label'] }}
            </span>
        @else
            <span class="badge badge-upcoming">No placement</span>
        @endif
    </div>
</div>

<div class="custom-modal-body custom-scrollbar">
    @if ($hasPlacement)
        <section
            data-selected-application-id="{{ $selectedPlacement->id }}"
            data-monitoring-status="{{ $monitoringMetrics['overall_status'] }}"
        >
            @if ($approvedPlacements->count() > 1)
                <div class="mb-5 rounded-xl border border-[#ddd] bg-neutral-light p-3">
                    <label class="form-label" for="profileHistoryPlacement">Placement being monitored</label>
                    <select
                        id="profileHistoryPlacement"
                        class="form-select"
                        data-placement-switcher
                        onchange="switchProfileHistory(this.value)"
                    >
                        @foreach ($approvedPlacements as $placement)
                            @php
                                $placementDate = $placement->adopted_at
                                    ?? $placement->queue_closed_at
                                    ?? $placement->updated_at
                                    ?? $placement->created_at;
                            @endphp
                            <option
                                value="{{ route('admin.adopter-profiles.history', ['user' => $adopter, 'application' => $placement->id]) }}"
                                data-placement-application-id="{{ $placement->id }}"
                                @selected($selectedPlacement->id === $placement->id)
                            >
                                {{ $placement->pet?->name ?? 'Unavailable pet record' }} — adopted {{ \App\Support\ManilaTime::format($placementDate, 'M j, Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <dl class="grid grid-cols-3 gap-3 mb-6 max-[576px]:grid-cols-1" aria-label="Placement monitoring summary">
                <div class="rounded-xl border border-[#ccc] bg-white p-3.5">
                    <dt class="text-xs font-semibold text-[#777]">Adopted on</dt>
                    <dd class="mt-1 font-primary text-base font-bold">
                        <time datetime="{{ $adoptionDate->toDateString() }}">{{ $adoptionDate->format('F j, Y') }}</time>
                    </dd>
                </div>
                <div class="rounded-xl border border-[#ccc] bg-white p-3.5">
                    <dt class="text-xs font-semibold text-[#777]">Monitoring</dt>
                    <dd class="mt-1 font-primary text-base font-bold">{{ $monitoringMetrics['overall_status_label'] }}</dd>
                </div>
                <div class="rounded-xl border border-[#ccc] bg-white p-3.5">
                    <dt class="text-xs font-semibold text-[#777]">Reports filed</dt>
                    <dd class="mt-1 font-primary text-base font-bold">{{ $monitoringMetrics['reports_fraction'] }}</dd>
                </div>
            </dl>

            <div class="mb-3 flex items-center gap-2 font-primary font-bold">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                <h3>Post-adoption Monitoring — 3-3-3 check-ins</h3>
            </div>

            <ol class="mb-6 grid grid-cols-[1fr_auto_1fr_auto_1fr] items-center gap-2 max-[576px]:grid-cols-1" aria-label="Post-adoption check-in timeline">
                @foreach ($monitoringTimeline as $item)
                    @php
                        $displayStatusSlug = $item['is_submitted'] ? 'completed' : $item['status_slug'];
                        $displayBadgeClass = 'badge-'.$displayStatusSlug;
                    @endphp
                    <li
                        class="contents"
                        data-milestone="{{ $item['milestone_value'] }}"
                        data-report-status="{{ $displayStatusSlug }}"
                        data-has-unresolved-flag="{{ $item['has_unresolved_flag'] ? 'true' : 'false' }}"
                    >
                        <div class="badge {{ $item['has_unresolved_flag'] ? 'badge-flagged' : $displayBadgeClass }} w-full justify-center rounded-lg px-3 py-2 text-center">
                            <span>{{ $item['short_label'] }}</span>
                            @if ($item['target_date'])
                                <time datetime="{{ $item['target_date']->toDateString() }}">({{ $item['target_date']->format('M j') }})</time>
                            @endif
                            @if ($item['has_unresolved_flag'])
                                <i class="fa-solid fa-triangle-exclamation" aria-label="Flagged for review"></i>
                            @endif
                        </div>
                        @if (! $loop->last)
                            <i class="fa-solid fa-arrow-right text-[#888] max-[576px]:rotate-90 max-[576px]:justify-self-center" aria-hidden="true"></i>
                        @endif
                    </li>
                @endforeach
            </ol>

            <div class="mb-3 flex items-center gap-2 font-primary font-bold">
                <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                <h3>Individual report summaries</h3>
            </div>

            <ul class="mb-6 flex flex-col gap-3" aria-label="Individual post-adoption report summaries">
                @foreach ($monitoringTimeline as $item)
                    @php
                        $log = $item['log'];
                        $displayStatusSlug = $item['is_submitted'] ? 'completed' : $item['status_slug'];
                        $displayStatusLabel = $item['is_submitted'] ? 'Completed' : $item['status_label'];
                        $displayBadgeClass = 'badge-'.$displayStatusSlug;
                        $petStatus = $log?->pet_current_status?->value
                            ?? data_get($log?->survey_data, 'pet_current_status');
                    @endphp
                    <li
                        class="rounded-xl border border-[#ccc] bg-white p-3.5"
                        data-milestone="{{ $item['milestone_value'] }}"
                        data-report-status="{{ $displayStatusSlug }}"
                        data-has-unresolved-flag="{{ $item['has_unresolved_flag'] ? 'true' : 'false' }}"
                    >
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="badge {{ $displayBadgeClass }}">{{ $item['short_label'] }}</span>
                            @if ($item['target_date'])
                                <time class="text-sm font-semibold" datetime="{{ $item['target_date']->toDateString() }}">
                                    {{ $item['target_date']->format('M j, Y') }}
                                </time>
                            @endif
                            <div class="ml-auto flex items-center gap-2 flex-wrap">
                                <span class="badge {{ $displayBadgeClass }}">{{ $displayStatusLabel }}</span>
                                @if ($item['has_unresolved_flag'])
                                    <span class="badge badge-flagged">Flagged</span>
                                @endif
                            </div>
                        </div>

                        @if ($item['is_submitted'])
                            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-[#666]">
                                <span>Filed {{ \App\Support\ManilaTime::format($item['submitted_date'], 'M j, Y \a\t g:i A') }}</span>
                                @if ($petStatus)
                                    <span>Reported pet status: <strong>{{ $petStatus }}</strong></span>
                                @endif
                            </div>
                        @elseif ($log === null)
                            <p class="mt-2 text-xs text-status-processing-text">
                                Target date projected from the adoption date; the scheduler has not created this check-in record yet.
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @else
        <div class="modal-note caution">
            <strong>No completed adoption placement yet.</strong>
            Post-adoption welfare monitoring becomes available after one of this account's applications is approved.
        </div>
    @endif

    <details
        class="rounded-xl border border-[#ddd] bg-neutral-light"
        data-application-attempt-history
        @if (! $hasPlacement) open @endif
    >
        <summary class="cursor-pointer px-4 py-3 font-primary font-bold">
            All application attempts ({{ $applications->count() }})
        </summary>
        <div class="border-t border-[#ddd] p-3 flex flex-col gap-2.5">
            @foreach ($applications as $application)
                <article
                    class="rounded-lg border border-[#ddd] bg-white p-3"
                    data-history-application-id="{{ $application->id }}"
                >
                    <div class="flex items-start justify-between gap-3 max-[576px]:flex-col">
                        <div>
                            <h4 class="font-primary font-bold">{{ $application->pet?->name ?? 'Unavailable pet record' }}</h4>
                            <p class="mt-1 text-xs text-[#777]">
                                Application #{{ $application->id }} &middot;
                                Submitted <time datetime="{{ \App\Support\ManilaTime::at($application->created_at)->toDateString() }}">{{ \App\Support\ManilaTime::format($application->created_at, 'M d, Y') }}</time>
                            </p>
                        </div>
                        <span class="badge {{ $application->status_badge_class }}">{{ $application->status_display }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-3 flex-wrap text-xs text-[#777]">
                        <span>Last updated {{ \App\Support\ManilaTime::format($application->updated_at, 'M j, Y \a\t g:i A') }}</span>
                        @if ($application->document_path)
                            <a
                                href="{{ route('admin.adopter-profiles.document', $application) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-secondary btn-sm"
                                data-history-document-for="{{ $application->id }}"
                            >
                                <i class="fa-solid fa-file-shield"></i> View Document
                            </a>
                        @else
                            <span>No document uploaded</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </details>
</div>

<div class="custom-modal-footer-1">
    <button type="button" class="btn btn-secondary" onclick="closeProfileHistory()">Close</button>
</div>
