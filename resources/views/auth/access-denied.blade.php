@extends('layouts.app')
@section('title', 'Access Denied')

@section('content')
<div style="max-width:480px;margin:5rem auto;text-align:center">
    <div style="font-size:4rem;margin-bottom:1rem">🔒</div>
    <h1 style="font-size:1.75rem;font-weight:700;margin-bottom:0.5rem">Access Denied</h1>
    <p style="color:var(--muted);margin-bottom:2rem">You don't have permission to view this page. Please contact an administrator if you believe this is a mistake.</p>
    <a href="{{ route('pets.index') }}" class="btn btn-primary">Return to Home</a>
</div>
@endsection
