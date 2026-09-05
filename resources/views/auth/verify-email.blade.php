<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify Email - PAIRfect Paws</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#fbf9f5] font-sans">
    @include('components.navbar')

    <main class="mx-auto flex min-h-[calc(100vh-76px)] max-w-2xl items-center px-4 py-10">
        <section class="w-full rounded-2xl border-2 border-[#d8d1c5] bg-[#f5f1ea] p-6 shadow-md sm:p-8">
            <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-maroon-100 text-2xl text-maroon-700">
                <span aria-hidden="true">&#9993;</span>
            </div>

            <h1 class="text-2xl font-bold text-gray-900">Verify your email address</h1>
            <p class="mt-3 leading-relaxed text-gray-700">
                We sent a verification link to <strong>{{ $user->email }}</strong>. The link expires after 60 minutes.
                You may stay signed in and browse pets, but applications and protected account actions remain locked until verification is complete.
            </p>

            @if (session('verification_error'))
                <div class="mt-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm font-medium text-red-800" role="alert">
                    {{ session('verification_error') }}
                </div>
            @endif

            @if (session('status') === 'verification-link-sent')
                <div class="mt-5 rounded-xl border border-green-300 bg-green-50 p-4 text-sm font-medium text-green-800" role="status">
                    A new verification link has been sent. Check your inbox and spam folder.
                </div>
            @endif

            <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-maroon-600 px-5 py-2.5 font-semibold text-white transition hover:bg-maroon-700 sm:w-auto">
                        Resend verification email
                    </button>
                </form>

                @if ($user->isAdopter())
                    <a href="{{ route('animal.index') }}" class="rounded-lg border border-gray-400 bg-white px-5 py-2.5 text-center font-semibold text-gray-800 transition hover:bg-gray-50">
                        Browse pets
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg px-5 py-2.5 font-semibold text-gray-700 transition hover:bg-white/70 sm:w-auto">
                        Log out
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
