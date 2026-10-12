@extends('admin.layouts.app')
@section('title', 'Identity Verification | PAIRfect Paws')
@section('content')
    
    @php
        $handover = $application->handover()->first();
        $handoverId = request('handover') ?: ($handover?->id);
        $isFromHandover = request('from') === 'handover'
            || request()->filled('handover')
            || (url()->previous() && str_contains(url()->previous(), '/admin/handover'));
        $handoverUrl = $handoverId ? route('admin.handover.show', $handoverId) : url()->previous();
    @endphp

    @if($isFromHandover)
        <div class="flex items-center gap-2 text-sm text-[#777] mb-2 font-medium">
            <a href="{{ $handoverUrl }}" class="hover:text-primary transition no-underline text-[#777]">            
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Handover Details</a>
        </div>
    @endif
    <div class="heading-text mb-5">
        <h2>Identity Verification — Application #{{ $application->id }}</h2>
        <p class="pb-3">{{ $application->first_name }} {{ $application->last_name }} · {{ $application->pet?->name }}</p>
        @if(!$isFromHandover)
            <a class="btn btn-secondary" href="{{ route('admin.applications.index', ['highlight' => $application->id]) }}"><i class="fa-solid fa-arrow-left"></i><span>Back to Application</span></a>
        @endif
        <a class="btn btn-secondary" href="{{ route('admin.applications.document-verification', array_filter(['application' => $application, 'from' => $isFromHandover ? 'handover' : null, 'handover' => $isFromHandover ? $handoverId : null])) }}"> <i class="fa-solid fa-eye"></i><span>Review Private Document</span></a>
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
