@php $user = auth()->user(); @endphp

<div class="user-card" id="profileCardToggle">

    <div class="avatar">
        {{ $user?->avatar_initial ?? '?' }}
    </div>

    <div>
        <strong>{{ $user?->full_name ?? 'Guest' }}</strong>
        <br>
        {{ $user?->email ?? '' }}
    </div>

    <i class="fa-solid fa-chevron-up profile-caret" id="profileCaret"></i>

    <div class="profile-dropdown" id="profileDropdown">

        <div class="profile-dropdown-header">
            Signed in as<br>
            <strong>{{ $user?->email ?? '' }}</strong>
        </div>

        <a href="{{ route('landing') }}" class="profile-dropdown-item">
            <i class="fa-solid fa-house"></i> Home
        </a>

        <a href="{{ route('account.settings') }}" class="profile-dropdown-item">
            <i class="fa-solid fa-gear"></i> Settings
        </a>

        <form action="{{ route('logout') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="profile-dropdown-item profile-dropdown-item-danger logout-button">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
        </form>

    </div>

</div>
