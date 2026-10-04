@extends('layouts.app')

@section('title', 'My Applications - PAIRfect Paws')

@section('notification-bell-in-header', true)
@section('content')

    <div class="nonsticky-header custom-scrollbar">
        <div class="main-content-header">
            <div class="heading-text">
                <h2>My Applications</h2>
                <p>Track the progress of all your adoption applications</p>
            </div>

            @include('partials.notification-bell')
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
                                    <div class="my-4 rounded-xl border border-red-200 bg-red-50/60 p-5 text-gray-800 shadow-xs">
                                        <div class="flex items-start gap-2.5 text-red-900 font-bold text-sm">
                                            <i class="fa-solid fa-circle-exclamation text-red-500 mt-0.5 text-base shrink-0"></i>
                                            <span>Document or application details require correction:</span>
                                        </div>

                                        <ul class="mt-2.5 ml-7 list-disc space-y-1 text-xs sm:text-sm text-red-950 font-medium">
                                            @foreach($application->document_verification_reasons ?? [] as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        </ul>

                                        <p class="mt-3 text-xs text-gray-700 leading-relaxed">
                                            You may submit one follow-up document. If the name or address entered on the application is incorrect, please contact shelter staff instead.
                                        </p>

                                        @if($application->canUploadReplacementDocument())
                                            <form method="POST" action="{{ route('applications.document.replace', $application) }}" enctype="multipart/form-data" class="mt-4 pt-4 border-t border-red-200/80">
                                                @csrf
                                                <label class="block text-xs font-bold text-gray-800 mb-2" for="replacement_document_{{ $application->id }}">
                                                    Upload a matching government ID or proof of address
                                                </label>
                                                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <input id="replacement_document_{{ $application->id }}" 
                                                               type="file" 
                                                               name="document" 
                                                               accept=".pdf,.jpg,.jpeg,.png" 
                                                               required
                                                               class="block w-full text-xs text-gray-700 rounded-lg border border-gray-300 bg-white p-1.5 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer focus:outline-none focus:ring-1 focus:ring-maroon-600">
                                                    </div>
                                                    <button type="submit" class="shrink-0 inline-flex items-center justify-center gap-2 rounded-lg bg-maroon-600 hover:bg-maroon-700 text-white font-bold px-5 py-2.5 text-xs sm:text-sm shadow-sm transition duration-150 cursor-pointer">
                                                        <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
                                                        <span>Submit Follow-up Document</span>
                                                    </button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs sm:text-sm text-amber-900 flex items-center gap-2">
                                                <i class="fa-solid fa-circle-info text-amber-600 shrink-0"></i>
                                                <span><strong>Follow-up already submitted.</strong> Additional document uploads are blocked. Authorized shelter staff will review the application.</span>
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
                                @if ($status === 'InterviewScheduled' && $application->interview_date)
                                    <div id="reschedule-{{ $application->id }}" class="note-section">
                                        @if ($application->reschedule_status === 'pending')
                                            <strong>Reschedule requested:</strong> Staff is reviewing your preferred times. Your current interview schedule remains in effect until they confirm a change.
                                        @else
                                            @if ($application->reschedule_status === 'approved')
                                                <p>Your interview was rescheduled. The confirmed time is shown above.</p>
                                            @elseif ($application->reschedule_status === 'declined')
                                                <p>Staff could not approve your last reschedule request. The current interview time remains in effect.</p>
                                            @endif
                                            @if ($application->interview_date->isFuture())
                                                <details class="group mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" @if(request('reschedule') == $application->id) open @endif>
                                                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-2.5 font-semibold text-gray-900 hover:bg-gray-50">
                                                        <div>
                                                            <p class="text-m font-bold">Request Reschedule</p>
                                                            <p class="text-sm font-normal text-gray-500">Suggest up to three preferred schedules.</p>
                                                        </div>

                                                        <span class="text-gray-400 transition group-open:rotate-180">
                                                            ▾
                                                        </span>
                                                    </summary>

                                                    <div class="border-t border-gray-200 bg-gray-50/40 px-4 py-3">
                                                        <p class="mb-3 text-sm leading-5 text-gray-600">
                                                            Option 1 is required. Staff will review your preferred dates and confirm the final schedule.
                                                        </p>

                                                        <form method="POST" action="{{ route('applications.reschedule.request', $application) }}" class="space-y-3">
                                                            @csrf
                                                            <!-- Reason -->
                                                            <div>
                                                                <label for="reschedule_reason_{{ $application->id }}" class="mb-1 block text-sm font-semibold text-gray-700">
                                                                    Reason
                                                                    <span class="font-normal text-gray-400">(optional)</span>
                                                                </label>

                                                                <textarea id="reschedule_reason_{{ $application->id }}" name="reason" maxlength="1000" rows="2" placeholder="Reason for rescheduling..."
                                                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-maroon-600 focus:outline-none focus:ring-1 focus:ring-maroon-600"
                                                                >{{ old('reason') }}</textarea>
                                                            </div>

                                                            <!-- Schedule Options -->
                                                            <div class="space-y-2">
                                                                @for ($option = 0; $option < 3; $option++)
                                                                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                                                                        <div class="mb-2 flex items-center justify-between">
                                                                            <span class="text-sm font-semibold text-gray-900">Option {{ $option + 1 }}</span>
                                                                            @if ($option === 0)
                                                                                <span class="text-xs font-semibold text-primary">Required</span>
                                                                            @else
                                                                                <span class="text-xs text-gray-400">Optional</span>
                                                                            @endif

                                                                        </div>

                                                                        <!-- Date -->
                                                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                                            <div>
                                                                                <label for="reschedule_date_{{ $application->id }}_{{ $option }}" class="mb-1 block text-[11px] font-medium text-gray-600">
                                                                                    Date
                                                                                </label>

                                                                                <input id="reschedule_date_{{ $application->id }}_{{ $option }}" type="date" name="options[{{ $option }}][date]" value="{{ old('options.' . $option . '.date') }}"
                                                                                    min="{{ \App\Support\ManilaTime::now()->format('Y-m-d') }}"
                                                                                    class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm focus:border-maroon-600 focus:outline-none focus:ring-1 focus:ring-maroon-600"
                                                                                    @if ($option === 0) required @endif
                                                                                >
                                                                            </div>

                                                                            <!-- Time -->
                                                                            <div>
                                                                                <label for="reschedule_time_{{ $application->id }}_{{ $option }}" class="mb-1 block text-[11px] font-medium text-gray-600">
                                                                                    Time
                                                                                </label>

                                                                                <input id="reschedule_time_{{ $application->id }}_{{ $option }}" type="time" name="options[{{ $option }}][time]"
                                                                                    value="{{ old('options.' . $option . '.time') }}"
                                                                                    class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm focus:border-maroon-600 focus:outline-none focus:ring-1 focus:ring-maroon-600"
                                                                                    @if ($option === 0) required @endif
                                                                                >
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endfor
                                                            </div>

                                                            <!-- Submit -->
                                                            <div class="flex justify-end pt-1">
                                                                <button type="submit" class="rounded-lg bg-maroon-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-maroon-700 focus:outline-none focus:ring-2 focus:ring-maroon-500 focus:ring-offset-1">
                                                                    Send Request
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </details>
                                            @endif
                                        @endif
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

                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@endsection
