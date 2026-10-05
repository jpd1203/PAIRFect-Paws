<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicons')
    <title>Forgot Password - PAIRfect Paws</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#fbf9f5] font-sans">
    @include('components.navbar')

    <main class="mx-auto flex min-h-[calc(100vh-76px)] max-w-lg items-center px-4 py-10">
        <section class="w-full rounded-2xl border-2 border-[#d8d1c5] bg-[#f5f1ea] p-6 shadow-md sm:p-8" aria-labelledby="forgot-password-heading">
            <div class="mb-5 flex justify-center">
                <img
                    src="{{ asset('images/rcpp-logo-2.png') }}"
                    alt=""
                    class="h-20 w-20 rounded-full object-cover shadow-md"
                >
            </div>

            <div class="text-center">
                <h1 id="forgot-password-heading" class="text-2xl font-bold text-gray-900">Forgot your password?</h1>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">
                    Enter the email address connected to your account. We will send you a secure link to choose a new password.
                </p>
            </div>

            @if (session('status'))
                <div class="mt-5 rounded-xl border border-green-800 bg-green-50 p-3 text-sm font-medium text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5" novalidate>
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-xs font-bold text-gray-700">Email address*</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        autofocus
                        required
                        aria-describedby="@error('email') email-error @enderror"
                        @error('email') aria-invalid="true" @enderror
                        class="w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-1 @error('email') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-gray-400 focus:border-maroon-600 focus:ring-maroon-600 @enderror"
                    >
                    @error('email')
                        <p id="email-error" class="mt-1.5 text-xs font-medium text-red-800" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded-lg bg-maroon-600 py-2.5 text-base font-medium text-white shadow-sm transition duration-200 hover:bg-maroon-700 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                    Send password reset link
                </button>
            </form>

            <p class="mt-5 text-center text-sm text-gray-600">
                Remembered your password?
                <a href="{{ route('login') }}" class="font-semibold text-maroon-600 underline hover:text-maroon-700">Return to sign in</a>
            </p>
        </section>
    </main>
</body>
</html>
