@extends('layouts.app')
@php($title = $section === 'check-ins' ? 'My Check-Ins' : 'My Applications')
@section('title', $title.' - PAIRfect Paws')
@section('notification-bell-in-header', true)
@section('content')
<div class="nonsticky-header custom-scrollbar">
    <div class="main-content-header">
        <div class="heading-text"><h2>{{ $title }}</h2><p>{{ $section === 'check-ins' ? 'View and complete your welfare reports' : 'Track the progress of your adoption applications' }}</p></div>
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
                @if($section !== 'check-ins')
                    <div class="application-details">
                        <div class="detail-row">
                            <span class="label">Document Verification</span>
                            <span class="value">{{ match($p->document_verification_status?->value) {
                                'Verified' => 'Name and address matched by OCR',
                                'ManualReview' => 'Automatic Check Unavailable',
                                'NeedsResubmission' => 'Could Not Verify Document',
                                default => $p->document_verification_status?->value ?? 'Not Submitted',
                            } }}</span>
                        </div>
                        @if($p->document_verification_status?->value === 'Verified')
                            <p class="text-sm">This text match is not proof of identity.</p>
                        @endif
                        @foreach($p->document_verification_reasons ?? [] as $reason)
                            <p class="text-sm">{{ $reason }}</p>
                        @endforeach
                    </div>
                @endif
                @if($p->status === \App\Enums\ApplicationStatus::Waitlisted)
                    <div class="application-details">
                        <p>{{ $positions->get($p->id) ? 'Current waitlist position: '.$positions->get($p->id).'.' : 'Your profile or the pet is no longer eligible for promotion.' }}</p>
                    </div>
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
                    @if($p->status === \App\Enums\ApplicationStatus::Approved && $section !== 'check-ins')
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
                <i class="fa-solid fa-file" aria-hidden="true"></i><h3>{{ $section === 'check-ins' ? 'No check-ins yet' : 'No applications yet' }}</h3>
                <a href="{{ route('recommendation.intake') }}">Find a pet match</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
