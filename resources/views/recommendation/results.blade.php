@extends('layouts.app')
@section('title', 'Pet Recommendation - PAIRfect Paws')
@section('content')
<div class="nonsticky-header custom-scrollbar">
    <div class="heading-text">
        <h2>Pet Recommendation</h2>
        <p>Available pets recommended from your completed personality and household profile.</p>
    </div>
    <div class="reco-banner my-3">
        Compatibility supports your decision. Shelter staff review every application and make the final adoption decision.
    </div>
    <a class="btn btn-secondary mb-4" href="{{ route('recommendation.intake') }}">Update My Profile</a>
    <form method="GET" action="{{ route('recommendation.results') }}" class="flex flex-wrap gap-4 mb-5">
        <label>Species
            <select name="species" class="rounded border p-2">
                <option value="">All cats and dogs</option>
                @foreach (['Dog', 'Cat'] as $species)
                    <option @selected(request('species') === $species)>{{ $species }}</option>
                @endforeach
            </select>
        </label>
        <label>Size
            <select name="size" class="rounded border p-2">
                <option value="">All sizes</option>
                @foreach (array_keys(config('matching.size_levels')) as $size)
                    <option @selected(request('size') === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Filter Recommendations</button>
    </form>
    @if ($errors->any())
        <p class="text-red-700">{{ $errors->first() }}</p>
    @endif
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
</div>
@endsection
