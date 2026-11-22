@extends('layouts.app')
@section('title', 'Handover Status')
@section('notification-bell-in-header', true)
@section('content')
<div class="nonsticky-header custom-scrollbar">
    <div class="main-content-header">
        <div class="heading-text"><h2>Handover Status</h2></div>
        @include('partials.notification-bell')
    </div>
    <div class="content-area">
        <div class="max-w-[860px] mx-auto space-y-5 py-3">
            <a class="btn btn-secondary btn-sm" href="{{ route('research.index') }}">Back to My Applications</a>
            <section class="flex flex-wrap items-center gap-4 rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
                <img src="{{ $placement->pet->image_url }}" alt="" class="h-20 w-20 shrink-0 rounded-xl object-cover">
                <div>
                    <h3 class="text-xl font-bold">{{ $placement->pet->name }}</h3>
                    <ol class="flex flex-wrap gap-3 text-sm mt-2" aria-label="Handover progress">
                        <li>Application approved</li><li>Handover ready</li><li>{{ $placement->received_at ? 'Receipt confirmed' : 'Awaiting confirmation' }}</li>
                    </ol>
                </div>
            </section>
            <section class="rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
                @if($placement->received_at)
                    <h3 class="font-semibold">Confirmation recorded</h3>
                    <a class="btn btn-primary mt-3" href="{{ route('research.index') }}#checkins-{{ $placement->id }}">View Check-Ins</a>
                @else
                    <h3 class="font-semibold">Confirm Pet Receipt</h3>
                    <p class="my-3">Record your confirmation to unlock the post-adoption check-ins.</p>
                    <form method="POST" action="{{ route('research.receive', $placement) }}">
                        @csrf
                        <button class="btn btn-primary">Confirm Receipt</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
