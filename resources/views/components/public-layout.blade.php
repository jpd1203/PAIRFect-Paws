<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicons')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <title>{{ $title ?? config('app.brand_name') }}</title>
    <meta name="description" content="{{ $description ?? 'Red Cubs Pet Patrol - Compassion Make Us Human. Rescue, adopt, and support pets in need through PAIRfect Paws.' }}">
    @include('partials.open-graph')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-white text-gray-900 font-secondary antialiased">
    <x-navbar />

    <main class="flex-1 bg-white">
        {{ $slot }}
    </main>

    <x-footer />

    <div id="toastHost" class="toast-host"></div>

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
                    window.PAIRfectPaws?.showToast(@json($toastPayload['message']), @json($toastPayload['type']));
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
