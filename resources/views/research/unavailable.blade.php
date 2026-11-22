@extends('layouts.app')
@section('title', 'Application unavailable')
@section('content')
    <div class="nonsticky-header custom-scrollbar">
        <div class="main-content-header">
            <div class="heading-text"><h2>Apply to Adopt {{ $pet->name }}</h2></div>
        </div>
        <div class="content-area">
            <div class="form-section" role="alert">
                <p>{{ \App\Services\Matching\MatchPresenter::reason($match->exclusionReason) }}</p>
                <a class="btn btn-secondary" href="{{ route('recommendation.results') }}">Back to Recommendations</a>
            </div>
        </div>
    </div>
@endsection
