{{--
    Shared sidebar navigation.
    Usage from any Blade view:
        Set $activePage before rendering, or rely on the current route name
        (see the $isActive() helper below) — no controller wiring needed.
--}}
@php
    $isActive = fn (string ...$routeNames) => collect($routeNames)->contains(fn ($r) => request()->routeIs($r));
    $user = auth()->user();

    $adopterApplicationsCount = 0;
    $adopterHandoverCount = 0;
    $adopterDueReportsCount = 0;
    $adopterOverdueCount = 0;
    $adopterFlaggedCount = 0;

    if ($user) {
        $adopterApplicationsCount = $user->adoptionApplications()
            ->whereIn('status', [
                \App\Enums\ApplicationStatus::Pending->value,
                \App\Enums\ApplicationStatus::UnderReview->value,
                \App\Enums\ApplicationStatus::InterviewScheduled->value,
                \App\Enums\ApplicationStatus::DocumentFlagged->value,
            ])
            ->count();

        $adopterHandoverCount = \App\Models\Handover::where('user_id', $user->id)
            ->whereNull('adopter_confirmed_at')
            ->count();

        $today = app(\App\Services\PostAdoptionClock::class)->today()->toDateString();
        $approvedLogsBase = \App\Models\PostAdoptionLog::query()
            ->whereHas('adoptionApplication', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('status', \App\Enums\ApplicationStatus::Approved->value);
            });

        $adopterDueReportsCount = (clone $approvedLogsBase)
            ->whereNull('submitted_date')
            ->whereDate('scheduled_date', '<=', $today)
            ->count();

        $adopterOverdueCount = (clone $approvedLogsBase)
            ->whereNull('submitted_date')
            ->whereDate('scheduled_date', '<', $today)
            ->count();

        $adopterFlaggedCount = (clone $approvedLogsBase)
            ->where('is_flagged', true)
            ->whereNull('resolved_at')
            ->count();
    }

    $hasReceivedPet = $user && $user->adoptionApplications()
        ->whereHas('handover', function ($query) {
            $query->where('adopter_outcome', 'received');
        })->exists();
@endphp

@auth
<div class="sidebar" id="appSidebar">

    <div class="logo flex items-center justify-center">
        <h3>PAIRfect Paws</h3>
    </div>

    <div class="sidebar-nav">

        <div class="menu-title">ADOPTION</div>

        <div class="menu-section">

            <a href="{{ route('animal.index') }}"
               class="menu-item {{ $isActive('animal.index', 'animal.show', 'home') ? 'active' : '' }}">
                <i class="fa-solid fa-paw fa-lg"></i> Browse Pets
            </a>

            <a href="{{ route('recommendation.intake') }}"
               class="menu-item {{ $isActive('recommendation.intake', 'recommendation.start', 'recommendation.results') ? 'active' : '' }}">
                <i class="fa-solid fa-heart fa-lg"></i> Pet Recommendation
            </a>

            <a href="{{ route('application.index') }}"
               class="menu-item {{ $isActive('application.index', 'application.apply', 'application.submit') ? 'active' : '' }}"
               data-sidebar-dismissible="adopter_applications"
               data-badge-count="{{ $adopterApplicationsCount }}">
                <i class="fa-solid fa-file fa-lg"></i> My Applications
                @if ($adopterApplicationsCount > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white sidebar-dismissible-badge"
                          id="sidebar-badge-adopter_applications">
                        {{ $adopterApplicationsCount > 99 ? '99+' : $adopterApplicationsCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('adopter.handover.my') }}"
               class="menu-item {{ $isActive('adopter.handover.my', 'adopter.handover.status', 'adopter.confirm', 'adopter.handover.notifications') ? 'active' : '' }}"
               data-sidebar-dismissible="adopter_handover"
               data-badge-count="{{ $adopterHandoverCount }}">
                <i class="fa-solid fa-truck-ramp-box fa-lg"></i> Handover Status
                @if ($adopterHandoverCount > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white sidebar-dismissible-badge"
                          id="sidebar-badge-adopter_handover">
                        {{ $adopterHandoverCount > 99 ? '99+' : $adopterHandoverCount }}
                    </span>
                @endif
            </a>
        </div>

@if ($hasReceivedPet)
        <div class="menu-title">POST-ADOPTION</div>

        <div class="menu-section">

            <a href="{{ route('monitoring.my-checkins') }}"
               class="menu-item {{ $isActive('monitoring.my-checkins', 'monitoring.index') ? 'active' : '' }}">
                <i class="fa-solid fa-circle-check fa-lg"></i> My Check-ins
            </a>

            <a href="{{ route('monitoring.submit-report') }}"
               class="menu-item {{ $isActive('monitoring.submit-report', 'monitoring.create', 'monitoring.capture-challenge', 'monitoring.submit') ? 'active' : '' }}"
               data-sidebar-dismissible="adopter_submit_report"
               data-badge-count="{{ $adopterDueReportsCount }}">
                <i class="fa-solid fa-pen fa-lg"></i> Submit Report
                @if ($adopterDueReportsCount > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white sidebar-dismissible-badge"
                          id="sidebar-badge-adopter_submit_report">
                        {{ $adopterDueReportsCount > 99 ? '99+' : $adopterDueReportsCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('monitoring.overdue-notice') }}"
               class="menu-item {{ $isActive('monitoring.overdue-notice') ? 'active' : '' }}"
               data-sidebar-dismissible="adopter_overdue"
               data-badge-count="{{ $adopterOverdueCount }}">
                <i class="fa-solid fa-circle-exclamation fa-lg"></i> Overdue Notice
                @if ($adopterOverdueCount > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white sidebar-dismissible-badge"
                          id="sidebar-badge-adopter_overdue">
                        {{ $adopterOverdueCount > 99 ? '99+' : $adopterOverdueCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('monitoring.flagged-notice') }}"
               class="menu-item {{ $isActive('monitoring.flagged-notice') ? 'active' : '' }}"
               data-sidebar-dismissible="adopter_flagged"
               data-badge-count="{{ $adopterFlaggedCount }}">
                <i class="fa-solid fa-triangle-exclamation fa-lg"></i> Flagged Notice
                @if ($adopterFlaggedCount > 0)
                    <span class="ml-auto min-w-5 rounded-full bg-status-danger-text px-1.5 py-0.5 text-center text-[.7rem] font-bold leading-none text-white sidebar-dismissible-badge"
                          id="sidebar-badge-adopter_flagged">
                        {{ $adopterFlaggedCount > 99 ? '99+' : $adopterFlaggedCount }}
                    </span>
                @endif
            </a>
        </div>
@endif

    </div>
    
    @include('partials.profile-card')
    
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

{{-- Adopter Sidebar Badges Dismissible Logic --}}
<script>
(function() {
    var dismissibleKeys = [
        'adopter_applications',
        'adopter_handover',
        'adopter_submit_report',
        'adopter_overdue',
        'adopter_flagged'
    ];

    // If currently on an active route, automatically mark it as dismissed
    @if ($isActive('application.index', 'application.apply', 'application.submit'))
        try { localStorage.setItem('sidebar_badge_dismissed_adopter_applications', 'true'); } catch (e) {}
    @endif
    @if ($isActive('adopter.handover.my', 'adopter.handover.status', 'adopter.confirm', 'adopter.handover.notifications'))
        try { localStorage.setItem('sidebar_badge_dismissed_adopter_handover', 'true'); } catch (e) {}
    @endif
    @if ($isActive('monitoring.submit-report', 'monitoring.create', 'monitoring.capture-challenge', 'monitoring.submit'))
        try { localStorage.setItem('sidebar_badge_dismissed_adopter_submit_report', 'true'); } catch (e) {}
    @endif
    @if ($isActive('monitoring.overdue-notice'))
        try { localStorage.setItem('sidebar_badge_dismissed_adopter_overdue', 'true'); } catch (e) {}
    @endif
    @if ($isActive('monitoring.flagged-notice'))
        try { localStorage.setItem('sidebar_badge_dismissed_adopter_flagged', 'true'); } catch (e) {}
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
@endauth
