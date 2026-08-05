<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in - PAIRfect Paws</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-sm bg-white border border-[#e2ddd7] rounded-card shadow-card p-8">

        <div class="text-center mb-6">
            <h1 class="font-primary font-bold text-2xl text-primary">PAIRfect Paws</h1>
            <p class="text-[#777] text-sm mt-1">Sign in to your adopter account</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-status-danger-text bg-status-danger-bg px-4 py-3 text-status-danger-text text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login.store') }}" method="POST" class="flex flex-col gap-4" novalidate>
            @csrf

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>

            <label class="flex items-center gap-2 text-sm text-text-muted">
                <input type="checkbox" name="remember"> Remember me
            </label>

            <button type="submit" class="btn btn-primary w-full mt-2">Sign In</button>
        </form>

    </div>

</body>
</html>
