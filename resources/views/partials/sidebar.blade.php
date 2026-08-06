{{--
    Shared sidebar navigation.
    Usage from any Blade view:
        Set $activePage before rendering, or rely on the current route name
        (see the $isActive() helper below) — no controller wiring needed.
--}}
@php
    $isActive = fn (string ...$routeNames) => collect($routeNames)->contains(fn ($r) => request()->routeIs($r));
@endphp

<div class="sidebar" id="appSidebar">

    <div class="logo">
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
               class="menu-item {{ $isActive('application.index', 'application.apply', 'application.submit') ? 'active' : '' }}">
                <i class="fa-solid fa-file fa-lg"></i> My Application
            </a>

        </div>

        <div class="menu-title">POST-ADOPTION</div>

        <div class="menu-section">

            <a href="{{ route('monitoring.index') }}"
               class="menu-item {{ $isActive('monitoring.index', 'monitoring.report.show') ? 'active' : '' }}">
                <i class="fa-solid fa-circle-check fa-lg"></i> My Check-ins
            </a>

            <a href="{{ route('flagged.submitReport') }}"
               class="menu-item {{ $isActive('flagged.submitReport', 'flagged.previewReport', 'flagged.confirmSubmit') ? 'active' : '' }}">
                <i class="fa-solid fa-pen fa-lg"></i> Submit Report
            </a>

            <a href="{{ route('flagged.overdueNotice') }}"
               class="menu-item {{ $isActive('flagged.overdueNotice') ? 'active' : '' }}">
                <i class="fa-solid fa-circle-exclamation fa-lg"></i> Overdue Notice
            </a>

            <a href="{{ route('flagged.flaggedNotice') }}"
               class="menu-item {{ $isActive('flagged.flaggedNotice') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation fa-lg"></i> Flagged Notice
            </a>

        </div>

    </div>

    @include('partials.profile-card')

</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
