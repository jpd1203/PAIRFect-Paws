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

        <!-- Shelter contact number -->
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
                        <br><span class="text-maroon-500">Red Cubs Pet Patrol</span>
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
    <section class="bg-cream-100 border-t border-b border-gray-100">
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
                    By adopting a pet, you give them a safe home and a second chance. Together, we
                    can make every paw feel safe and loved.
                </p>

                <a href="{{ route('login') }}" class="btn-primary mt-8 inline-flex items-center justify-center rounded-lg bg-maroon-600 hover:bg-maroon-700 text-white font-bold px-6 py-3 shadow-md transition duration-200 text-base border-0 no-underline cursor-pointer"><i class="fa-solid fa-paw mr-1"></i>
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

    {{-- MEET THE PETS --}}
    <section class="bg-cream-100 py-14 border-t border-[#f0ede6]" aria-labelledby="meet-heading">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center sm:mb-10"> 
                <div class="mb-2 flex items-center justify-center gap-3 text-primary"> 
                    <span class="h-px w-10 bg-primary"></span> 
                    <i class="fa-solid fa-paw text-lg"></i> 
                    <span class="h-px w-10 bg-primary"></span> 
                </div> 
                <h2 class="font-heading font-primary text-3xl font-bold text-[#2f2327] sm:text-4xl"> Meet Our <span class="text-primary font-primary">Featured Pets</span></h2> 
                <p class="mt-2 text-sm text-gray-500 sm:text-base"> These amazing animals are waiting for their forever homes. </p> 
            </div>

            <div id="petsCarousel"
                class="-mx-4 sm:-mx-6 mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth scroll-px-4 sm:scroll-px-6 px-4 sm:px-6 pb-5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">

                @forelse ($featuredPets ?? [] as $pet)
                    <article
                        class="group relative flex w-[230px] shrink-0 snap-start flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white p-3.5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1.5 hover:shadow-xl hover:border-[#c9ae72] active:scale-[0.98]">
                        <div class="pointer-events-none absolute -right-3 -top-3 opacity-0 transition-all duration-300 group-hover:right-1 group-hover:top-1 group-hover:opacity-10">
                            <i class="fa-solid fa-paw text-5xl text-[#8b6b43] rotate-12"></i>
                        </div>

                        <div class="relative overflow-hidden rounded-xl aspect-square bg-[#f5f3ef]">
                            <a href="{{ route('pets.show', $pet) }}" class="block h-full w-full">
                                <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}, {{ $pet->breed }}" class="aspect-square w-full h-full object-cover rounded-xl border border-[#999] transition-transform duration-500 ease-out group-hover:scale-110">

                                <div class="absolute inset-0 flex items-end justify-center bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                    <span class="mb-3 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-bold text-[#4a3520] shadow-md translate-y-2 transition-transform duration-300 group-hover:translate-y-0">
                                        <i class="fa-solid fa-paw mr-1"></i>
                                        Meet {{ $pet->name }}
                                    </span>
                                </div>
                            </a>
                            <span class="absolute top-2 right-2 rounded-full bg-white/95 backdrop-blur-sm px-2.5 py-1 text-[11px] font-semibold text-gray-800 border border-gray-200 shadow-sm transition-all duration-300 group-hover:scale-105">
                                {{ $pet->status }}
                            </span>
                        </div>

                        {{-- Pet Information --}}
                        <div class="relative z-10">
                            <h3 class="mt-3 font-bold text-gray-900 text-base leading-snug transition-colors duration-200 group-hover:text-[#7a5a35]">
                                {{ $pet->name }}
                            </h3>

                            <p class="mt-0.5 text-xs text-gray-500 truncate"> 
                                {{ $pet->species_display }} &middot; {{ $pet->breed ?? 'Mix' }} &middot; {{ $pet->age_years ? $pet->age_years . ' yrs' : ($pet->age_months ? $pet->age_months . ' mos' : $pet->age_group) }} &middot; {{ $pet->sex === 'Female' ? 'F' : 'M' }}
                            </p>

                            {{-- Interactive Button --}}
                            <div class="mt-auto flex justify-end pt-3">
                                <a href="{{ route('pets.show', $pet) }}" aria-label="View and Apply: {{ $pet->name }}" class="btn btn-adoptMe group/button inline-flex items-center gap-1.5 transition-all duration-200 hover:gap-2.5 hover:shadow-md active:scale-95">
                                    <span>View and Apply</span>
                                    <i class="fa-solid fa-arrow-right text-[10px] transition-transform duration-200 group-hover/button:translate-x-1"></i>
                                </a>
                            </div>
                        </div>
                    </article>

                @empty

                    <div class="flex w-full flex-col items-center justify-center py-10 text-center text-gray-500">
                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#f5f3ef]">
                            <i class="fa-solid fa-paw text-xl text-[#c9ae72]"></i>
                        </div>

                        <p class="text-sm font-medium">No pets currently listed.</p>
                        <p class="mt-1 text-xs text-gray-400">Check back soon for new furry friends!</p>
                    </div>

                @endforelse


                {{-- See More Card --}}
                <a href="{{ route('pets.index') }}"
                class="group relative flex w-[230px] shrink-0 snap-start flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-[#c9ae72] bg-white p-6 text-center no-underline cursor-pointer transition-all duration-300 hover:-translate-y-1.5 hover:border-[#a98b58] hover:bg-[#fffdf8] hover:shadow-lg active:scale-[0.98]">

                    {{-- Decorative paw --}}
                    <div class="pointer-events-none absolute -bottom-5 -right-4 rotate-12 opacity-[0.06] transition-transform duration-500 group-hover:scale-125 group-hover:rotate-6">
                        <i class="fa-solid fa-paw text-8xl text-[#4a3520]"></i>
                    </div>

                    {{-- Arrow Circle --}}
                    <span class="relative flex h-14 w-14 items-center justify-center rounded-full bg-[#fffaf0] text-[#4a3520] ring-1 ring-[#d8c7a5] shadow-sm transition-all duration-300 group-hover:scale-110 group-hover:bg-[#4a3520] group-hover:text-white group-hover:ring-[#4a3520] group-hover:shadow-md">
                        <i class="fa-solid fa-arrow-right text-base transition-transform duration-300 group-hover:translate-x-1"></i>
                    </span>

                    <span class="relative mt-4 font-bold text-gray-900 transition-colors duration-200 group-hover:text-[#7a5a35]">
                        See more available pets
                    </span>

                    <span class="relative mt-1 text-xs text-gray-600">
                        {{ $availablePetsCount ?? 0 }} pets are waiting for a home
                    </span>

                    <span class="relative mt-3 text-[10px] font-semibold uppercase tracking-wider text-[#9a7b4f] opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                        Explore <i class="fa-solid fa-arrow-right ml-1"></i>
                    </span>

                </a>

            </div>

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

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const carousel = document.getElementById('petsCarousel');
                const prevBtn = document.getElementById('prevPetsBtn');
                const nextBtn = document.getElementById('nextPetsBtn');
                if (!carousel || !prevBtn || !nextBtn) return;

                function updateButtons() {
                    prevBtn.disabled = carousel.scrollLeft <= 5;
                    nextBtn.disabled = carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 5;
                }

                prevBtn.addEventListener('click', function () {
                    carousel.scrollBy({ left: -460, behavior: 'smooth' });
                });

                nextBtn.addEventListener('click', function () {
                    carousel.scrollBy({ left: 460, behavior: 'smooth' });
                });

                carousel.addEventListener('scroll', updateButtons);
                window.addEventListener('resize', updateButtons);
                updateButtons();
            });
        </script>
    @endpush

</x-public-layout>
