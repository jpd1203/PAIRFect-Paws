@php
    $isActive = fn (string ...$routeNames) => collect($routeNames)->contains(fn ($r) => request()->routeIs($r));
    $staff = auth()->user();
    
    // Flagged cases count (persistent, not dismissible)
    $unresolvedMonitoringFlags = \App\Models\PostAdoptionLog::query()
        ->afterCompletedHandover()
        ->where('is_flagged', true)
        ->whereNull('resolved_at')
        ->count();

    // Dismissible badges counts
    $pendingApplicationsCount = \App\Models\AdoptionApplication::query()
        ->where('status', 'Pending')
        ->count();

    $pendingHandoverCount = \App\Models\Handover::query()
        ->whereNull('released_at')
        ->count();

    $pendingMonitoringCount = \App\Models\PostAdoptionLog::query()
        ->afterCompletedHandover()
        ->whereNull('submitted_date')
        ->count();
@endphp

<div class="sidebar" id="appSidebar">

    <div class="logo flex items-center justify-center">
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

            <a href="{{ route('admin.applications.index') }}"
               class="menu-item {{ $isActive('admin.applications.index') ? 'active' : '' }}"
               data-sidebar-dismissible="applications"
               data-badge-count="{{ $pendingApplicationsCount }}">
                <i class="fa-solid fa-file fa-lg"></i> Applications
                @if ($pendingApplicationsCount > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white sidebar-dismissible-badge"
                          id="sidebar-badge-applications">
                        {{ $pendingApplicationsCount > 99 ? '99+' : $pendingApplicationsCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('admin.compatibility.index') }}" class="menu-item {{ $isActive('admin.compatibility.index') ? 'active' : '' }}">
                <i class="fa-solid fa-percent fa-lg"></i> Compatibility
            </a>

            <a href="{{ route('admin.adopter-profiles.index') }}" class="menu-item {{ $isActive('admin.adopter-profiles.index') ? 'active' : '' }}">
                <i class="fa-solid fa-circle-user fa-lg"></i> Adoption Profile
            </a>

            <a href="{{ route('admin.handover.index') }}"
               class="menu-item {{ $isActive('admin.handover.index', 'admin.handover.show') ? 'active' : '' }}"
               data-sidebar-dismissible="handover"
               data-badge-count="{{ $pendingHandoverCount }}">
                <i class="fa-solid fa-truck-ramp-box fa-lg"></i> Handover &amp; Release
                @if ($pendingHandoverCount > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white sidebar-dismissible-badge"
                          id="sidebar-badge-handover">
                        {{ $pendingHandoverCount > 99 ? '99+' : $pendingHandoverCount }}
                    </span>
                @endif
            </a>

        </div>

        <div class="menu-title">POST-ADOPTIONS</div>

        <div class="menu-section">

            <a href="{{ route('admin.monitoring.index') }}"
               class="menu-item {{ $isActive('admin.monitoring.index') ? 'active' : '' }}"
               data-sidebar-dismissible="monitoring"
               data-badge-count="{{ $pendingMonitoringCount }}">
                <i class="fa-solid fa-magnifying-glass fa-lg"></i> Monitoring
                @if ($pendingMonitoringCount > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white sidebar-dismissible-badge"
                          id="sidebar-badge-monitoring">
                        {{ $pendingMonitoringCount > 99 ? '99+' : $pendingMonitoringCount }}
                    </span>
                @endif
            </a>

            {{-- Flagged Cases: Count stays permanently (not dismissible on click) --}}
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

            @if($staff?->isAdmin())
                <a href="{{ route('admin.volunteers.index') }}" class="menu-item {{ $isActive('admin.volunteers.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-users fa-lg"></i> Volunteers
                </a>
            @endif

            @if($staff?->isAdmin())
                <a href="{{ route('admin.audit-logs.index') }}" class="menu-item {{ $isActive('admin.audit-logs.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-clipboard-list fa-lg"></i> Audit Logs
                </a>
            @endif

            @if (app(\App\Services\PostAdoptionClock::class)->enabled())
                <a href="{{ route('time-travel.index') }}" class="menu-item {{ $isActive('time-travel.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-clock-rotate-left fa-lg"></i> Test Time Travel
                    @if (app(\App\Services\PostAdoptionClock::class)->isActive())
                        <span class="ml-auto rounded bg-amber-500 px-1.5 py-0.5 text-[.65rem] font-bold text-white">ACTIVE</span>
                    @endif
                </a>
            @endif

        </div>

    </div>

    @include('partials.profile-card')
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

{{-- Sidebar Badges Dismissible Logic --}}
<script>
(function() {
    var dismissibleKeys = ['applications', 'handover', 'monitoring'];

    // If currently on an active route, automatically mark it as dismissed
    @if ($isActive('admin.applications.index'))
        try { localStorage.setItem('sidebar_badge_dismissed_applications', 'true'); } catch (e) {}
    @endif
    @if ($isActive('admin.handover.index', 'admin.handover.show'))
        try { localStorage.setItem('sidebar_badge_dismissed_handover', 'true'); } catch (e) {}
    @endif
    @if ($isActive('admin.monitoring.index'))
        try { localStorage.setItem('sidebar_badge_dismissed_monitoring', 'true'); } catch (e) {}
    @endif

    // Hide badges immediately to prevent UI flicker
    dismissibleKeys.forEach(function(key) {
        try {
            var isDismissed = localStorage.getItem('sidebar_badge_dismissed_' + key) === 'true';
            if (isDismissed) {
                var badge = document.getElementById('sidebar-badge-' + key);
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        } catch (e) {}
    });

    // Attach click listeners to dismiss badges immediately on click
    function initDismissibleBadges() {
        document.querySelectorAll('[data-sidebar-dismissible]').forEach(function(link) {
            link.addEventListener('click', function() {
                var key = this.getAttribute('data-sidebar-dismissible');
                if (key) {
                    try {
                        localStorage.setItem('sidebar_badge_dismissed_' + key, 'true');
                    } catch (e) {}
                    var badge = document.getElementById('sidebar-badge-' + key);
                    if (badge) {
                        badge.style.display = 'none';
                    }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDismissibleBadges);
    } else {
        initDismissibleBadges();
    }
})();
</script>
