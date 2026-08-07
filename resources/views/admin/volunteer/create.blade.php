@extends('layouts.app')
@section('title', 'Create Staff Account')

@section('content')
<div style="max-width:600px;margin:0 auto">
    <div class="page-header">
        <h1>Create Staff Account</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.volunteers.store') }}">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required>
                    @error('first_name') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required>
                    @error('last_name') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input id="password" type="password" name="password" required>
                    @error('password') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password *</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="role">Role *</label>
                    <select id="role" name="role" required>
                        <option value="Volunteer" {{ old('role') === 'Volunteer' ? 'selected' : '' }}>Volunteer</option>
                        <option value="Administrator" {{ old('role') === 'Administrator' ? 'selected' : '' }}>Administrator</option>
                    </select>
                    @error('role') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="branch_id">Branch</label>
                    <select id="branch_id" name="branch_id">
                        <option value="">-- None / System-wide --</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="width:100%">Create Account</button>
            </div>
        </form>
    </div>
</div>
@endsection
