@extends('layouts.app')
@section('title', 'Pet Recommendation - PAIRfect Paws')
@section('notification-bell-in-header', true)
@section('content')
<div class="nonsticky-header custom-scrollbar">
    <div class="main-content-header">
        <div class="heading-text">
            <h2>Pet Recommendation</h2>
            <p>Available pets recommended from your completed personality and household profile.</p>
        </div>
        @include('partials.notification-bell')
    </div>
    <div class="reco-banner my-3">
        Compatibility supports your decision. Shelter staff review every application and make the final adoption decision.
    </div>
    @if (auth()->check() && ! auth()->user()->hasVerifiedEmail())
        <div role="alert" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            You can view your pet matches now. Verify your email address before you can apply to adopt a pet.
            <a href="{{ route('verification.notice') }}" class="font-bold underline">Verify email</a>
        </div>
    @endif
    <a class="btn btn-secondary mb-4" href="{{ route('recommendation.intake') }}">Update My Profile</a>
    <form method="GET" action="{{ route('recommendation.results') }}" class="flex flex-wrap gap-4 mb-5">
        <div class="filter-section m-0">
            <div class="select-wrapper">
                <select name="species" class="rounded border p-2">
                    <option value="">All Species</option>
                    @foreach (['Dog', 'Cat'] as $species)
                        <option @selected(request('species') === $species)>{{ $species }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>

            <div class="select-wrapper">
                <select name="size" class="rounded border p-2">
                    <option value="">All sizes</option>
                    @foreach (array_keys(config('matching.size_levels')) as $size)
                        <option @selected(request('size') === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>
        </div>

        <!-- <div class="filter-section">
            <label>Size
                <div class="select-wrapper">
                    <select name="size" class="rounded border p-2">
                        <option value="">All sizes</option>
                        @foreach (array_keys(config('matching.size_levels')) as $size)
                            <option @selected(request('size') === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
            </label>
        </div> -->
        <button class="btn btn-primary" type="submit">Filter Recommendations</button>
    </form>
    @if ($errors->any())
        <p class="text-red-700">{{ $errors->first() }}</p>
    @endif
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="m-0 text-sm text-text-muted">
            Showing {{ $matches->firstItem() ?? 0 }} to {{ $matches->lastItem() ?? 0 }} of {{ $matches->total() }} eligible pet matches
        </p>
        @if ($matches->hasPages())
            {{ $matches->links() }}
        @endif
    </div>
    <div id="matchResults" class="flex flex-col gap-4">
        @forelse ($matches as $match)
            @include('recommendation._match-card', ['pet' => $match['pet'], 'result' => $match['result'], 'matcher' => $matcher])
        @empty
            <div class="empty-state">
                <p>No fully assessed, available pets currently match your profile.</p>
                <p>Recommendations require three distinct observers, complete care information, and a household safety match.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-5">{{ $matches->links() }}</div>
</div>

@auth
    <!-- Modal Overlay -->
    <div class="modal-overlay" id="petModal">
        <div class="pet-modal" id="petModalContent">
            <!-- Filled in dynamically via fetch() -->
        </div>
    </div>
@endauth
@endsection
