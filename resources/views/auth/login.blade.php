<!DOCTYPE html>
<html lang="en" class="bg-[#fbf9f5]" style="background-color: #fbf9f5; min-height: 100%;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in & Register - PAIRfect Paws</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body {
            background-color: #fbf9f5 !important;
            min-height: 100% !important;
            height: auto !important;
        }
        main {
            background-color: #fbf9f5 !important;
        }
    </style>
</head>
<body class="bg-[#fbf9f5] min-h-screen flex flex-col font-sans" style="background-color: #fbf9f5;">

    {{-- NAVBAR --}}
    @include('components.navbar')

    {{-- MAIN CONTENT CONTAINER --}}
    <main class="flex-1 min-h-[calc(100vh-80px)] flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-[#fbf9f5]">

        <div class="w-full max-w-[490px] bg-[#f5f1ea] border-2 border-[#d8d1c5] rounded-2xl p-5 sm:p-6 shadow-md relative">

            <!-- Superhero Cat Badge Avatar -->
            <div class="flex justify-center mb-4">
                <img src="{{ asset('images/rcpp-logo-2.png') }}" alt="RCPP Logo"
                     class="w-18 h-18 sm:w-20 sm:h-20 rounded-full shadow-md object-cover">
            </div>

            @if (isset($intendedPet) && $intendedPet)
                <div class="mb-4 flex items-center gap-3.5 rounded-xl border border-[#c9ae72] bg-[#fffaf0] p-3 text-sm text-[#4a3520] shadow-sm">
                    <img src="{{ $intendedPet->image_url }}" alt="{{ $intendedPet->name }}" class="h-12 w-12 rounded-lg object-cover border border-[#c9ae72] shrink-0">
                    <div class="min-w-0">
                        <div class="font-bold text-gray-900 truncate">Adopting {{ $intendedPet->name }}</div>
                        <p class="text-xs text-gray-600 mt-0.5 leading-snug">Sign in or register below. Complete the personality assessment before applying for {{ $intendedPet->name }}.</p>
                    </div>
                </div>
            @endif

            <!-- Tab Switcher Header -->
            <div class="flex border-b-2 border-[#c5bcb0] mb-5">
                <button type="button" id="tabSignInBtn" onclick="switchAuthTab('signin')"
                        class="flex-1 py-1.5 text-center text-sm sm:text-base font-primary font-bold border-b-4 -mb-[2px] transition-all border-maroon-600 text-maroon-600">
                    Sign in
                </button>
                <button type="button" id="tabRegisterBtn" onclick="switchAuthTab('register')"
                        class="flex-1 py-1.5 text-center text-sm sm:text-base font-bold border-b-4 -mb-[2px] transition-all border-transparent text-gray-600 hover:text-gray-900">
                    Register
                </button>
            </div>

            @if (session('status'))
                <div class="mb-4 rounded-xl border border-green-800 bg-green-50 p-3.5 text-xs font-medium leading-relaxed text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-800 bg-red-50 p-3.5 text-xs text-red-800 leading-relaxed font-medium">
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
                <input type="hidden" name="redirect" value="{{ $redirectTo ?? request('redirect', session('url.intended')) }}">

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
                <input type="hidden" name="redirect" value="{{ $redirectTo ?? request('redirect', session('url.intended')) }}">

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

                <!-- Separate account terms and privacy consent -->
                <div class="space-y-3 pt-1">
                    <div id="consentError" role="alert" class="rounded-lg border border-red-300 bg-red-50 p-2 text-xs text-red-800" @if (! $errors->has('terms_accepted') && ! $errors->has('privacy_consent')) hidden @endif>
                        You must agree to the Terms and Conditions and acknowledge the Privacy Notice before creating an account.
                    </div>
                    <div>
                        <div class="flex items-start gap-2 text-[11px] text-gray-700 leading-snug font-medium">
                            <input id="terms_accepted" type="checkbox" name="terms_accepted" required value="1" aria-describedby="terms_accepted_error" class="mt-0.5 rounded border-gray-300 text-maroon-600 focus:ring-maroon-500">
                            <label for="terms_accepted">I have read and agree to the <a href="{{ route('legal.terms') }}" data-legal-dialog="termsDialog" class="font-bold text-maroon-700 underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-maroon-600">PAIRfect Paws Terms and Conditions</a>, including my responsibilities for the proper care and welfare of animals in accordance with Republic Act No. 8485, as amended.</label>
                        </div>
                        <p id="terms_accepted_error" class="mt-1 text-xs text-red-700" @if (! $errors->has('terms_accepted')) hidden @endif>Terms and Conditions consent is required.</p>
                    </div>
                    <div>
                        <div class="flex items-start gap-2 text-[11px] text-gray-700 leading-snug font-medium">
                            <input id="privacy_consent" type="checkbox" name="privacy_consent" required value="1" aria-describedby="privacy_consent_error" class="mt-0.5 rounded border-gray-300 text-maroon-600 focus:ring-maroon-500">
                            <label for="privacy_consent">I have read the <a href="{{ route('legal.privacy') }}" data-legal-dialog="privacyDialog" class="font-bold text-maroon-700 underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-maroon-600">PAIRfect Paws Privacy Notice</a> and consent to the collection, use, storage, and processing of my personal information for the purposes described in the notice.</label>
                        </div>
                        <p id="privacy_consent_error" class="mt-1 text-xs text-red-700" @if (! $errors->has('privacy_consent')) hidden @endif>Privacy Notice consent is required.</p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-1">
                    <button type="submit" class="w-full bg-maroon-600 hover:bg-maroon-700 text-white font-medium py-2.5 rounded-lg shadow-sm transition duration-200 text-base">
                        Create Account
                    </button>
                </div>

                <p class="text-[11px] text-gray-500 text-center leading-tight pt-1">
                    New accounts are assigned as Prospective Adopter. The optional personality assessment comes next; you may skip it and browse pets. Volunteer accounts are created by an Administrator.
                </p>
            </form>

        </div>

    </main>

    <dialog id="termsDialog" aria-labelledby="terms-dialog-title" class="w-[90vw] max-w-3xl max-h-[85vh] overflow-y-auto rounded-xl bg-white p-0 shadow-2xl backdrop:bg-black/60">
        <div class="sticky top-0 z-10 flex justify-end border-b border-gray-200 bg-white px-5 py-3">
            <button type="button" data-close-legal-dialog class="rounded-md px-3 py-1 text-sm font-semibold text-maroon-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-maroon-600" aria-label="Close Terms and Conditions">Close</button>
        </div>
        @include('legal.terms-content')
    </dialog>

    <dialog id="privacyDialog" aria-labelledby="privacy-dialog-title" class="w-[90vw] max-w-3xl max-h-[85vh] overflow-y-auto rounded-xl bg-white p-0 shadow-2xl backdrop:bg-black/60">
        <div class="sticky top-0 z-10 flex justify-end border-b border-gray-200 bg-white px-5 py-3">
            <button type="button" data-close-legal-dialog class="rounded-md px-3 py-1 text-sm font-semibold text-maroon-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-maroon-600" aria-label="Close Privacy Notice">Close</button>
        </div>
        @include('legal.privacy-content')
    </dialog>

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
            document.querySelectorAll('[data-legal-dialog]').forEach((link) => {
                const dialog = document.getElementById(link.dataset.legalDialog);
                if (!dialog || typeof dialog.showModal !== 'function') return;

                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    dialog.showModal();
                });

                dialog.addEventListener('close', () => link.focus());
                dialog.querySelector('[data-close-legal-dialog]').addEventListener('click', () => dialog.close());
                dialog.addEventListener('click', (event) => {
                    const bounds = dialog.getBoundingClientRect();
                    if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
                        dialog.close();
                    }
                });
            });

            const urlParams = new URLSearchParams(window.location.search);
            const isRegisterTab = urlParams.get('tab') === 'register';
            const hasRegisterError = {{ session()->has('register_error') || old('first_name') ? 'true' : 'false' }};

            if (isRegisterTab || hasRegisterError) {
                switchAuthTab('register');
            }

            const registerForm = document.getElementById('registerForm');
            const terms = document.getElementById('terms_accepted');
            const privacy = document.getElementById('privacy_consent');
            const consentError = document.getElementById('consentError');
            const termsError = document.getElementById('terms_accepted_error');
            const privacyError = document.getElementById('privacy_consent_error');
            let consentValidationAttempted = {{ $errors->has('terms_accepted') || $errors->has('privacy_consent') ? 'true' : 'false' }};

            function showConsentErrors() {
                const termsMissing = !terms.checked;
                const privacyMissing = !privacy.checked;
                consentError.hidden = !(termsMissing || privacyMissing);
                termsError.hidden = !termsMissing;
                privacyError.hidden = !privacyMissing;
                terms.setAttribute('aria-invalid', String(termsMissing));
                privacy.setAttribute('aria-invalid', String(privacyMissing));
                return termsMissing || privacyMissing;
            }

            registerForm.addEventListener('submit', (event) => {
                consentValidationAttempted = true;
                if (showConsentErrors()) {
                    event.preventDefault();
                    (terms.checked ? privacy : terms).focus();
                }
            });
            terms.addEventListener('change', () => {
                if (consentValidationAttempted) showConsentErrors();
            });
            privacy.addEventListener('change', () => {
                if (consentValidationAttempted) showConsentErrors();
            });
        });
    </script>

</body>
</html>
