{{--
    Shared sidebar navigation.
    Usage from any Blade view:
        Set $activePage before rendering, or rely on the current route name
        (see the $isActive() helper below) — no controller wiring needed.
--}}
@php
    $isActive = fn (string ...$routeNames) => collect($routeNames)->contains(fn ($r) => request()->routeIs($r));
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
               class="menu-item {{ $isActive('application.index', 'application.apply', 'application.submit') ? 'active' : '' }}">
                <i class="fa-solid fa-file fa-lg"></i> My Applications
            </a>

            <a href="{{ route('adopter.handover.my') }}"
               class="menu-item {{ $isActive('adopter.handover.my', 'adopter.handover.status', 'adopter.confirm', 'adopter.handover.notifications') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-ramp-box fa-lg"></i> Handover Status
            </a>
        </div>

        @php
    $hasReceivedPet = auth()->check() && auth()->user()->adoptionApplications()
        ->whereHas('handover', function ($query) {
            $query->where('adopter_outcome', 'received');
        })->exists();
@endphp

@if ($hasReceivedPet)
        <div class="menu-title">POST-ADOPTION</div>

        <div class="menu-section">

            <a href="{{ route('monitoring.my-checkins') }}"
               class="menu-item {{ $isActive('monitoring.my-checkins', 'monitoring.index') ? 'active' : '' }}">
                <i class="fa-solid fa-circle-check fa-lg"></i> My Check-ins
            </a>

            <a href="{{ route('monitoring.submit-report') }}"
               class="menu-item {{ $isActive('monitoring.submit-report', 'monitoring.create', 'monitoring.capture-challenge', 'monitoring.submit') ? 'active' : '' }}">
                <i class="fa-solid fa-pen fa-lg"></i> Submit Report
            </a>

            <a href="{{ route('monitoring.overdue-notice') }}"
               class="menu-item {{ $isActive('monitoring.overdue-notice') ? 'active' : '' }}">
                <i class="fa-solid fa-circle-exclamation fa-lg"></i> Overdue Notice
            </a>

            <a href="{{ route('monitoring.flagged-notice') }}"
               class="menu-item {{ $isActive('monitoring.flagged-notice') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation fa-lg"></i> Flagged Notice
            </a>
        </div>
@endif

    </div>
    
    @include('partials.profile-card')
    
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
@endauth
