@php
    $isActive = fn (string ...$routeNames) => collect($routeNames)->contains(fn ($r) => request()->routeIs($r));
    $staff = auth()->user();
    $unresolvedMonitoringFlags = \App\Models\PostAdoptionLog::query()
        ->where('is_flagged', true)
        ->whereNull('resolved_at')
        ->count();
@endphp

<div class="sidebar" id="appSidebar">

    <div class="logo">
        <h3>PAIRfect Paws</h3>
    </div>

    <div class="sidebar-nav">

        <div class="menu-section">
            <a href="{{ route('admin.dashboard') }}" class="menu-item {{ $isActive('admin.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-table-columns fa-lg"></i> Dashboard
            </a>
        </div>

        <div class="menu-title">ADOPTION</div>

        <div class="menu-section">

            <a href="{{ route('admin.animals.index') }}" class="menu-item {{ $isActive('admin.animals.index') ? 'active' : '' }}">
                <i class="fa-solid fa-paw fa-lg"></i> Animal Records
            </a>

            <a href="{{ route('admin.assessments.record') }}" class="menu-item {{ $isActive('admin.assessments.record', 'admin.assessments.create', 'admin.assessments.store') ? 'active' : '' }}">
                <i class="fa-solid fa-pen-to-square fa-lg"></i> Assessment Record
            </a>

            <a href="{{ route('admin.applications.index') }}" class="menu-item {{ $isActive('admin.applications.index') ? 'active' : '' }}">
                <i class="fa-solid fa-file fa-lg"></i> Applications
            </a>

            <a href="{{ route('admin.compatibility.index') }}" class="menu-item {{ $isActive('admin.compatibility.index') ? 'active' : '' }}">
                <i class="fa-solid fa-percent fa-lg"></i> Compatibility
            </a>

            <a href="{{ route('admin.adopter-profiles.index') }}" class="menu-item {{ $isActive('admin.adopter-profiles.index') ? 'active' : '' }}">
                <i class="fa-solid fa-circle-user fa-lg"></i> Adoption Profile
            </a>

        </div>

        <div class="menu-title">POST-ADOPTIONS</div>

        <div class="menu-section">

            <a href="{{ route('admin.monitoring.index') }}" class="menu-item {{ $isActive('admin.monitoring.index') ? 'active' : '' }}">
                <i class="fa-solid fa-magnifying-glass fa-lg"></i> Monitoring
            </a>

            <a href="{{ route('admin.monitoring.flagged') }}" class="menu-item {{ $isActive('admin.monitoring.flagged') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation fa-lg"></i> Flagged Cases
                @if ($unresolvedMonitoringFlags > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white">
                        {{ $unresolvedMonitoringFlags > 99 ? '99+' : $unresolvedMonitoringFlags }}
                    </span>
                @endif
            </a>

        </div>

        <div class="menu-title">SYSTEM</div>

        <div class="menu-section">

            <a href="{{ route('admin.volunteers.index') }}" class="menu-item {{ $isActive('admin.volunteers.index') ? 'active' : '' }}">
                <i class="fa-solid fa-users fa-lg"></i> Volunteers
            </a>

            <a href="{{ route('admin.audit-logs.index') }}" class="menu-item {{ $isActive('admin.audit-logs.index') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-list fa-lg"></i> Audit Logs
            </a>

            <a href="{{ route('admin.funds.index') }}" class="menu-item {{ $isActive('admin.funds.index') ? 'active' : '' }}">
                <i class="fa-solid fa-sack-dollar fa-lg"></i> Manage Funds
            </a>

            @if (config('post_adoption.time_travel.enabled'))
                <a href="{{ route('time-travel.index') }}" class="menu-item {{ $isActive('time-travel.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-clock-rotate-left fa-lg"></i> Test Time Travel
                    @if (app(\App\Services\PostAdoptionClock::class)->isActive())
                        <span class="ml-auto rounded bg-amber-500 px-1.5 py-0.5 text-[.65rem] font-bold text-white">ACTIVE</span>
                    @endif
                </a>
            @endif

        </div>

    </div>

    <div class="user-card">
        <div class="avatar">{{ $staff?->avatar_initial ?? '?' }}</div>
        <div class="user-info flex-1">
            <strong>{{ $staff?->full_name ?? 'Staff' }}</strong>
            <small>{{ $staff?->email }}</small>
        </div>
        <form action="{{ route('admin.logout') }}" method="POST">
            @csrf
            <button type="submit" class="logout-btn" title="Log out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
