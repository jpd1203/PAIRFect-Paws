@extends('layouts.app')
@section('title', 'Research application')
@section('content')
<h1>Research scenario: apply for {{ $pet->name }}</h1>
<p>No real adoption, identity document, address, or delivery is involved. Other participants can select this pet too.</p>
@if($match->eligible)
<p>Compatibility: {{ $match->compatibilityScore }}%. Your simulated application will be approved and the staff handover step completed automatically.</p>
<form method="POST" action="{{ route('research.apply',$pet) }}">@csrf
<button class="btn btn-primary">Submit simulated adoption application</button></form>
@else
<p>This scenario is ineligible: {{ $match->exclusionReason }}.</p>
@endif
<a href="{{ route('recommendation.results') }}">Back to recommendations</a>
@endsection
