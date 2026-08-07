@extends('layouts.app')
@section('title', 'Check-in Overdue')
@section('content')
<div style="max-width:540px;margin:5rem auto;text-align:center">
    <div style="font-size:4rem">⏰</div>
    <h1 style="font-size:1.75rem;font-weight:700;margin:0.5rem 0">Your Check-in is Overdue</h1>
    <p style="color:var(--muted);margin-bottom:2rem">Your scheduled post-adoption welfare report is past due. Please complete it as soon as possible to keep your case in good standing.</p>
    <a href="{{ route('monitoring.my-checkins') }}" class="btn btn-primary">Go to My Check-ins</a>
</div>
@endsection
