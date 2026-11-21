@extends('layouts.app')
@section('title','My research scenarios')
@section('content')
<h1>My research scenarios</h1>
<p>Fictional placements only. Check-ins are accelerated; answers do not send shelter alerts.</p>
<a href="{{ route('recommendation.intake') }}">Find a pet match</a>
@if($errors->any()) <p role="alert">{{ $errors->first() }}</p> @endif
@forelse($placements as $p)
<section class="p-4 border rounded my-3"><h2>{{ $p->name }} — simulated adoption approved</h2>
<p>Simulated staff handover complete. Match: {{ $p->compatibility_score }}%.</p>
@if(!$p->received_at)
<form method="POST" action="{{ route('research.receive',$p->id) }}">@csrf
<button class="btn btn-primary">Confirm simulated receipt</button></form>
@else
<p>Simulated receipt confirmed.</p>
@foreach(\App\Support\ResearchMode::MILESTONES as $milestone)
@if($submitted->get($p->id,collect())->contains('milestone',$milestone))
<p>{{ $milestone }} simulated report filed.</p>
@else
<form method="POST" action="{{ route('research.checkin',$p->id) }}">@csrf
<h3>Practice {{ $milestone }} welfare check-in</h3>
<input type="hidden" name="milestone" value="{{ $milestone }}">
<label>Appetite <select required name="appetite"><option value="">Select</option><option value="normal">Normal</option><option value="reduced">Reduced</option></select></label>
<label>Adjustment <select required name="adjustment"><option value="">Select</option><option value="settling">Settling well</option><option value="struggling">Struggling</option></select></label>
<button class="btn btn-primary">Submit simulated report</button></form>
@break
@endif
@endforeach
@endif
</section>
@empty
<p>No simulated applications yet. Complete your matching profile and select Apply on a recommended pet.</p>
@endforelse
@endsection
