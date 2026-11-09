@extends('layouts.app')

@section('title', 'Pet Recommendation - PAIRfect Paws')

@section('notification-bell-in-header', true)
@section('content')

    <div class="nonsticky-header custom-scrollbar">
        <div class="main-content-header">
            <div class="heading-text">
                <h2>{{ $isOnboarding ? 'Your Adoption Profile' : 'Pet Recommendation' }}</h2>
                <p>{{ $isOnboarding ? 'Get started with the 20-question personality assessment, or skip it for now and browse pets.' : 'Complete your personality questionnaire and household information to find compatible pets.' }}</p>
            </div>

            @include('partials.notification-bell')
        </div>
    
        <div class="mt-2">

            <div class="mb-6 rounded-xl border border-line bg-sand/60 px-4 py-3 text-sm text-ink-muted flex items-start gap-3">
                <i class="fa-solid fa-circle-info text-[#A61D24] text-base mt-0.5 shrink-0"></i>
                <div class="leading-relaxed">
                    @if ($returnPet)
                        Personality Assessment Required for {{ $returnPet->name }}. Complete your reusable assessment, then continue your application. Shelter staff make the final decision.
                    @elseif ($isOnboarding)
                        Your account is ready. This assessment is optional today, but you must complete it before personalized recommendations or a formal adoption application. You can still browse pets if you skip.
                    @else
                        Your reusable personality and household profile calculates pet compatibility. Shelter staff make the final adoption decision.
                    @endif
                </div>
            </div>

            @if (auth()->check() && ! auth()->user()->hasVerifiedEmail())
                <div role="alert" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 flex items-center justify-between gap-3">
                    <div>
                        You can complete your profile and view pet recommendations now. Verify your email address before you can apply to adopt a pet.
                    </div>
                    <a href="{{ route('verification.notice') }}" class="font-bold underline shrink-0 hover:text-amber-950">Verify email</a>
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="mt-2 list-disc pl-5 space-y-1">
                        @foreach ($errors->getMessages() as $field => $messages)
                            @foreach ($messages as $error)
                                <li>
                                    @if (str_starts_with($field, 'bfi_responses.'))
                                        <a href="#bfi_{{ substr($field, strlen('bfi_responses.')) }}" class="underline hover:text-red-950">{{ $error }}</a>
                                    @else
                                        {{ $error }}
                                    @endif
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Two-Column Form Layout matching Pet Assessment --}}
            <form action="{{ route('recommendation.start') }}" method="POST" id="recoForm">
                @csrf

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

                    {{-- Left Column: Household Profile + Personality Questionnaire --}}
                    <div class="space-y-6">

                        {{-- 1. Your Adopter Profile Section Card --}}
                        <section class="rounded-xl border border-line bg-white p-5 sm:p-6 shadow-sm">
                            <header class="border-b border-line pb-4 mb-5">
                                <h2 class="text-lg font-semibold text-ink font-primary">Your Adopter Profile</h2>
                                <p class="mt-0.5 text-sm text-ink-muted">
                                    {{ auth()->check() ? 'Your answers are saved to your account and reused for future pets.' : 'Your answers are saved for this browser session and can be added to your account when you sign up.' }} Housing, children, existing pets, and care budget determine eligibility.
                                </p>
                            </header>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                {{-- Housing Type --}}
                                <div class="flex flex-col">
                                    <label for="housing_type" class="mb-1.5 text-[13px] font-semibold text-ink">Housing Type*</label>
                                    <div class="select-wrapper">
                                        <select id="housing_type" name="housing_type" required class="h-[42px] border border-[#777] rounded-md px-3 text-sm bg-white focus:border-maroon-600 focus:ring-4 focus:ring-maroon-600/10 focus:outline-none w-full">
                                            <option value="">Select Housing Type</option>
                                            @foreach ($options::HOUSING_TYPES as $option)
                                                <option value="{{ $option }}" @selected(old('housing_type', $profile?->housing_type) === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                                    </div>
                                </div>

                                {{-- Monthly Income Range --}}
                                <div class="flex flex-col">
                                    <label for="monthly_income_range" class="mb-1.5 text-[13px] font-semibold text-ink">Monthly Income Range*</label>
                                    <div class="select-wrapper">
                                        <select id="monthly_income_range" name="monthly_income_range" required class="h-[42px] border border-[#777] rounded-md px-3 text-sm bg-white focus:border-maroon-600 focus:ring-4 focus:ring-maroon-600/10 focus:outline-none w-full">
                                            <option value="">Select Income Range</option>
                                            @foreach ($options::INCOME_RANGES as $option)
                                                <option value="{{ $option }}" @selected(old('monthly_income_range', $profile?->monthly_income_range) === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                                    </div>
                                </div>

                                {{-- Pets at home & Children (via matching-household-fields) --}}
                                @include('partials.matching-household-fields')
                            </div>
                        </section>

                        {{-- 2. Personality Questionnaire Cards (Categorized matching Pet Assessment) --}}
                        @include('partials.bfi-questionnaire')

                        {{-- Mobile bottom clearance so floating bar never covers content --}}
                        <div class="h-16 lg:hidden"></div>

                    </div>

                    {{-- Right Column: Sticky Sidebar matching Pet Assessment --}}
                    <aside class="hidden lg:block" aria-label="Recommendation progress">
                        <div class="sticky top-6 space-y-4">

                            {{-- Adopter Overview Card --}}
                            <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sand text-[#A61D24] text-xl border border-line">
                                        <i class="fa-solid fa-user-check"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-base font-semibold text-ink font-primary">
                                            {{ auth()->user()?->first_name ? auth()->user()->first_name . ' ' . auth()->user()->last_name : (auth()->user()?->name ?? 'Prospective Adopter') }}
                                        </p>
                                        <p class="truncate text-sm text-ink-muted">
                                            Personality & Household
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center justify-between border-t border-line pt-4">
                                    <span class="text-sm text-ink-muted">20 Questions · BFI-2</span>
                                    <div class="flex items-center gap-1" role="img" aria-label="Progress stage">
                                        <span class="h-1.5 w-6 rounded-full bg-[#A61D24]"></span>
                                        <span id="sidebarStagePill" class="h-1.5 w-6 rounded-full bg-line transition-colors"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Sticky Progress Card --}}
                            <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
                                <div class="flex items-baseline justify-between">
                                    <p class="text-sm font-semibold text-ink font-primary">Progress</p>
                                    <p class="text-sm tabular-nums text-ink-muted">
                                        <span id="desktopBfiAnsweredCount" class="font-semibold text-ink">0</span> of 20
                                    </p>
                                </div>

                                {{-- Overall Progress Bar --}}
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    <div id="desktopBfiProgressBar" class="h-full rounded-full bg-[#A61D24] transition-[width] duration-300 ease-out" style="width: 0%;"></div>
                                </div>

                                {{-- Section / Dimensions Checklist Jump Links matching Pet Assessment --}}
                                @php
                                    $dimCheckMeta = [
                                        'extraversion' => ['label' => 'Extraversion', 'count' => 6],
                                        'conscientiousness' => ['label' => 'Conscientiousness', 'count' => 6],
                                        'neuroticism' => ['label' => 'Emotional Stability', 'count' => 4],
                                        'openness' => ['label' => 'Openness', 'count' => 4],
                                    ];
                                    $dimSavedCounts = [
                                        'extraversion' => 0,
                                        'conscientiousness' => 0,
                                        'neuroticism' => 0,
                                        'openness' => 0,
                                    ];
                                    $dimConfigKeys = config('matching.bfi.dimensions', []);
                                    $savedBfi = old('bfi_responses', $profile?->bfi_responses ?? []);
                                    foreach ($dimConfigKeys as $dk => $keys) {
                                        foreach ($keys as $k) {
                                            if (isset($savedBfi[$k]) && $savedBfi[$k] !== '') {
                                                $dimSavedCounts[$dk] = ($dimSavedCounts[$dk] ?? 0) + 1;
                                            }
                                        }
                                    }
                                @endphp
                                <ul class="mt-4 space-y-0.5">
                                    @foreach ($dimCheckMeta as $dimKey => $meta)
                                        @php
                                            $currCount = $dimSavedCounts[$dimKey] ?? 0;
                                            $isComplete = $currCount >= $meta['count'];
                                        @endphp
                                        <li>
                                            <button type="button" data-jump-to="section-{{ $dimKey }}"
                                                    class="flex w-full items-center justify-between gap-3 rounded-md px-2 py-1.5 text-left text-sm transition-colors duration-150 hover:bg-[#FDF2F2] hover:text-[#A61D24] text-ink cursor-pointer">
                                                <span class="truncate">{{ $meta['label'] }}</span>
                                                <div class="flex items-center justify-end gap-2 shrink-0 min-w-[56px]">
                                                    <span class="text-xs tabular-nums text-ink-subtle">
                                                        <span data-bfi-dim-count="{{ $dimKey }}">{{ $currCount }}</span>/{{ $meta['count'] }}
                                                    </span>
                                                    <span class="flex items-center justify-center w-3.5 h-3.5 shrink-0">
                                                        <i class="fa-solid fa-check text-xs text-emerald-600" data-bfi-dim-check="{{ $dimKey }}" style="{{ $isComplete ? '' : 'display: none;' }}"></i>
                                                    </span>
                                                </div>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>

                                {{-- Actions --}}
                                <div class="mt-5 space-y-2">
                                    <button type="submit"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#A61D24] hover:bg-[#8D171E] px-4 py-2.5 text-sm font-semibold text-white transition-colors duration-150 shadow-sm cursor-pointer">
                                        <i class="fa-solid fa-heart text-sm"></i>
                                        <span>Start Matching</span>
                                    </button>

                                    @if ($isOnboarding)
                                        <button type="submit" form="skipOnboardingForm"
                                                class="flex w-full items-center justify-center rounded-xl border border-[#A61D24] text-[#A61D24] hover:bg-[#FDF2F2] px-4 py-2 text-sm font-semibold transition-colors duration-150 cursor-pointer">
                                            Skip for now
                                        </button>
                                    @else
                                        <a href="{{ route('animal.index') }}"
                                           class="flex w-full items-center justify-center rounded-xl border border-line text-ink-muted hover:bg-sand px-4 py-2 text-sm font-semibold transition-colors duration-150 cursor-pointer">
                                            Browse pets
                                        </a>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </aside>

                </div>

            </form>

            {{-- Floating Mobile Bottom Action Bar (Fixed to phone bottom) --}}
            <div class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-between gap-3 border-t border-line bg-white/95 backdrop-blur-md px-4 py-3 lg:hidden shadow-[0_-4px_16px_rgba(0,0,0,0.08)]">
                <div class="flex flex-col gap-1 min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold text-ink tabular-nums">
                            <span id="mobileBfiAnsweredCount">0</span> of 20
                        </span>
                        <span class="text-[11px] text-ink-muted">rated</span>
                    </div>
                    {{-- Mini animated progress bar --}}
                    <div class="h-1.5 w-24 sm:w-36 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <div id="mobileBfiProgressBar" class="h-full rounded-full bg-[#A61D24] transition-all duration-300 ease-out" style="width: 0%;"></div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if ($isOnboarding)
                        <button type="submit" form="skipOnboardingForm"
                                class="rounded-xl border border-line px-3 py-2 text-xs font-semibold text-ink-muted hover:bg-sand transition-colors cursor-pointer">
                            Skip for now
                        </button>
                    @endif
                    <button type="submit" form="recoForm" id="mobileStartMatchingBtn"
                            class="flex items-center gap-2 rounded-xl bg-[#A61D24] hover:bg-[#8D171E] active:scale-95 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-150 cursor-pointer">
                        <i class="fa-solid fa-heart text-xs"></i>
                        <span>Start Matching</span>
                    </button>
                </div>
            </div>

            @if ($isOnboarding)
                <form id="skipOnboardingForm" action="{{ route('recommendation.onboarding.skip') }}" method="POST" class="hidden">
                    @csrf
                </form>
            @endif

        </div>
    </div>

@endsection
