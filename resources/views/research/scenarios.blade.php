@extends('layouts.app')
@php($title = match ($section) {'handover' => 'Handover Status', 'check-ins' => 'My Check-Ins', default => 'My Applications'})
@section('title', $title.' - PAIRfect Paws')
@section('notification-bell-in-header', true)
@section('content')
<div class="nonsticky-header custom-scrollbar">
    <div class="main-content-header">
        <div class="heading-text"><h2>{{ $title }}</h2><p>{{ match ($section) {'handover' => 'View your handover progress', 'check-ins' => 'View and complete your welfare reports', default => 'Track the progress of your adoption applications'} }}</p></div>
        @include('partials.notification-bell')
    </div>
    <div class="content-area-nonsticky">
        @if(session('success')) <p role="status">{{ session('success') }}</p> @endif
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800" role="alert">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @forelse($placements as $p)
            <article class="application-card shadow-card">
                <div class="card-header">
                    <div><h2>{{ $p->pet->name }}</h2><p class="text-sm text-[#777]">Application #{{ $p->id }}</p></div>
                    <span class="badge {{ $p->status_badge_class }}">{{ $p->status->value === 'Closed' ? 'Closed' : $p->status_display }}</span>
                </div>
                <div class="application-details">
                    <div class="detail-row">
                        <span class="label">Document Verification</span>
                        <span class="value font-semibold">{{ match($p->document_verification_status?->value) {
                            'Verified' => 'Name and address matched by OCR',
                            'NeedsResubmission' => 'Replacement Required for Verification',
                            'ManualReview' => 'Automatic Check Unavailable',
                            'LegacyReview' => 'Manual Document Review',
                            'Pending' => 'Pending Document Check',
                            default => 'Not Submitted',
                        } }}</span>
                    </div>
                    @if($p->document_verification_status?->value === 'Verified')
                        <p class="text-sm">This text match is not proof of identity.</p>
                    @endif
                    @foreach($p->document_verification_reasons ?? [] as $reason)
                        <p class="text-sm">{{ $reason }}</p>
                    @endforeach
                    @if($p->acceptsResearchReplacement())
                        <form action="{{ route('research.document', $p) }}" method="POST" enctype="multipart/form-data" class="mt-3 border-t pt-3">
                            @csrf
                            <label class="block" for="replacement_document_{{ $p->id }}">Upload a replacement ID for verification</label>
                            <input id="replacement_document_{{ $p->id }}" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                            @include('research._document-consent', ['consentId' => $p->id])
                            <button type="submit" class="btn btn-secondary">Submit Replacement</button>
                        </form>
                    @endif
                    @if($p->status === \App\Enums\ApplicationStatus::Waitlisted)
                        <p>{{ $positions->get($p->id) ? 'Current waitlist position: '.$positions->get($p->id).'.' : 'Your profile or the pet is no longer eligible for promotion.' }}</p>
                    @endif
                </div>
                @if($p->status === \App\Enums\ApplicationStatus::PrimaryCandidate && $p->document_verification_status
                    && $p->document_verification_status->value !== 'Verified')
                    <p class="mt-3 text-sm">You can continue your application without waiting for document verification.</p>
                @endif
                <div class="flex flex-wrap gap-3 mt-4">
                    @if($p->status === \App\Enums\ApplicationStatus::PrimaryCandidate)
                        <form method="POST" action="{{ route('research.continue', $p) }}">@csrf
                            <button class="btn btn-primary">Continue Application</button>
                        </form>
                    @endif
                    @if(in_array($p->status, [\App\Enums\ApplicationStatus::PrimaryCandidate, \App\Enums\ApplicationStatus::Waitlisted], true))
                        <form method="POST" action="{{ route('research.withdraw', $p) }}">@csrf
                            <button class="btn btn-secondary">Withdraw Application</button>
                        </form>
                    @endif
                    @if($p->status === \App\Enums\ApplicationStatus::Approved && $p->released_at)
                        <a class="btn btn-secondary" href="{{ route('research.handover', $p) }}">Handover Status</a>
                    @endif
                </div>
                @if($p->received_at)
                    <section id="checkins-{{ $p->id }}" class="mt-4 border-t pt-3" aria-label="Post-Adoption Check-Ins">
                        <h3 class="font-semibold">Post-Adoption Check-Ins</h3>
                        <ol class="flex flex-wrap gap-4 my-3">
                            @foreach(\App\Support\ResearchMode::MILESTONES as $milestone)
                                <li>{{ $milestone }}: {{ $submitted->get($p->id, collect())->contains('milestone', $milestone) ? 'Report filed' : 'Not yet filed' }}</li>
                            @endforeach
                        </ol>
                        @if($submitted->get($p->id, collect())->count() < count(\App\Support\ResearchMode::MILESTONES))
                            <a class="btn btn-primary" href="{{ route('research.report', $p) }}">Start Check-In</a>
                        @endif
                    </section>
                @endif
            </article>
        @empty
            <div class="empty-state">
                <i class="fa-solid fa-file" aria-hidden="true"></i><h3>{{ match ($section) {'handover' => 'No handovers yet', 'check-ins' => 'No check-ins yet', default => 'No applications yet'} }}</h3>
                <a href="{{ route('recommendation.intake') }}">Find a pet match</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
