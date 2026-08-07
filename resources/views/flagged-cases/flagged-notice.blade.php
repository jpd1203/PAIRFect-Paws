@extends('layouts.app')
@section('title', 'Case Flagged')
@section('content')
<div style="max-width:540px;margin:5rem auto;text-align:center">
    <div style="font-size:4rem">🚩</div>
    <h1 style="font-size:1.75rem;font-weight:700;margin:0.5rem 0">Your Case Has Been Flagged</h1>
    <p style="color:var(--muted);margin-bottom:2rem">A member of our team has flagged your post-adoption case for review. A staff member will reach out to you shortly. Please continue to complete any outstanding check-ins.</p>
    <a href="{{ route('monitoring.my-checkins') }}" class="btn btn-primary">Go to My Check-ins</a>
</div>
@endsection
