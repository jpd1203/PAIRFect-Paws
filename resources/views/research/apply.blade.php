@extends('layouts.app')
@section('title', 'Research application')
@section('content')
<h1>Research scenario: apply for {{ $pet->name }}</h1>
<p>No real adoption, identity document, or delivery is involved. Participants share this catalog and reservation queue.</p>
@if($match->eligible)
<p>Compatibility: {{ $match->compatibilityScore }}%. @if($pet->availability_status->value === 'Soft-Reserved') This pet is reserved. Eligible applications join the waitlist. @else Applying reserves the pet for the primary candidate. @endif</p>
<form method="POST" action="{{ route('research.apply',$pet) }}">@csrf
<button class="btn btn-primary">{{ $pet->availability_status->value === 'Soft-Reserved' ? 'Join simulated waitlist' : 'Submit simulated adoption application' }}</button></form>
@else
<p>This scenario is ineligible: {{ \App\Services\Matching\MatchPresenter::reason($match->exclusionReason) }}.</p>
@endif
<a href="{{ route('recommendation.results') }}">Back to recommendations</a>
@endsection
