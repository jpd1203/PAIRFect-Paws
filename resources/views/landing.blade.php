<x-public-layout :title="config('app.name').' - Compassion Make Us Human'">

    {{-- HERO --}}
    <section class="relative overflow-hidden bg-white min-h-[calc(100vh-68px)] pt-14 sm:pt-20 pb-20">
        <!-- Background Images -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <div class="grid h-full w-full grid-cols-3 grid-rows-2">
                <div class="overflow-hidden">
                    <img src="{{ asset('images/hero/cat1.png') }}" class="h-full w-full object-cover">
                </div>
                <div class="overflow-hidden">
                    <img src="{{ asset('images/hero/dog1.png') }}" class="h-full w-full object-cover">
                </div>
                <div class="overflow-hidden">
                    <img src="{{ asset('images/hero/dog2.png') }}" class="h-full w-full object-cover">
                </div>
                <div class="overflow-hidden">
                    <img src="{{ asset('images/hero/cat2.png') }}" class="h-full w-full object-cover">
                </div>
                <div class="overflow-hidden">
                    <img src="{{ asset('images/hero/cat3.png') }}" class="h-full w-full object-cover">
                </div>
                <div class="overflow-hidden">
                    <img src="{{ asset('images/hero/dog3.png') }}" class="h-full w-full object-cover">
                </div>
            </div>
        </div>

        <!-- Telephone Number in Drawn Position (far right side under DONATE/Login) -->
        <div class="absolute right-4 top-4 sm:right-8 sm:top-6 lg:right-12 lg:top-6 z-20">
            <div class="inline-flex items-center gap-2 rounded-full border-2 border-maroon-500 bg-white/95 backdrop-blur-sm px-4 py-2 text-sm font-black text-maroon-600 shadow-md">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3.5A1.5 1.5 0 013.5 2h1.148a1.5 1.5 0 011.465 1.175l.716 3.223a1.5 1.5 0 01-.437 1.485L4.784 9.485a11.03 11.03 0 005.731 5.731l1.602-1.608a1.5 1.5 0 011.485-.437l3.223.716A1.5 1.5 0 0118 15.352V16.5a1.5 1.5 0 01-1.5 1.5H15c-8.284 0-15-6.716-15-15v-.5z"/></svg>
                +63 918 985 2149
            </div>
        </div>

        <div class="relative z-10 flex h-full items-start pt-25 sm:pt-28 lg:pt-32">

    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="max-w-3xl">

            <p class="text-lg font-black uppercase tracking-[0.25em] text-maroon-500">
                Compassion in Action
            </p>

            <h1 class="mt-4 text-5xl font-black leading-tight tracking-tight text-gray-900 sm:text-6xl lg:text-7xl">
                Welcome to
                <br>
                <span class="text-maroon-500">
                    Red Cubs Pet Patrol
                </span>
            </h1>

            <h2 class="mt-10 text-3xl font-black tracking-tight text-maroon-500 sm:text-4xl">
                Together, We Make Every Pet Safe &amp; Loved
            </h2>

            <p class="mt-6 max-w-2xl text-xl font-semibold leading-relaxed text-gray-900">
                We rescue, protect, and care for pets in need. Together, we make every pet safe
                and loved &mdash; find your perfect match today.
            </p>

        </div>

    </div>

</div> 
    </section>

    {{-- LOVE & PROTECTION --}}
    <section class="bg-cream-100">
        <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="order-2 flex justify-center lg:order-1">
                <img src="{{ asset('images/cats-love.png') }}" alt="Cats cared for by Red Cubs Pet Patrol"
                     class="max-h-96 w-auto drop-shadow-xl">
            </div>

            <div class="order-1 lg:order-2">
                <h2 class="section-title">
                    Every Pet Deserves<br>
                    <span class="section-title-accent">Love &amp; Protection</span>
                </h2>
                <p class="mt-5 leading-relaxed text-gray-700">
                    Thousands of stray and neglected pets need our help every day. At Red Cubs Pet
                    Patrol, we believe compassion makes us human and with your support, we can
                    rescue, protect, and provide care to those who can't speak for themselves.
                </p>
                <p class="mt-4 leading-relaxed text-gray-700">
                    Your donation, big or small, gives food, shelter, and medical care to pets who
                    need a hero. Together, we can make every paw feel safe and loved.
                </p>

                <a href="{{ route('login') }}" class="btn-primary mt-8">
                    Adopt a Pet
                </a>
            </div>
        </div>
    </section>

    {{-- SERVICES --}}
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="section-title">
                    Every Pet Deserves<br>
                    <span class="section-title-accent">Care, Safety, and a Loving Home</span>
                </h2>
            </div>

            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <x-service-card image="images/service-rescue.jpg" title="Rescue">
                    We respond to pets in need&mdash;helping lost, stray, and abandoned animals find
                    safety and care within our community.
                </x-service-card>

                <x-service-card image="images/service-adoption.jpg" title="Adoption">
                    Every pet deserves a loving home. We connect rescued cats and dogs with families
                    ready to give them a second chance.
                </x-service-card>

                <x-service-card image="images/service-kapon.jpg" title="Kapon">
                    We support responsible pet ownership through spay and neuter programs that keep
                    our community's pets healthy and safe.
                </x-service-card>
            </div>
        </div>
    </section>

    {{-- COMMUNITY JOURNEY --}}
    <section class="relative bg-cream-100 py-16 overflow-hidden min-h-[260px] flex items-center justify-center">
        <!-- Left: Cat Peek (pinned to left screen edge and bottom of cream section) -->
        <div class="hidden md:flex flex-col items-start absolute left-0 bottom-0 z-10">
            <p class="ml-6 sm:ml-10 -mb-4 z-20 text-sm font-extrabold text-gray-800 bg-white px-4 py-2 rounded-full border border-gray-300 shadow-lg tracking-wide whitespace-nowrap">
                800 donors have contributed!
            </p>
            <img src="{{ asset('images/cat-peek.png') }}" alt="Cat peek" class="h-48 sm:h-56 lg:h-64 w-auto object-contain object-bottom">
        </div>

        <!-- Center: Donations Counter -->
        <div class="mx-auto max-w-4xl px-4 text-center z-20 py-2">
            <h2 class="section-title text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-gray-900">
                Our Pets' Community Journey
            </h2>
            <p class="mt-3 text-sm sm:text-base lg:text-lg font-bold text-maroon-500 max-w-xl mx-auto leading-relaxed">
                Every peso helps provide medical care, food and love. Track our progress and make a difference!
            </p>
            <a href="{{ route('community-impact') }}" class="mt-6 group inline-block cursor-pointer no-underline transition hover:scale-105">
                <span class="text-6xl sm:text-7xl lg:text-8xl font-black tracking-tight text-gray-900 group-hover:text-maroon-500 transition leading-none block">
                    {{ number_format($impactTotal ?: 45500) }}
                </span>
                <p class="mt-2 text-sm sm:text-base lg:text-lg font-extrabold uppercase tracking-widest text-gray-800 group-hover:text-maroon-600 transition">
                    TOTAL COMMUNITY DONATIONS
                </p>
                <p class="text-xs sm:text-sm text-gray-500 font-medium mt-1">Funds Record Since Launch</p>
            </a>
        </div>

        <!-- Right: Dog Face Accent (pinned to right screen edge and bottom of cream section) -->
        <div class="hidden md:flex absolute right-0 bottom-0 z-10 items-end">
            <img src="{{ asset('images/dog-face-accent.png') }}" alt="Dog accent" class="h-48 sm:h-56 lg:h-64 w-auto object-contain object-bottom">
        </div>
    </section>

    {{-- WHERE EVERY VISIT BRINGS HOPE --}}
    <section class="bg-white">
        <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="flex justify-center">
                <img src="{{ asset('images/dogs-group.png') }}" alt="Dogs cared for by Red Cubs Pet Patrol"
                     class="max-h-80 w-auto drop-shadow-xl">
            </div>

            <div>
                <h2 class="section-title">
                    Where Every Visit<br>
                    Brings <span class="section-title-accent">Hope</span>
                </h2>

                <div class="mt-8 space-y-6">
                    <div>
                        <p class="text-lg font-extrabold text-maroon-500">Red Cubs Cat Shelter</p>
                        <p class="text-sm font-semibold text-gray-700">7th ave., Beverly Hills, Antipolo</p>
                    </div>
                    <div>
                        <p class="text-lg font-extrabold text-maroon-500">Red Cubs Dog Shelter</p>
                        <p class="text-sm font-semibold text-gray-700">Banha Subd., San Jose, Antipolo</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

</x-public-layout>
