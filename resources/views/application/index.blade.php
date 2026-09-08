@extends('layouts.app')

@section('title', 'My Applications - PAIRfect Paws')

@section('content')

    <div class="nonsticky-header custom-scrollbar">
        <div class="heading-text">
            <h2>My Applications</h2>
            <p>Track the progress of all your adoption applications</p>
        </div>
    
        <div class="content-area-nonsticky">
            @if(session('success'))
                <div class="mb-5 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="mb-5 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">{{ session('warning') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @if ($applications->isEmpty())
                <div class="empty-state">
                    <i class="fa-solid fa-file"></i>
                    <h3>No applications yet</h3>
                    <p>Browse available pets and tap "Adopt Me!" to get started.</p>
                </div>
            @else
                <div class="flex flex-col">
                    @foreach ($applications as $application)
                        @php
                            $status = $application->status->value;
                            $progressStage = match ($status) {
                                'Pending', 'DocumentFlagged', 'PrimaryCandidate', 'Waitlisted' => 0,
                                'InterviewScheduled' => 1,
                                'UnderReview' => 2,
                                'Approved', 'Rejected', 'Withdrawn', 'NoShow', 'Closed' => 3,
                                default => -1,
                            };
                            $unsuccessful = in_array($status, ['Rejected', 'Withdrawn', 'NoShow', 'Closed'], true);
                        @endphp

                        <article class="application-card shadow-card"
                                data-application-id="{{ $application->id }}"
                                data-last-updated="{{ $application->last_updated->toIso8601String() }}">
                            <div class="card-header">
                                <div>
                                    <h2>Application #{{ str_pad($application->id, 4, '0', STR_PAD_LEFT) }}</h2>
                                    <p class="text-sm text-[#777] mt-1">
                                        Submitted {{ \App\Support\ManilaTime::format($application->submitted_on, 'F j, Y') }}
                                    </p>
                                </div>

                                <span class="badge {{ $application->status_badge_class }}">
                                    {{ $application->status_display }}
                                </span>
                            </div>

                            <div class="application-details">
                                <div class="detail-row">
                                    <span class="label">Document Verification</span>
                                    <span class="value font-semibold">
                                        {{ match($application->document_verification_status?->value) {
                                            'Verified' => 'Verified by OCR',
                                            'NeedsResubmission' => 'Replacement Required',
                                            'ManualReview' => 'Awaiting Staff Review',
                                            'LegacyReview' => 'Legacy Staff Review',
                                            default => 'Pending',
                                        } }}
                                    </span>
                                </div>

                                @if($application->document_verification_status?->value === 'NeedsResubmission')
                                    <div class="note-section bg-[#fdf4f4] border border-red-300">
                                        <strong>Document or application details require correction:</strong>
                                        <ul class="list-disc ml-5 mt-1">
                                            @foreach($application->document_verification_reasons ?? [] as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                        @if($application->canUploadReplacementDocument())
                                            <form method="POST" action="{{ route('applications.document.replace', $application) }}" enctype="multipart/form-data" class="mt-4">
                                                @csrf
                                                <p class="mt-2 text-sm">You may submit one follow-up document. If the name or address entered on the application is incorrect, contact shelter staff instead.</p>
                                                <label class="block font-semibold mb-2 mt-3" for="replacement_document_{{ $application->id }}">Upload a matching government ID or proof of address</label>
                                                <input id="replacement_document_{{ $application->id }}" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                                                <button type="submit" class="btn btn-primary mt-3">Submit Follow-up Document</button>
                                            </form>
                                        @else
                                            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                                <strong>Follow-up already submitted.</strong> Additional document uploads are blocked. Authorized shelter staff will review the application.
                                            </div>
                                        @endif
                                    </div>
                                @elseif($application->document_verification_status?->value === 'ManualReview')
                                    <div class="note-section"><strong>Manual review:</strong> Automatic OCR was unavailable. Authorized shelter staff will review your private document.</div>
                                @endif

                                @if ($status === 'Waitlisted')
                                    <div class="note-section">
                                        <strong>Waitlisted:</strong> Your application remains active and is ordered by submission time. Staff will contact you if you are promoted.
                                    </div>
                                @elseif ($status === 'PrimaryCandidate')
                                    <div class="note-section">
                                        <strong>Promoted:</strong> You are now the primary candidate. Staff will contact you to schedule an interview.
                                    </div>
                                @endif

                                <div class="detail-row">
                                    <span class="label">Pet Requested</span>
                                    <span class="value">
                                        {{ $application->pet ? "{$application->pet->name} ({$application->pet->species_display}, {$application->pet->breed}, {$application->pet->age_display})" : '—' }}
                                    </span>
                                </div>

                                <div class="detail-row">
                                    <span class="label">Last Updated</span>
                                    <span class="value">{{ \App\Support\ManilaTime::format($application->last_updated, 'F j, Y g:i A') }}</span>
                                </div>

                                @if ($application->interview_date)
                                    <div class="detail-row">
                                        <span class="label">Interview Schedule</span>
                                        <span class="value">
                                            {{ \App\Support\ManilaTime::format($application->interview_date, 'F j, Y g:i A') }}
                                            @if ($application->conducted_by)
                                                with {{ $application->conducted_by }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="progress-section">
                                <h3>Application Progress</h3>
                                <div class="progress-flow">
                                    <!-- SUBMITTED -->
                                    <span class="step submitted-step border border-status-success-text bg-status-success-bg text-status-success-text">
                                        Submitted
                                    </span>

                                    <span class="arrow">&rarr;</span>

                                    <!-- Pending / Queue  -->
                                    <span class="step pending-step
                                        {{ $progressStage === 0
                                            ? 'border border-status-processing-text bg-status-processing-bg text-status-processing-text'
                                            : ($progressStage > 0
                                                ? 'border border-status-processing-text bg-status-processing-bg text-status-processing-text'
                                                : '') }}">
                                        Pending / Queue
                                    </span>

                                    <span class="arrow">&rarr;</span>

                                    <!-- Interview Scheduled  -->
                                    <span class="step interview-step 
                                        {{ $progressStage === 1
                                            ? 'border border-status-adopted-text bg-status-adopted-bg text-status-adopted-text'
                                            : ($progressStage > 1
                                                ? 'border border-status-adopted-text bg-status-adopted-bg text-status-adopted-text'
                                                : '') }}">
                                        Interview Scheduled
                                    </span>

                                    <span class="arrow">&rarr;</span>

                                    <!-- Under Review -->
                                    <span class="step review-step
                                        {{ $progressStage === 2
                                            ? 'border border-status-flagged-text bg-status-flagged-bg text-status-flagged-text'
                                            : ($progressStage > 2
                                                ? 'border border-status-flagged-text bg-status-flagged-bg text-status-flagged-text'
                                                : '') }}">
                                        Under Review
                                    </span>

                                    <span class="arrow">&rarr;</span>

                                    <!-- Decision -->
                                    <span class="step decision-step
                                        {{ $unsuccessful
                                            ? 'border border-status-danger-text bg-status-danger-bg text-status-danger-text font-semibold'
                                            : ($status === 'Approved'
                                                ? 'border border-status-success-text bg-status-success-bg text-status-success-text font-semibold'
                                                : ($progressStage === 3
                                                    ? 'border border-status-success-text bg-status-success-bg text-status-success-text'
                                                    : '')) }}">
                                        Decision
                                    </span>

                                </div>
                            </div>

                            @if ($application->interview_notes || $application->decision_remarks)
                                <div class="note-section">
                                    <strong>Note:</strong>
                                    {{ $application->decision_remarks ?: $application->interview_notes }}
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@endsection
