@extends('admin.layouts.app')

@section('title', 'Applications - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Applications</h2>
        <p>Review adoption applications and manage the interview pipeline.</p>
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
        <div class="my-4 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
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

    <div class="flex flex-wrap gap-3 items-center my-5">
        <input type="text" data-search-input data-search-scope="applicationTableBody" class="search-input flex-1 min-w-[220px]" placeholder="Search by applicant or pet name…">
        <button type="button" class="btn btn-primary" onclick="openTopScheduleModal()">Schedule Interview</button>
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="applicationTableBody">
        <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
        <button class="filter-btn badge-pending" data-filter-btn="pending">Pending</button>
        <button class="filter-btn badge-documentflagged" data-filter-btn="documentflagged">Document Update</button>
        <button class="filter-btn badge-primarycandidate" data-filter-btn="primarycandidate">Primary</button>
        <button class="filter-btn badge-waitlisted" data-filter-btn="waitlisted">Waitlisted</button>
        <button class="filter-btn badge-scheduled" data-filter-btn="scheduled">Scheduled</button>
        <button class="filter-btn badge-underreview" data-filter-btn="underreview">Under Review</button>
        <button class="filter-btn badge-approved" data-filter-btn="approved">Approved</button>
        <button class="filter-btn badge-rejected" data-filter-btn="rejected">Rejected</button>
        <button class="filter-btn" data-filter-btn="withdrawn">Withdrawn</button>
        <button class="filter-btn" data-filter-btn="noshow">No Show</button>
    </div>

    <div class="records-container custom-scrollbar">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr><th>Applicant</th><th>Pet</th><th>Submitted</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="applicationTableBody">
                    @forelse ($applications as $app)
                        <tr data-search-row data-search-text="{{ $app->first_name }} {{ $app->last_name }} {{ $app->pet?->name }}"
                            data-filter-row data-status="{{ $app->status_slug }}">
                            <td class="font-semibold">
                                {{ $app->first_name }} {{ $app->last_name }}
                                @if($app->is_primary_candidate)<span class="badge badge-primarycandidate ml-1">Primary</span>@endif
                            </td>
                            <td>{{ $app->pet?->name }}</td>
                            <td>{{ \App\Support\ManilaTime::format($app->created_at, 'M j, Y') }}</td>
                            <td><span class="badge {{ $app->status_badge_class }}">{{ $app->status_display }}</span></td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="openReviewModal({{ $app->id }})">View</button>
                                @if ($app->status_slug === 'scheduled')
                                    <button class="btn btn-yellow btn-sm" onclick="openAddNoteModal({{ $app->id }})">Add Notes</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-[#888] py-6">No applications yet.</td></tr>
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

    <script id="applicationData" type="application/json">
        {!! $applications->map(function ($app) {
            return [
                'id' => $app->id,
                'status' => $app->status_slug,
                'status_display' => $app->status_display,
                'is_primary' => $app->is_primary_candidate,
                'queue_position' => $app->queue_position,
                'admin_review_flagged' => (bool) $app->admin_review_flagged_at,
                'full_name' => "{$app->first_name} {$app->last_name}",
                'pet' => $app->pet?->name,
                'pet_details' => $app->pet ? "{$app->pet->species_display}, {$app->pet->breed}, {$app->pet->age_display}" : '',
                'submitted' => \App\Support\ManilaTime::format($app->created_at, 'M j'),
                'submitted_full' => \App\Support\ManilaTime::format($app->created_at, 'F j, Y'),
                'contact' => $app->phone_number,
                'email' => $app->email,
                'address' => $app->address,
                'physical_activity_level' => $app->physical_activity_level,
                'time_availability' => $app->time_availability,
                'prior_pet_experience' => $app->prior_pet_experience,
                'housing_type' => $app->housing_type,
                'household_composition' => $app->household_composition,
                'monthly_income_range' => $app->monthly_income_range,
                'document_url' => route('admin.applications.document', $app),
                'document_verification_status' => $app->document_verification_status?->value ?? 'Pending',
                'verification_url' => route('admin.applications.document-verification', $app),
                'has_compatibility' => (bool) $app->compatibility_result,
                'compatibility' => $app->compatibility_result,
                'has_history' => (bool) $app->priorHistory,
                'interview_notes' => $app->interview_notes,
                'interview_date' => $app->interview_date_display,
                'interview_time' => $app->interview_time_display,
                'interview_date_input' => $app->interview_date ? \App\Support\ManilaTime::format($app->interview_date, 'Y-m-d') : null,
                'interview_time_input' => $app->interview_date ? \App\Support\ManilaTime::format($app->interview_date, 'H:i') : null,
                'conducted_by' => $app->conducted_by,
                'decision_remarks' => $app->decision_remarks,
                'schedule_action' => route('admin.applications.schedule'),
                'notes_action' => route('admin.applications.notes', $app),
                'decide_action' => route('admin.applications.decide', $app),
                'queue_outcome_action' => route('admin.applications.queue-outcome', $app),
                'override_action' => route('admin.applications.override', $app),
                'can_override' => auth()->user()->isAdmin(),
                'history_url' => route('admin.applications.history', $app),
            ];
        })->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>

@endsection

@push('scripts')
    <script src="{{ asset('js/admin/application.js') }}" defer></script>
@endpush
