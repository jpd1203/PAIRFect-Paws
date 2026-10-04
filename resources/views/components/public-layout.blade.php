<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <title>{{ $title ?? config('app.brand_name') }}</title>
    <meta name="description" content="{{ $description ?? 'Red Cubs Pet Patrol - Compassion Make Us Human. Rescue, adopt, and support pets in need through PAIRfect Paws.' }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-white text-gray-900 font-secondary antialiased">
    <x-navbar />

    <main class="flex-1 bg-white">
        {{ $slot }}
    </main>

    <x-footer />
    @stack('scripts')
</body>
</html>
