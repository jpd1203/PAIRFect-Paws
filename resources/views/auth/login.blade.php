<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in & Register - PAIRfect Paws</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#fbf9f5] min-h-screen flex flex-col font-sans">

    {{-- NAVBAR --}}
    @include('components.navbar')

    {{-- MAIN CONTENT CONTAINER --}}
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">

        <div class="w-full max-w-[430px] bg-[#f5f1ea] border-2 border-[#d8d1c5] rounded-2xl p-5 sm:p-6 shadow-md relative">

            <!-- Superhero Cat Badge Avatar -->
            <div class="flex justify-center mb-4">
                <img src="{{ asset('images/superhero-cat-avatar.png') }}" alt="Superhero Cat"
                     class="w-18 h-18 sm:w-20 sm:h-20 rounded-full border-4 border-white shadow-md object-cover">
            </div>

            <!-- Tab Switcher Header -->
            <div class="flex border-b-2 border-[#c5bcb0] mb-5">
                <button type="button" id="tabSignInBtn" onclick="switchAuthTab('signin')"
                        class="flex-1 py-1.5 text-center text-sm sm:text-base font-bold border-b-4 -mb-[2px] transition-all border-maroon-600 text-maroon-600">
                    Sign in
                </button>
                <button type="button" id="tabRegisterBtn" onclick="switchAuthTab('register')"
                        class="flex-1 py-1.5 text-center text-sm sm:text-base font-bold border-b-4 -mb-[2px] transition-all border-transparent text-gray-600 hover:text-gray-900">
                    Register
                </button>
            </div>

            @if (session('status'))
                <div class="mb-4 rounded-xl border border-green-300 bg-green-50 p-3.5 text-xs font-medium leading-relaxed text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-300 bg-red-50 p-3.5 text-xs text-red-700 leading-relaxed font-medium">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- 1. SIGN IN FORM -->
            <form id="signInForm" action="{{ route('login.store') }}" method="POST" class="space-y-4" novalidate>
                @csrf

                <div>
                    <label for="signin_email" class="block text-xs font-bold text-gray-700 mb-1">Email*</label>
                    <input id="signin_email" name="email" type="email" value="{{ old('email') }}" required
                           class="w-full bg-white border border-gray-400 rounded-lg px-3.5 py-2 text-sm text-gray-900 focus:outline-none focus:border-maroon-600 focus:ring-1 focus:ring-maroon-600">
                </div>

                <div>
                    <label for="signin_password" class="block text-xs font-bold text-gray-700 mb-1">Password*</label>
                    <input id="signin_password" name="password" type="password" required
                           class="w-full bg-white border border-gray-400 rounded-lg px-3.5 py-2 text-sm text-gray-900 focus:outline-none focus:border-maroon-600 focus:ring-1 focus:ring-maroon-600">
                    <a href="{{ route('password.request') }}"
                       class="text-[11px] text-gray-600 hover:underline block text-right mt-1 font-medium">
                        Forgot password?
                    </a>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-maroon-600 hover:bg-maroon-700 text-white font-medium py-2.5 rounded-lg shadow-sm transition duration-200 text-base">
                        Login
                    </button>
                </div>
            </form>

            <!-- 2. REGISTER FORM -->
            <form id="registerForm" action="{{ route('register.store') }}" method="POST" class="space-y-3.5 hidden" novalidate>
                @csrf

                <!-- Name (First, Last) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Name*</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <input name="first_name" type="text" value="{{ old('first_name') }}" placeholder="First" required
                                   class="w-full bg-white border border-gray-400 rounded-lg px-3.5 py-2 text-xs text-gray-900 focus:outline-none focus:border-maroon-600 focus:ring-1 focus:ring-maroon-600">
                            <span class="text-[10px] text-gray-500 mt-0.5 block">First</span>
                        </div>
                        <div>
                            <input name="last_name" type="text" value="{{ old('last_name') }}" placeholder="Last" required
                                   class="w-full bg-white border border-gray-400 rounded-lg px-3.5 py-2 text-xs text-gray-900 focus:outline-none focus:border-maroon-600 focus:ring-1 focus:ring-maroon-600">
                            <span class="text-[10px] text-gray-500 mt-0.5 block">Last</span>
                        </div>
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Email*</label>
                    <input name="email" type="email" value="{{ old('email') }}" required
                           class="w-full bg-white border border-gray-400 rounded-lg px-3.5 py-2 text-xs text-gray-900 focus:outline-none focus:border-maroon-600 focus:ring-1 focus:ring-maroon-600">
                </div>

                <x-philippine-address-fields id-prefix="login_register_address" compact />

                <!-- Password & Confirm Password -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Password*</label>
                        <input name="password" type="password" required
                               class="w-full bg-white border border-gray-400 rounded-lg px-3.5 py-2 text-xs text-gray-900 focus:outline-none focus:border-maroon-600 focus:ring-1 focus:ring-maroon-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Confirm Password*</label>
                        <input name="password_confirmation" type="password" required
                               class="w-full bg-white border border-gray-400 rounded-lg px-3.5 py-2 text-xs text-gray-900 focus:outline-none focus:border-maroon-600 focus:ring-1 focus:ring-maroon-600">
                    </div>
                </div>

                <!-- Terms & Conditions Checkbox -->
                <div class="pt-1">
                    <label class="flex items-start gap-2 text-[11px] text-gray-700 leading-snug cursor-pointer font-medium">
                        <input type="checkbox" name="terms" required value="1" class="mt-0.5 rounded border-gray-300 text-maroon-600 focus:ring-maroon-500">
                        <span>I agree to the terms and conditions under RA 8485 and RA 10173.</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-1">
                    <button type="submit" class="w-full bg-maroon-600 hover:bg-maroon-700 text-white font-medium py-2.5 rounded-lg shadow-sm transition duration-200 text-base">
                        Create Account
                    </button>
                </div>

                <p class="text-[11px] text-gray-500 text-center leading-tight pt-1">
                    New accounts are assigned as Prospective Adopter. Volunteer accounts are created by an Administrator.
                </p>
            </form>

        </div>

    </main>

    <script>
        function switchAuthTab(tab) {
            const signInForm = document.getElementById('signInForm');
            const registerForm = document.getElementById('registerForm');
            const tabSignInBtn = document.getElementById('tabSignInBtn');
            const tabRegisterBtn = document.getElementById('tabRegisterBtn');

            if (tab === 'register') {
                signInForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
                tabSignInBtn.className = "flex-1 py-1.5 text-center text-sm sm:text-base font-bold border-b-4 -mb-[2px] transition-all border-transparent text-gray-600 hover:text-gray-900";
                tabRegisterBtn.className = "flex-1 py-1.5 text-center text-sm sm:text-base font-bold border-b-4 -mb-[2px] transition-all border-maroon-600 text-maroon-600";
            } else {
                registerForm.classList.add('hidden');
                signInForm.classList.remove('hidden');
                tabRegisterBtn.className = "flex-1 py-1.5 text-center text-sm sm:text-base font-bold border-b-4 -mb-[2px] transition-all border-transparent text-gray-600 hover:text-gray-900";
                tabSignInBtn.className = "flex-1 py-1.5 text-center text-sm sm:text-base font-bold border-b-4 -mb-[2px] transition-all border-maroon-600 text-maroon-600";
            }
        }

        // Auto-switch to register tab if query param ?tab=register or validation error on register form
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const isRegisterTab = urlParams.get('tab') === 'register';
            const hasRegisterError = {{ session()->has('register_error') || old('first_name') ? 'true' : 'false' }};

            if (isRegisterTab || hasRegisterError) {
                switchAuthTab('register');
            }
        });
    </script>

</body>
</html>
