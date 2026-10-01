@extends('layouts.app')

@section('title', auth()->check() && auth()->user()->isStaff() ? 'Settings - PAIRfect Paws Admin' : 'Settings - PAIRfect Paws')

@section('content')

    <div class="nonsticky-header custom-scrollbar">
        <div class="heading-text">
            <h2>Account Settings</h2>
            <p>Manage your profile and password</p>
        </div>

        <div class="content-area-nonsticky">

            @if (session('success'))
                <div class="mb-5 rounded-xl border border-green-300 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">
                    {{ session('success') }}
                </div>
            @endif

            <div class="settings-wrap">

                <form action="{{ route('account.profile.update') }}" method="POST" class="settings-card shadow-card">
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

                        @if (!$user->isStaff())
                            <div class="settings-field">
                                <label for="phone_number">Phone Number 
                                    @if($user->phone_number)
                                        <span class="field-hint" id="phone_hint">(<a href="#" style="color:var(--primary);text-decoration:underline;" onclick="event.preventDefault(); document.getElementById('phone_number').removeAttribute('disabled'); document.getElementById('phone_hint').style.display='none'; document.getElementById('phone_number').focus();">change</a>)</span>
                                    @endif
                                </label>
                                <input id="phone_number" name="phone_number" type="text" value="{{ old('phone_number', $user->phone_number) }}" {{ $user->phone_number ? 'disabled' : '' }}>
                            </div>

                            <div class="settings-field full-width">
                                <label for="address">Address 
                                    @if($user->address)
                                        <span class="field-hint" id="address_hint">(<a href="#" style="color:var(--primary);text-decoration:underline;" onclick="event.preventDefault(); document.getElementById('address').removeAttribute('disabled'); document.getElementById('address_hint').style.display='none'; document.getElementById('address').focus();">change</a>)</span>
                                    @endif
                                </label>
                                <input id="address" name="address" type="text" value="{{ old('address', $user->address) }}" {{ $user->address ? 'disabled' : '' }}>
                            </div>
                        @endif

                    </div>

                    <div class="settings-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save Changes</button>
                    </div>
                </form>

                <form action="{{ route('account.password.update') }}" method="POST" class="settings-card shadow-card">
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
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i>Update Password</button>
                    </div>
                </form>

            </div>

        </div>
    </div>

@endsection
