<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PAIRfect Paws Admin')</title>

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

    <div class="topbar max-[991px]:flex hidden">
        <button class="topbar-toggle" id="sidebarToggle" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">PAIRfect Paws Admin</span>
    </div>

    <div class="app-container">

        @include('admin.partials.sidebar')

        <div class="main-content custom-scrollbar">
            @include('partials.notification-bell')
            @include('partials.time-travel-banner')
            @yield('content')
        </div>

    </div>

    <div id="toastHost"></div>

    @if (session('toast'))
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                window.PAIRfectAdmin?.showToast(@json(session('toast.message')), @json(session('toast.type', 'success')));
            });
        </script>
    @endif

    @stack('scripts')
</body>
</html>
