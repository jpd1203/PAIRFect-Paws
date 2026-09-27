@php
    $energyLabels = [
        1 => 'Very Low Energy',
        2 => 'Low Energy',
        3 => 'Moderate Energy',
        4 => 'High Energy',
        5 => 'Very High Energy',
    ];
    $independenceLabels = [
        1 => 'Very Dependent',
        2 => 'Somewhat Dependent',
        3 => 'Mostly Independent',
        4 => 'Independent',
        5 => 'Very Independent',
    ];
    $trainabilityLabels = [
        1 => 'Slow Learner',
        2 => 'Moderate Trainability',
        3 => 'Average Trainability',
        4 => 'Highly Trainable',
        5 => 'Exceptionally Trainable',
    ];
    $temperamentLabels = [
        1 => 'Cautious / Reactive',
        2 => 'Reserved',
        3 => 'Generally calm',
        4 => 'Gentle & Friendly',
        5 => 'Very Calm & Adaptable',
    ];

    $energyVal = ($pet->energy_level > 0) ? ($energyLabels[(int)$pet->energy_level] ?? $pet->energy_level) : 'N/A';
    $independenceVal = ($pet->independence > 0) ? ($independenceLabels[(int)$pet->independence] ?? $pet->independence) : 'N/A';
    $trainabilityVal = ($pet->trainability > 0) ? ($trainabilityLabels[(int)$pet->trainability] ?? $pet->trainability) : 'N/A';
    $temperamentVal = ($pet->temperament > 0) ? ($temperamentLabels[(int)$pet->temperament] ?? $pet->temperament) : 'N/A';
@endphp

<x-public-layout :title="$pet->name . ' - ' . config('app.name')">
    <main class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8">
        <!-- Back Link -->
        <a href="{{ route('pets.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-900 transition-colors duration-150 no-underline mb-6">
            <i class="fa-solid fa-chevron-left text-xs"></i>
            <span>All available pets</span>
        </a>

        <!-- Top Section: Photo and Story -->
        <div class="grid gap-8 lg:grid-cols-[380px_1fr]">
            <!-- Pet Photo -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 shadow-sm bg-[#f5f3ef]">
                <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}, {{ $pet->breed }}" 
                     class="aspect-square w-full h-full object-cover">
            </div>

            <!-- Details & Story -->
            <div class="flex flex-col">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="font-heading text-3xl sm:text-4xl font-bold text-gray-900 leading-tight">
                            {{ $pet->name }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $pet->species_display }} &middot; {{ $pet->breed ?? 'Mix' }} &middot; {{ $pet->age_years ? $pet->age_years . ' yrs' : ($pet->age_months ? $pet->age_months . ' mos' : $pet->age_group) }} &middot; {{ $pet->sex_display }}
                        </p>
                    </div>
                    <span class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-800 shrink-0">
                        {{ $pet->status }}
                    </span>
                </div>

                <!-- Story & About Box -->
                <div class="mt-6 rounded-xl border border-gray-200 bg-[#FAF8F5] p-5 shadow-sm">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-700">
                        Story & About {{ $pet->name }}
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700">
                        {{ $pet->description ?: ($pet->behavioral_notes ?: 'Meet ' . $pet->name . '! A wonderful companion currently looking for a loving forever home.') }}
                    </p>
                </div>

                <!-- Adopt CTA Button -->
                <div class="mt-auto flex flex-wrap items-center gap-3 pt-6">
                    @if ($pet->availability_status->value === 'Available')
                        <button type="button" onclick="document.getElementById('applySection').scrollIntoView({behavior: 'smooth'})"
                                class="rounded-md border border-[#2f7d63] bg-[#ddf3ea] px-6 py-2.5 text-sm font-bold text-[#1f6b52] transition-colors duration-150 hover:bg-[#cbebdd] shadow-sm cursor-pointer">
                            Adopt Me!
                        </button>
                        <span class="text-xs sm:text-sm text-gray-500">Application takes about 5 minutes</span>
                    @else
                        <span class="rounded-md border border-gray-300 bg-gray-100 px-5 py-2 text-sm font-semibold text-gray-600">
                            {{ $pet->status }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Pet Profile Section -->
        <section aria-labelledby="details-heading" class="mt-12 pt-8 border-t border-gray-200">
            <h2 id="details-heading" class="font-heading text-xl font-bold text-gray-900">
                Pet Profile
            </h2>
            <div class="mt-4 grid gap-x-12 md:grid-cols-2">
                <!-- Left Column -->
                <dl class="divide-y divide-gray-200 text-sm">
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Species</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->species_display }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Breed</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->breed ?? 'Mixed Breed' }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Age</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->age_years ? $pet->age_years . ' yrs' : $pet->age_group }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Sex</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->sex_display }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Intake Date</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->intake_date ? $pet->intake_date->format('m/d/Y') : 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Health Status</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->health_status }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Vaccination Records</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->vaccination_record_status ?: 'Unknown' }}</dd>
                    </div>
                </dl>

                <!-- Right Column -->
                <dl class="divide-y divide-gray-200 text-sm">
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Status</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->status }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Energy Level</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $energyVal }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Independence Level</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $independenceVal }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Trainability</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $trainabilityVal }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Physical Size</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $pet->physical_size ?: 'Unknown' }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Temperament</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $temperamentVal }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Apply Section -->
        <section id="applySection" aria-labelledby="apply-heading" class="mt-14 rounded-2xl border border-gray-200 bg-white p-6 sm:p-8 shadow-sm scroll-mt-8">
            <h2 id="apply-heading" class="font-heading text-xl sm:text-2xl font-bold text-gray-900">
                Apply to adopt {{ $pet->name }}
            </h2>
            <p class="mb-6 mt-1 text-sm text-gray-500">
                Tell us a bit about you and your home. We'll get back to you by email.
            </p>

            @if ($pet->availability_status->value !== 'Available')
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800 text-sm">
                    <strong>Notice:</strong> This pet is currently {{ strtolower($pet->status) }}. New applications are temporarily paused while candidates are evaluated.
                </div>
            @else
                @auth
                    @if (auth()->user()->isAdopter())
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-xl bg-[#FAF8F5] border border-gray-200">
                            <div>
                                <h3 class="font-semibold text-gray-900">Ready to start your application?</h3>
                                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Submit your living setup and background info to apply for {{ $pet->name }}.</p>
                            </div>
                            <a href="{{ route('application.apply', $pet) }}" 
                               class="inline-flex items-center gap-2 rounded-md bg-[#2f7d63] px-6 py-2.5 text-sm font-bold text-white transition hover:bg-[#25634e] shadow-sm no-underline whitespace-nowrap">
                                <i class="fa-solid fa-file-pen"></i>
                                Start Application Form
                            </a>
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-600">
                            You are signed in as <strong>{{ auth()->user()->role->value }}</strong>. To submit an adoption application, please sign in with an Adopter account.
                        </div>
                    @endif
                @else
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-xl bg-[#FAF8F5] border border-gray-200">
                        <div>
                            <h3 class="font-semibold text-gray-900">Sign in to adopt {{ $pet->name }}</h3>
                            <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Create a free account or log in to track your adoption application progress.</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <a href="{{ route('login') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-100 transition no-underline">
                                Log In
                            </a>
                            <a href="{{ route('register') }}" class="rounded-md bg-maroon-600 hover:bg-maroon-700 px-5 py-2 text-sm font-semibold text-white transition shadow-sm no-underline">
                                Register
                            </a>
                        </div>
                    </div>
                @endauth
            @endif
        </section>
    </main>
</x-public-layout>
