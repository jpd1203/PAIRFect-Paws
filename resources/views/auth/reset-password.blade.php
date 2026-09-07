<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password - PAIRfect Paws</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#fbf9f5] font-sans">
    @include('components.navbar')

    <main class="mx-auto flex min-h-[calc(100vh-76px)] max-w-lg items-center px-4 py-10">
        <section class="w-full rounded-2xl border-2 border-[#d8d1c5] bg-[#f5f1ea] p-6 shadow-md sm:p-8" aria-labelledby="reset-password-heading">
            <div class="mb-5 flex justify-center">
                <img
                    src="{{ asset('images/superhero-cat-avatar.png') }}"
                    alt=""
                    class="h-20 w-20 rounded-full border-4 border-white object-cover shadow-md"
                >
            </div>

            <div class="text-center">
                <h1 id="reset-password-heading" class="text-2xl font-bold text-gray-900">Choose a new password</h1>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">
                    Create a strong password for your PAIRfect Paws account.
                </p>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="mb-1 block text-xs font-bold text-gray-700">Email address*</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email', $email ?? request('email')) }}"
                        autocomplete="email"
                        required
                        aria-describedby="@error('email') email-error @enderror"
                        @error('email') aria-invalid="true" @enderror
                        class="w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-1 @error('email') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-gray-400 focus:border-maroon-600 focus:ring-maroon-600 @enderror"
                    >
                    @error('email')
                        <p id="email-error" class="mt-1.5 text-xs font-medium text-red-800" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-xs font-bold text-gray-700">New password*</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        autofocus
                        required
                        aria-describedby="password-help @error('password') password-error @enderror"
                        @error('password') aria-invalid="true" @enderror
                        class="w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-1 @error('password') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-gray-400 focus:border-maroon-600 focus:ring-maroon-600 @enderror"
                    >
                    <p id="password-help" class="mt-1.5 text-xs text-gray-500">Use at least 8 characters.</p>
                    @error('password')
                        <p id="password-error" class="mt-1 text-xs font-medium text-red-800" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-xs font-bold text-gray-700">Confirm new password*</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-lg border border-gray-400 bg-white px-3.5 py-2.5 text-sm text-gray-900 focus:border-maroon-600 focus:outline-none focus:ring-1 focus:ring-maroon-600"
                    >
                </div>

                <button type="submit" class="w-full rounded-lg bg-maroon-600 py-2.5 text-base font-medium text-white shadow-sm transition duration-200 hover:bg-maroon-700 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                    Reset password
                </button>
            </form>

            <p class="mt-5 text-center text-sm text-gray-600">
                <a href="{{ route('login') }}" class="font-semibold text-maroon-600 underline hover:text-maroon-700">Return to sign in</a>
            </p>
        </section>
    </main>
</body>
</html>
