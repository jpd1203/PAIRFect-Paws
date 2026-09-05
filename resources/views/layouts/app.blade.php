<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PAIRfect Paws')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

    <!-- Mobile-only top bar with hamburger toggle -->
    <div class="topbar">
        <button class="topbar-toggle" id="sidebarToggle" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">PAIRfect Paws</span>
    </div>

    <div class="app-container">

        @include('partials.sidebar')

        <div class="main-content">
            @include('partials.time-travel-banner')
            @include('partials.email-verification-banner')
            @yield('content')
        </div>

    </div>

    <div id="toastHost" class="toast-host"></div>

    @if (session('toast'))
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                window.PAIRfectPaws?.showToast(@json(session('toast.message')), @json(session('toast.type', 'success')));
            });
        </script>
    @endif

    @stack('scripts')
</body>
</html>
