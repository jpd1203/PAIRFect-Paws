@extends('admin.layouts.app')
@section('title', 'Identity Verification | PAIRfect Paws')
@section('content')
    <div class="heading-text mb-5">
        <h2>Identity Verification — Application #{{ $application->id }}</h2>
        <p>{{ $application->first_name }} {{ $application->last_name }} · {{ $application->pet?->name }}</p>
        <a class="btn btn-secondary" href="{{ route('admin.applications.index', ['highlight' => $application->id]) }}">Back to Applications</a>
        <a class="btn btn-secondary" href="{{ route('admin.applications.document-verification', $application) }}">Review Private Document</a>
    </div>
    @if($errors->any())<div class="modal-note caution mb-4" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="modal-note mb-4">{{ session('success') }}</div>@endif
    @include('admin.application._identity-form')
    <section class="mt-5 rounded-card border bg-white p-5">
        <h3 class="font-bold">Verification history</h3>
        @forelse($events as $event)
            <div class="border-b py-3">
                <p>{{ $event->stage }} · {{ $event->status }} · {{ $event->verification_method }}</p>
                <p class="text-sm">{{ $event->verifier?->full_name ?? 'Former staff' }} · {{ \App\Support\ManilaTime::format($event->verified_at, 'M j, Y g:i A') }} (Asia/Manila)</p>
                @if($event->discrepancy_note)<p class="text-sm whitespace-pre-line">{{ $event->discrepancy_note }}</p>@endif
            </div>
        @empty<p>No staff-assisted identity verification has been recorded.</p>@endforelse
    </section>
@endsection
