@php $user = auth()->user(); @endphp

<style>
    .profile-dropdown {
        display: none;
        position: absolute;
        bottom: calc(100% + 8px);
        left: 0;
        width: 100%;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 8px;
        box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 50;
        overflow: hidden;
    }
    .profile-dropdown.show {
        display: block;
    }
    .profile-dropdown-header {
        padding: 12px 16px;
        background: #f9fafb;
        border-bottom: 1px solid #eee;
        font-size: 0.85rem;
        color: #666;
    }
    .profile-dropdown-header strong {
        color: #111;
    }
    .profile-dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        color: #333;
        text-decoration: none;
        transition: background 0.2s;
        border: none;
        width: 100%;
        text-align: left;
        background: none;
        font-size: 0.9rem;
        cursor: pointer;
    }
    .profile-dropdown-item:hover {
        background: #f3f4f6;
    }
    .profile-dropdown-item-danger {
        color: #dc2626;
    }
    .profile-dropdown-item-danger:hover {
        background: #fef2f2;
    }
    .user-card {
        position: relative;
    }
</style>

<div class="user-card" @if(auth()->check()) id="profileCardToggle" style="cursor:pointer;" onclick="document.getElementById('profileDropdown').classList.toggle('show'); event.stopPropagation();" @endif>

    <div class="avatar">
        {{ $user?->avatar_initial ?? '?' }}
    </div>

    <div class="user-info min-w-0">
        <strong class="block truncate">{{ ($user?->full_name ?: null) ?? ($user?->isStaff() ? 'Staff' : 'Guest') }}</strong>
        <small class="block truncate">{{ $user?->email ?? '' }}</small>
    </div>

    @if (auth()->check())
        <span class="inline-flex flex-col leading-none text-xs text-[#777] ml-auto">
            <i class="fa-solid fa-chevron-up"></i>
            <i class="fa-solid fa-chevron-down"></i>
        </span>

        <div class="profile-dropdown" id="profileDropdown" onclick="event.stopPropagation();">
            <div class="profile-dropdown-header">
                Signed in as<br>
                <strong class="block truncate">{{ $user->email }}</strong>
            </div>

            <a href="{{ route('landing') }}" class="profile-dropdown-item">
                <i class="fa-solid fa-house"></i> Home
            </a>

            <a href="{{ route('account.settings') }}" class="profile-dropdown-item">
                <i class="fa-solid fa-gear"></i> Settings
            </a>

            <form action="{{ Route::has('admin.logout') && request()->is('admin*') ? route('admin.logout') : route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="profile-dropdown-item profile-dropdown-item-danger logout-button font-semibold">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </button>
            </form>
        </div>
    @else
        <a href="{{ route('login') }}" class="logout-btn ml-auto" title="Sign in" aria-label="Sign in">
            <i class="fa-solid fa-right-to-bracket"></i>
        </a>
    @endif

</div>

<script>
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('profileDropdown');
        const toggle = document.getElementById('profileCardToggle');
        if (dropdown && dropdown.classList.contains('show') && toggle && !toggle.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
</script>