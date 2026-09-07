@php $user = auth()->user(); @endphp

<div class="user-card" id="profileCardToggle">

    <div class="avatar">
        {{ $user?->avatar_initial ?? '?' }}
    </div>

    <div class="min-w-0">
        <strong class="block truncate">{{ $user?->full_name ?? 'Guest' }}</strong>
        <span class="block truncate">{{ $user?->email ?? '' }}</span>
    </div>
    
    <span class="inline-flex flex-col leading-none text-xs text-[#777]"><i class="fa-solid fa-chevron-up"></i><i class="fa-solid fa-chevron-down"></i></span>    <div class="profile-dropdown" id="profileDropdown">

        <div class="profile-dropdown-header">
            Signed in as<br>
            <strong class="block truncate">{{ $user?->email ?? '' }}</strong>
        </div>

        <a href="{{ route('landing') }}" class="profile-dropdown-item">
            <i class="fa-solid fa-house"></i> Home
        </a>

        <a href="{{ route('account.settings') }}" class="profile-dropdown-item">
            <i class="fa-solid fa-gear"></i> Settings
        </a>

        <form action="{{ route('logout') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="profile-dropdown-item profile-dropdown-item-danger logout-button font-semibold">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
        </form>

    </div>

</div>
