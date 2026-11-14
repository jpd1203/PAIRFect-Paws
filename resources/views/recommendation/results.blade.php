@extends('layouts.app')
@section('title', 'Pet Recommendation - PAIRfect Paws')
@section('notification-bell-in-header', true)
@section('content')
<div class="nonsticky-header custom-scrollbar">
    <div class="main-content-header">
        <div class="heading-text">
            <h2>Pet Recommendation</h2>
            <p>Explore compatible, adoptable pets matching your personality and household profile.</p>
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
    <form id="recommendationFilters" method="GET" action="{{ route('recommendation.results') }}" class="flex flex-wrap gap-4 mb-5">
        @if(request()->boolean('include_ineligible'))
            <input type="hidden" name="include_ineligible" value="1">
        @endif
        <div class="filter-section m-0">
            <div class="select-wrapper">
                <label for="recommendationSpecies" class="sr-only">Species</label>
                <select id="recommendationSpecies" name="species" class="rounded border p-2">
                    <option value="">All Species</option>
                    @foreach (['Dog', 'Cat'] as $species)
                        <option value="{{ $species }}" @selected($selectedSpecies === $species)>{{ $species }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>

            <div class="select-wrapper">
                <label for="recommendationSize" class="sr-only">Adult weight-based size</label>
                <select id="recommendationSize" name="size" class="rounded border p-2" aria-describedby="recommendationSizeHelp" @disabled($selectedSpecies === '')>
                    <option value="">All Sizes</option>
                    @foreach ($sizeOptionsBySpecies[$selectedSpecies] ?? [] as $option)
                        <option value="{{ $option['value'] }}" @selected($selectedSize === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>
        </div>
        <p id="recommendationSizeHelp" class="sr-only" aria-live="polite">{{ $selectedSpecies === '' ? 'Select Dog or Cat before choosing a size.' : $selectedSpecies.' adult weight ranges. All Sizes includes other size and classification states when ineligible pets are requested.' }}</p>
        <button class="btn btn-primary flex items-center gap-2" type="submit">
            <i class="fa-solid fa-filter text-xs"></i>
            <span>Apply Filters</span>
        </button>
    </form>
    @if ($errors->any())
        <p class="text-red-700">{{ $errors->first() }}</p>
    @endif
    <div id="matchResults" class="flex flex-col gap-4">
        @forelse ($matches as $match)
            @include('recommendation._match-card', ['pet' => $match['pet'], 'result' => $match['result'], 'matcher' => $matcher])
        @empty
            <div class="empty-state">
                <p>No pets found for this page or the selected filters.</p>
                <a class="btn btn-secondary" href="{{ route('recommendation.results') }}">Show all pets</a>
            </div>
        @endforelse
    </div>
    @include('recommendation._pagination')
</div>

    <!-- Modal Overlay -->
    <div class="modal-overlay" id="petModal" role="dialog" aria-modal="true" aria-label="Pet profile">
        <div class="pet-modal" id="petModalContent">
            <!-- Filled in dynamically via fetch() -->
        </div>
    </div>
@endsection

@push('scripts')
    <script id="recommendationSizeOptions" type="application/json">@json($sizeOptionsBySpecies)</script>
    <script src="{{ asset('js/recommendation-size-filters.js') }}" defer></script>
@endpush
