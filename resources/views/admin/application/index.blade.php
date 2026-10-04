@extends('admin.layouts.app')

@section('title', 'Applications - PAIRfect Paws Admin')

@section('notification-bell-in-header', true)
@section('content')
    <div class="main-content-header">
        <div class="heading-text">
            <h2>Applications</h2>
            <p>Review adoption applications and manage the interview pipeline.</p>
        </div>

        @include('partials.notification-bell')
    </div>

    @php
        $adcPath = trim((string) config('document_verification.google_application_credentials'));
        $adcPathIsAbsolute = str_starts_with($adcPath, '/')
            || str_starts_with($adcPath, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $adcPath) === 1;
        $adcFile = $adcPath === '' ? null : ($adcPathIsAbsolute ? $adcPath : base_path($adcPath));
    @endphp
    @if($adcFile !== null && !is_file($adcFile))
        <div class="my-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Automatic OCR is waiting for the configured Google Application Default Credentials file. New uploads will be held for authorized staff review until the service-account JSON is installed.
        </div>
    @endif

    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                showToast(@json(session('success')), 'success');
            });
        </script>
    @endif

    @if (session('warning'))
        <div class="my-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>{{ session('warning') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="my-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
            <div class="mb-1"><i class="fa-solid fa-circle-exclamation mr-2"></i>The requested action could not be completed:</div>
            <ul class="ml-7 list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    {{-- Unified Single-Line Interactive Toolbar --}}
    <form action="{{ route('admin.applications.index') }}" method="GET" id="applicationFilterForm"
          class="flex flex-wrap items-center gap-2.5 my-4 p-2.5 bg-white border border-gray-200/90 rounded-2xl shadow-xs"
          aria-label="Filter adoption applications">

        {{-- Live Search Input --}}
        <div class="relative flex-1 min-w-[200px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
            <input type="text" id="appSearchInput" data-search-input data-search-scope="applicationTableBody"
                   class="search-input !pl-9 !pr-3 !py-1.5 !h-10 text-sm w-full bg-gray-50/60 hover:bg-white focus:bg-white border-gray-300 rounded-xl transition"
                   placeholder="Search applicant or pet…" aria-label="Search applications">
        </div>

        {{-- From Date --}}
        <div class="flex items-center gap-1.5 shrink-0 bg-gray-50/60 hover:bg-white border border-gray-300 rounded-xl px-2.5 h-10 text-sm transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/10 focus-within:bg-white">
            <label for="appFromDate" class="text-xs font-bold text-gray-500 uppercase tracking-wider cursor-pointer select-none">From</label>
            <input type="date" id="appFromDate" name="from" value="{{ $filters['from'] ?? '' }}"
                   class="border-0 p-0 text-sm focus:outline-none focus:ring-0 text-gray-700 bg-transparent cursor-pointer"
                   aria-label="Application start date">
        </div>

        {{-- To Date --}}
        <div class="flex items-center gap-1.5 shrink-0 bg-gray-50/60 hover:bg-white border border-gray-300 rounded-xl px-2.5 h-10 text-sm transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/10 focus-within:bg-white">
            <label for="appToDate" class="text-xs font-bold text-gray-500 uppercase tracking-wider cursor-pointer select-none">To</label>
            <input type="date" id="appToDate" name="to" value="{{ $filters['to'] ?? '' }}"
                   class="border-0 p-0 text-sm focus:outline-none focus:ring-0 text-gray-700 bg-transparent cursor-pointer"
                   aria-label="Application end date">
        </div>

        {{-- Status Dropdown --}}
        <div class="shrink-0 min-w-[145px]">
            <select id="appStatusSelect" name="status"
                    class="form-select !h-10 !py-1.5 !px-3 text-sm font-medium border-gray-300 rounded-xl bg-gray-50/60 hover:bg-white focus:bg-white transition"
                    aria-label="Filter application status">
                <option value="">All statuses</option>
                @foreach (\App\Enums\ApplicationStatus::cases() as $reportStatus)
                    <option value="{{ $reportStatus->value }}" @selected(($filters['status'] ?? '') === $reportStatus->value)>{{ str($reportStatus->value)->headline() }}</option>
                @endforeach
            </select>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-1.5 shrink-0">
            <button type="submit" id="applyFiltersBtn" class="btn btn-primary !h-10 !px-3.5 text-sm whitespace-nowrap shadow-xs hover:shadow transition" title="Apply filters">
                <i class="fa-solid fa-filter text-xs"></i> Apply
            </button>
            <button type="button" id="clearFiltersBtn" class="btn btn-secondary !h-10 !px-3 text-sm whitespace-nowrap hover:bg-gray-100 transition" title="Reset all filters">
                <i class="fa-solid fa-rotate-left text-xs"></i> Clear
            </button>
            <button type="submit" formaction="{{ route('admin.applications.export') }}" class="btn btn-secondary !h-10 !px-3 text-sm whitespace-nowrap border-gray-300 hover:border-gray-400 transition" title="Export Applications CSV">
                <i class="fa-solid fa-download text-xs"></i> Export
            </button>
        </div>

        {{-- Schedule Interview CTA in same row --}}
        <div class="ml-auto shrink-0">
            <button type="button" class="btn btn-primary !h-10 !px-3.5 text-sm whitespace-nowrap shadow-xs hover:shadow transition" onclick="openTopScheduleModal()">
                <i class="fa-solid fa-calendar-check text-xs"></i> Schedule Interview
            </button>
        </div>
    </form>

    @php
        $appCounts = [
            'all' => $applications->count(),
            'pending' => $applications->filter(fn($a) => $a->status_slug === 'pending')->count(),
            'documentflagged' => $applications->filter(fn($a) => $a->status_slug === 'documentflagged')->count(),
            'primarycandidate' => $applications->filter(fn($a) => $a->status_slug === 'primarycandidate' || $a->is_primary_candidate)->count(),
            'waitlisted' => $applications->filter(fn($a) => $a->status_slug === 'waitlisted')->count(),
            'scheduled' => $applications->filter(fn($a) => $a->status_slug === 'scheduled')->count(),
            'underreview' => $applications->filter(fn($a) => $a->status_slug === 'underreview')->count(),
            'approved' => $applications->filter(fn($a) => $a->status_slug === 'approved')->count(),
            'rejected' => $applications->filter(fn($a) => $a->status_slug === 'rejected')->count(),
            'withdrawn' => $applications->filter(fn($a) => $a->status_slug === 'withdrawn')->count(),
            'noshow' => $applications->filter(fn($a) => $a->status_slug === 'noshow')->count(),
        ];
    @endphp

    <div class="filter-bar-container">
        <button type="button" class="tab-scroll-arrow tab-scroll-left" aria-label="Scroll tabs left" style="display: none;">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <div class="filter-bar" data-filter-bar data-filter-scope="applicationTableBody">
            <button class="filter-btn filter-all active" data-filter-btn="all">All ({{ $appCounts['all'] }})</button>
            <button class="filter-btn badge-pending" data-filter-btn="pending">Pending ({{ $appCounts['pending'] }})</button>
            <button class="filter-btn badge-documentflagged" data-filter-btn="documentflagged">Document Update ({{ $appCounts['documentflagged'] }})</button>
            <button class="filter-btn badge-primarycandidate" data-filter-btn="primarycandidate">Primary ({{ $appCounts['primarycandidate'] }})</button>
            <button class="filter-btn badge-waitlisted" data-filter-btn="waitlisted">Waitlisted ({{ $appCounts['waitlisted'] }})</button>
            <button class="filter-btn badge-scheduled" data-filter-btn="scheduled">Scheduled ({{ $appCounts['scheduled'] }})</button>
            <button class="filter-btn badge-underreview" data-filter-btn="underreview">Under Review ({{ $appCounts['underreview'] }})</button>
            <button class="filter-btn badge-approved" data-filter-btn="approved">Approved ({{ $appCounts['approved'] }})</button>
            <button class="filter-btn badge-rejected" data-filter-btn="rejected">Rejected ({{ $appCounts['rejected'] }})</button>
            <button class="filter-btn badge-withdrawn" data-filter-btn="withdrawn">Withdrawn ({{ $appCounts['withdrawn'] }})</button>
            <button class="filter-btn badge-noshow" data-filter-btn="noshow">No Show ({{ $appCounts['noshow'] }})</button>
        </div>
        <button type="button" class="tab-scroll-arrow tab-scroll-right" aria-label="Scroll tabs right" style="display: none;">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>

    <div class="records-container">
        <div class="table-responsive custom-scrollbar">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="!text-left px-5">Pet</th>
                        <th class="!text-left px-4">Applicant</th>
                        <th class="text-center px-4 whitespace-nowrap">Queue Rank</th>
                        <th class="text-center px-4 whitespace-nowrap">Submitted</th>
                        <th class="text-center px-4 whitespace-nowrap">Status</th>
                        <th class="text-center px-5 whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationTableBody">
                    <tr id="noFilterResultsRow" style="display: none;">
                        <td colspan="6" class="text-center py-12 text-gray-500">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i class="fa-solid fa-filter-circle-xmark text-3xl text-gray-400"></i>
                                <p class="font-medium text-gray-700">No applications match your filter criteria.</p>
                                <p class="text-xs text-gray-400">Try adjusting your date range, search query, or status.</p>
                                <button type="button" class="btn btn-secondary text-xs !py-1.5 !px-3 mt-1" onclick="resetApplicationFilters()">
                                    <i class="fa-solid fa-rotate-left mr-1"></i>Reset filters
                                </button>
                            </div>
                        </td>
                    </tr>
                    @forelse ($applications as $app)
                        @php
                            $highlightTarget = request('highlight') ?? request('application_id') ?? request('app');
                            $isHighlighted = $highlightTarget && (string) $highlightTarget === (string) $app->id;
                        @endphp
                        <tr data-search-row data-search-text="{{ $app->pet?->name }} {{ $app->first_name }} {{ $app->last_name }} {{ $app->email }}"
                            data-filter-row data-status="{{ $app->status_slug }} {{ strtolower($app->status->value) }}{{ $app->is_primary_candidate ? ' primarycandidate' : '' }}"
                            data-status-raw="{{ $app->status->value }}"
                            data-date="{{ $app->created_at->format('Y-m-d') }}"
                            id="application-row-{{ $app->id }}"
                            class="{{ $isHighlighted ? 'highlighted-application-row' : '' }}">
                            <td class="px-5 py-3.5 text-left">
                                @if ($app->pet)
                                    <div class="flex items-center gap-4">

                                        @if ($app->pet->photo_path)
                                            <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 border border-gray-200 bg-gray-100">
                                                <img
                                                    src="{{ $app->pet->image_url }}"
                                                    alt="{{ $app->pet->name }}"
                                                    class="w-full h-full object-cover">
                                            </div>
                                        @else
                                            <div class="w-12 h-12 rounded-xl shrink-0 flex items-center justify-center bg-maroon-50 text-maroon-600 border border-maroon-100 text-sm">
                                                <i class="fa-solid fa-{{ strtolower($app->pet->species?->value ?? $app->pet->species) === 'cat' ? 'cat' : 'dog' }}"></i>
                                            </div>
                                        @endif

                                        <div class="min-w-0">
                                            <p class="font-bold text-left text-gray-900 leading-snug">
                                                {{ $app->pet->name }}
                                            </p>

                                            <p class="text-xs text-gray-500 capitalize truncate text-left">
                                                {{ $app->pet->species_display }}
                                                &middot;
                                                {{ $app->pet->breed ?? 'Mix' }}
                                                &middot;
                                                {{ $app->pet->age_display }}
                                            </p>
                                        </div>

                                    </div>
                                @else
                                    <span class="text-gray-400">No pet assigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-left">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="text-gray-900 font-bold leading-tight">{{ $app->first_name }} {{ $app->last_name }}</span>
                                    @if($app->is_primary_candidate || (($historySummaries[$app->id]['review_status'] ?? 'no_recorded_concerns') !== 'no_recorded_concerns'))
                                        <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                            @if($app->is_primary_candidate)<span class="badge badge-primarycandidate">Primary</span>@endif
                                            @if(($historySummaries[$app->id]['review_status'] ?? 'no_recorded_concerns') !== 'no_recorded_concerns')
                                                <span class="badge badge-overdue">Needs Review</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                @if($app->queue_position)
                                    <span class="badge" style="background:#f3f4f6;color:#374151">#{{ $app->queue_position }}</span><br>
                                    <span style="font-size:0.75rem;color:var(--muted)">Score: {{ isset($app->compatibility_result['overall']) ? $app->compatibility_result['overall'].'%' : 'N/A' }}</span>
                                @else
                                    <span style="font-size:0.75rem;color:var(--muted)">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">{{ \App\Support\ManilaTime::format($app->created_at, 'M j, Y') }}</td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap"><span class="badge {{ $app->status_badge_class }}">{{ $app->status_display }}</span></td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="inline-flex flex-col items-center justify-center gap-2">
                                    @if ($app->reschedule_status === 'pending')
                                        <span class="badge badge-scheduled">Reschedule Requested</span>
                                    @endif
                                    <button class="btn btn-secondary btn-sm" onclick="openReviewModal({{ $app->id }})"><i class="fa-solid fa-eye"></i>View</button>
                                    @if ($app->status_slug === 'scheduled')
                                        <button class="btn btn-yellow btn-sm" onclick="openAddNoteModal({{ $app->id }})"><i class="fa-solid fa-note-sticky"></i>Add Notes</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-[#888] py-6">No applications yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.application._review-modal')
    @include('admin.application._schedule-modal', ['volunteers' => $volunteers])
    @include('admin.application._note-modal')
    @include('admin.application._history-modal')
    @include('admin.application._compatibility-modal')
    @if(auth()->user()->isAdmin())
        @include('admin.application._decision-modal')
    @endif

    <script id="applicationData" type="application/json">
        {!! $applications->map(function ($app) use ($historySummaries) {
            return [
                'id' => $app->id,
                'pet_id' => $app->pet_id,
                'status' => $app->status_slug,
                'status_display' => $app->status_display,
                'is_primary' => $app->is_primary_candidate,
                'queue_position' => $app->queue_position,
                'knn_score' => $app->knn_score,
                'admin_review_flagged' => (bool) $app->admin_review_flagged_at,
                'full_name' => "{$app->first_name} {$app->last_name}",
                'pet' => $app->pet?->name,
                'pet_details' => $app->pet ? "{$app->pet->species_display}, {$app->pet->breed}, {$app->pet->age_display}" : '',
                'submitted' => \App\Support\ManilaTime::format($app->created_at, 'M j'),
                'submitted_full' => \App\Support\ManilaTime::format($app->created_at, 'F j, Y'),
                'contact' => $app->phone_number,
                'email' => $app->email,
                'address' => $app->address,
                'motivation_statement' => $app->motivation_statement,
                'physical_activity_level' => $app->physical_activity_level,
                'time_availability' => $app->time_availability,
                'prior_pet_experience' => $app->prior_pet_experience,
                'housing_type' => $app->housing_type,
                'household_composition' => $app->household_composition,
                'monthly_income_range' => $app->monthly_income_range,
                'document_url' => route('admin.applications.document', $app),
                'document_verification_status' => $app->document_verification_status?->value ?? 'Pending',
                'verification_url' => route('admin.applications.document-verification', $app),
                'has_compatibility' => $app->compatibility_result !== null,
                'compatibility' => $app->compatibility_result ?? ['overall' => 0, 'rows' => []],
                'has_history' => (bool) $app->priorHistory,
                'interview_notes' => $app->interview_notes,
                'interview_date' => $app->interview_date_display,
                'interview_time' => $app->interview_time_display,
                'interview_date_input' => $app->interview_date ? \App\Support\ManilaTime::format($app->interview_date, 'Y-m-d') : null,
                'interview_time_input' => $app->interview_date ? \App\Support\ManilaTime::format($app->interview_date, 'H:i') : null,
                'conducted_by' => $app->conducted_by,
                'decision_remarks' => $app->decision_remarks,
                'reschedule_status' => $app->reschedule_status,
                'reschedule_reason' => $app->reschedule_reason,
                'reschedule_options' => $app->reschedule_options ?? [],
                'reschedule_decline_action' => route('admin.applications.reschedule.decline', $app),
                'schedule_action' => route('admin.applications.schedule'),
                'notes_action' => route('admin.applications.notes', $app),
                'decide_action' => route('admin.applications.decide', $app),
                'queue_outcome_action' => route('admin.applications.queue-outcome', $app),
                'override_action' => route('admin.applications.override', $app),
                'can_override' => (bool) auth()->user()?->isAdmin(),
                'history_url' => $app->user_id ? route('admin.adopter-profiles.history', $app->user_id) : null,
                'history_summary' => $historySummaries[$app->id] ?? null,
            ];
        })->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>

@endsection

@push('scripts')
    <script src="{{ asset('js/admin/application.js') }}?v={{ filemtime(public_path('js/admin/application.js')) }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            let highlightId = urlParams.get('highlight') || urlParams.get('application_id') || urlParams.get('app');
            if (!highlightId && window.location.hash) {
                const match = window.location.hash.match(/\d+/);
                if (match) {
                    highlightId = match[0];
                }
            }
            if (highlightId) {
                const row = document.getElementById('application-row-' + highlightId);
                if (row) {
                    row.classList.add('highlighted-application-row');
                    setTimeout(() => {
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 200);

                    setTimeout(() => {
                        row.classList.remove('highlighted-application-row');
                    }, 4000);
                }
            }
        });
    </script>
@endpush
