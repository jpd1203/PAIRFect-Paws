@extends('layouts.app')
@section('title', 'Create Account')
@section('meta_description', 'Register for a PAIRfect Paws adopter account')

@section('content')
<div style="max-width:480px;margin:3rem auto">
    <div class="card">
        <div style="text-align:center;margin-bottom:1.75rem">
            <div style="font-size:2.5rem;margin-bottom:0.5rem">🐾</div>
            <h1 style="font-size:1.5rem;font-weight:700">Create an account</h1>
            <p style="color:var(--muted);font-size:0.9rem;margin-top:0.25rem">Start your adoption journey today</p>
        </div>

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="first_name">First name</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus>
                    @error('first_name') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="last_name">Last name</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required>
                    @error('last_name') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label for="password">Password <span style="color:var(--muted);font-weight:400">(min. 8 characters)</span></label>
                <input id="password" type="password" name="password" required>
                @error('password') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Create account</button>
        </form>

        <p style="text-align:center;margin-top:1.25rem;font-size:0.875rem;color:var(--muted)">
            Already have an account? <a href="{{ route('login') }}" style="color:var(--primary);font-weight:600">Sign in</a>
        </p>
    </div>
</div>
@endsection
