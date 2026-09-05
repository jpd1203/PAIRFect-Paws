@extends('layouts.app')

@section('title', 'Settings - PAIRfect Paws')

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Account Settings</h2>
            <p>Manage your profile and password</p>
        </div>
    </div>

    <div class="content-area">

        @if (session('success'))
            <div class="mb-5 rounded-xl border border-green-300 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        <div class="settings-wrap">

            <form action="{{ route('account.profile.update') }}" method="POST" class="settings-card">
                @csrf
                @method('PATCH')

                <h3>Profile Information</h3>

                <div class="settings-grid">

                    <div class="settings-field">
                        <label for="full_name">Full Name</label>
                        <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $user->full_name) }}">
                    </div>

                    <div class="settings-field">
                        <label>Email <span class="field-hint">(contact support to change)</span></label>
                        <input type="email" value="{{ $user->email }}" disabled>
                    </div>

                    <div class="settings-field">
                        <label for="phone_number">Phone Number</label>
                        <input id="phone_number" name="phone_number" type="text" value="{{ old('phone_number', $user->phone_number) }}">
                    </div>

                    <div class="settings-field full-width">
                        <label for="address">Address</label>
                        <input id="address" name="address" type="text" value="{{ old('address', $user->address) }}">
                    </div>

                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

            <form action="{{ route('account.password.update') }}" method="POST" class="settings-card">
                @csrf
                @method('PATCH')

                <h3>Change Password</h3>

                @if ($errors->updatePassword->any())
                    <div class="mb-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        <p class="font-semibold">Your password was not changed.</p>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($errors->updatePassword->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="settings-grid">

                    <div class="settings-field">
                        <label for="current_password">Current Password</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    </div>

                    <div class="settings-field"></div>

                    <div class="settings-field">
                        <label for="password">New Password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10">
                        <small class="field-hint">Use at least 10 characters and choose a password different from your current one.</small>
                    </div>

                    <div class="settings-field">
                        <label for="password_confirmation">Confirm New Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="10">
                    </div>

                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>

        </div>

    </div>

@endsection
