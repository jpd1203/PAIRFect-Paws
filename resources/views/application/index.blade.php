@extends('layouts.app')

@section('title', 'My Application - PAIRfect Paws')

@php
    use App\Models\AdoptionApplication;
    $statusValue = $application?->status ?? -1;
    $pendingValue = AdoptionApplication::STATUS_PENDING;
    $scheduledValue = AdoptionApplication::STATUS_SCHEDULED;
    $underReviewValue = AdoptionApplication::STATUS_UNDER_REVIEW;
@endphp

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>My Application Status</h2>
            <p>Track the progress of your adoption application</p>
        </div>
    </div>

    <div class="content-area">

        @if (!$application)
            <div class="empty-state">
                <i class="fa-solid fa-file"></i>
                <h3>No application yet</h3>
                <p>Browse available pets and tap "Adopt Me!" to get started.</p>
            </div>
        @else
            <div class="application-card" id="applicationCard"
                 data-application-id="{{ $application->id }}"
                 data-last-updated="{{ $application->last_updated->toIso8601String() }}">

                <div class="card-header">
                    <h2>Application #{{ str_pad($application->id, 4, '0', STR_PAD_LEFT) }}</h2>

                    <span class="badge {{ $application->status_badge_class }}" id="statusBadge">
                        {{ $application->status_display }}
                    </span>
                </div>

                <div class="application-details">

                    <div class="detail-row">
                        <span class="label">Pet Requested</span>
                        <span class="value">
                            {{ $application->pet ? "{$application->pet->name} ({$application->pet->species_display}, {$application->pet->breed}, {$application->pet->age_display})" : '—' }}
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="label">Submitted on</span>
                        <span class="value">{{ $application->submitted_on->format('F j, Y') }}</span>
                    </div>

                    <div class="detail-row">
                        <span class="label">Last Updated</span>
                        <span class="value" id="lastUpdatedValue">{{ $application->last_updated->format('F j, Y') }}</span>
                    </div>

                </div>

                <div class="progress-section">

                    <h3>Application Progress</h3>

                    <div class="progress-flow" id="progressFlow">

                        <span class="step completed">Submitted</span>

                        <span class="arrow">&rarr;</span>

                        <span class="step {{ $statusValue == $pendingValue ? 'active' : ($statusValue > $pendingValue ? 'completed' : '') }}">
                            Pending
                        </span>

                        <span class="arrow">&rarr;</span>

                        <span class="step {{ $statusValue == $scheduledValue ? 'active' : ($statusValue > $scheduledValue ? 'completed' : '') }}">
                            Interview Scheduled
                        </span>

                        <span class="arrow">&rarr;</span>

                        <span class="step {{ $statusValue == $underReviewValue ? 'active' : ($statusValue > $underReviewValue ? 'completed' : '') }}">
                            Under Review
                        </span>

                        <span class="arrow">&rarr;</span>

                        @if ($statusValue == AdoptionApplication::STATUS_APPROVED)
                            <span class="step completed font-semibold">
                                Decision
                            </span>
                        @elseif ($statusValue == AdoptionApplication::STATUS_REJECTED)
                            <span class="step bg-[#fdf4f4] text-[#b91c1c] border border-red-300 font-semibold">
                                Decision
                            </span>
                        @else
                            <span class="step">
                                Decision
                            </span>
                        @endif

                    </div>

                </div>

                @if ($application->interview_notes || $application->decision_remarks)
                    <div class="note-section" id="noteSection">
                        <strong>Note:</strong>
                        {{ $application->decision_remarks ?: $application->interview_notes }}
                    </div>
                @endif

            </div>
        @endif

    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/my-application.js') }}" defer></script>
@endpush
