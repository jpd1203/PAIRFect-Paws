<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicons')
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $brandName = config('app.brand_name');
    @endphp
    <title>{{ $pageTitle === '' ? $brandName : (str_contains($pageTitle, $brandName) ? $pageTitle : $pageTitle.' | '.$brandName) }}</title>

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
            <!-- @if (! $__env->hasSection('notification-bell-in-header'))
                @include('partials.notification-bell')
            @endif -->
            @include('partials.time-travel-banner')
            @yield('content')
        </div>

    </div>

    <div id="toastHost"></div>

    @php
        $toastPayload = null;
        if (session('toast')) {
            $toastPayload = is_array(session('toast'))
                ? ['message' => session('toast.message') ?? session('toast')['message'] ?? '', 'type' => session('toast.type') ?? session('toast')['type'] ?? 'success']
                : ['message' => session('toast'), 'type' => 'success'];
        } elseif (session('success')) {
            $toastPayload = ['message' => session('success'), 'type' => 'success'];
        } elseif (session('status') && is_string(session('status')) && session('status') !== 'verification-link-sent') {
            $toastPayload = ['message' => session('status'), 'type' => 'success'];
        } elseif (session('error')) {
            $toastPayload = ['message' => session('error'), 'type' => 'error'];
        } elseif (session('warning')) {
            $toastPayload = ['message' => session('warning'), 'type' => 'error'];
        } elseif (session('info')) {
            $toastPayload = ['message' => session('info'), 'type' => 'success'];
        }
    @endphp

    @if ($toastPayload && !empty($toastPayload['message']))
        <script>
            (function () {
                function fireToast() {
                    window.PAIRfectAdmin?.showToast(@json($toastPayload['message']), @json($toastPayload['type']));
                }
                if (document.readyState === 'loading') {
                    window.addEventListener('DOMContentLoaded', fireToast);
                } else {
                    fireToast();
                }
            })();
        </script>
    @endif

    @stack('scripts')
</body>
</html>
