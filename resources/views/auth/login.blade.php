@extends('layouts.app')
@section('title', 'Log In')
@section('meta_description', 'Log in to your PAIRfect Paws account')

@section('content')
<div style="max-width:420px;margin:4rem auto">
    <div class="card">
        <div style="text-align:center;margin-bottom:1.75rem">
            <div style="font-size:2.5rem;margin-bottom:0.5rem">🐾</div>
            <h1 style="font-size:1.5rem;font-weight:700">Welcome back</h1>
            <p style="color:var(--muted);font-size:0.9rem;margin-top:0.25rem">Sign in to your account</p>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
                @error('password') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:0.5rem">
                <input type="checkbox" name="remember" id="remember" style="width:auto">
                <label for="remember" style="margin:0;font-weight:400;cursor:pointer">Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Sign in</button>
        </form>

        <p style="text-align:center;margin-top:1.25rem;font-size:0.875rem;color:var(--muted)">
            Don't have an account? <a href="{{ route('register') }}" style="color:var(--primary);font-weight:600">Register</a>
        </p>
    </div>
</div>
@endsection
