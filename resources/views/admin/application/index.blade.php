@extends('admin.layouts.app')

@section('title', 'Applications - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Applications</h2>
        <p>Review adoption applications and manage the interview pipeline.</p>
    </div>

    <div class="flex flex-wrap gap-3 items-center my-5">
        <input type="text" data-search-input data-search-scope="applicationTableBody" class="search-input flex-1 min-w-[220px]" placeholder="Search by applicant or pet name…">
        <button class="btn btn-primary" onclick="openModal('addVolunteerModal')"> Schedule Interview</button>
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="applicationTableBody">
        <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
        <button class="filter-btn badge-pending" data-filter-btn="pending">Pending</button>
        <button class="filter-btn badge-scheduled" data-filter-btn="scheduled">Scheduled</button>
        <button class="filter-btn badge-underreview" data-filter-btn="underreview">Under Review</button>
        <button class="filter-btn badge-approved" data-filter-btn="approved">Approved</button>
        <button class="filter-btn badge-rejected" data-filter-btn="rejected">Rejected</button>
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr><th>Applicant</th><th>Pet</th><th>Submitted</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="applicationTableBody">
                    @forelse ($applications as $app)
                        <tr data-search-row data-search-text="{{ $app->first_name }} {{ $app->last_name }} {{ $app->pet?->name }}"
                            data-filter-row data-status="{{ $app->status_slug }}">
                            <td class="font-semibold">{{ $app->first_name }} {{ $app->last_name }}</td>
                            <td>{{ $app->pet?->name }}</td>
                            <td>{{ $app->created_at->format('M j, Y') }}</td>
                            <td><span class="badge {{ $app->status_badge_class }}">{{ $app->status_display }}</span></td>
                            <td>
                                @if ($app->status_slug === 'scheduled')
                                    <button class="btn btn-yellow btn-sm" onclick="openAddNoteModal({{ $app->id }})">Add Notes</button>
                                @else
                                    <button class="btn btn-secondary btn-sm" onclick="openReviewModal({{ $app->id }})">View</button>
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
                'full_name' => "{$app->first_name} {$app->last_name}",
                'pet' => $app->pet?->name,
                'pet_details' => $app->pet ? "{$app->pet->species}, {$app->pet->breed}, {$app->pet->age_display}" : '',
                'submitted' => $app->created_at->format('M j'),
                'submitted_full' => $app->created_at->format('F j, Y'),
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
                'has_compatibility' => (bool) $app->compatibility_result,
                'compatibility' => $app->compatibility_result,
                'has_history' => (bool) $app->priorHistory,
                'interview_notes' => $app->interview_notes,
                'interview_date' => $app->interview_date_display,
                'interview_time' => $app->interview_time_display,
                'conducted_by' => $app->conducted_by,
                'decision_remarks' => $app->decision_remarks,
                'schedule_action' => route('admin.applications.schedule'),
                'notes_action' => route('admin.applications.notes', $app),
                'decide_action' => route('admin.applications.decide', $app),
                'history_url' => route('admin.applications.history', $app),
            ];
        })->toJson() !!}
    </script>

@endsection

@push('scripts')
    <script src="{{ asset('js/admin/application.js') }}" defer></script>
@endpush
