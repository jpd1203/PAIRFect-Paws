<x-public-layout :title="'Available Pets - '.config('app.name')">
    <div class="bg-white min-h-[calc(100vh-140px)]">
        <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-1 py-10">
            <!-- Page Title & Subtitle -->
            <h1 class="font-heading font-primary font-bold text-3xl mb-0.5 text-text-dark max-[991px]:text-2xl">Available Pets</h1>
            <p class="text-[#777] text-base m-0 max-[991px]:text-sm">Browse animals ready for adoption</p>

            <!-- Filters Bar -->
            <form method="GET" action="{{ route('pets.index') }}" class="mt-6 flex flex-wrap items-center gap-3">
                <div class="filter-section m-0">
                
                    <div class="select-wrapper">
                        <select name="species" onchange="this.form.submit()">
                            <option value="all" @selected(($speciesFilter ?? 'all') === 'all')>All Species</option>
                            <option value="Dog" @selected(($speciesFilter ?? '') === 'Dog')>Dogs</option>
                            <option value="Cat" @selected(($speciesFilter ?? '') === 'Cat')>Cats</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    
                    <div class="select-wrapper">
                        <select name="age" onchange="this.form.submit()">
                            <option value="all" @selected(($ageFilter ?? 'all') === 'all')>All Ages</option>
                            <option value="Baby" @selected(($ageFilter ?? '') === 'Baby')>Baby</option>
                            <option value="Young" @selected(($ageFilter ?? '') === 'Young')>Young</option>
                            <option value="Adult" @selected(($ageFilter ?? '') === 'Adult')>Adult</option>
                            <option value="Senior" @selected(($ageFilter ?? '') === 'Senior')>Senior</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                </div>

                @if (($speciesFilter ?? 'all') !== 'all' || ($ageFilter ?? 'all') !== 'all')
                    <a href="{{ route('pets.index') }}" class="text-sm font-semibold text-maroon-600 hover:underline ml-2">
                        <i class="fa-solid fa-rotate-left pr-1"></i> Reset filters
                    </a>
                @endif

                <!-- Find Your Match Button -->
                <div class="mt-4 ml-auto">
                    <a href="{{ route('recommendation.intake') }}" 
                       class="inline-flex items-center rounded-full bg-maroon-600 hover:bg-maroon-700 text-white px-5 py-2 text-xs sm:text-sm font-semibold shadow-sm transition-all duration-150 hover:shadow no-underline cursor-pointer">
                        <span>Not sure who fits? Find your match</span>
                    </a>
                </div>
            </form>

            <!-- Pet Grid or Empty State -->
            @if ($pets->isEmpty())
                <div class="mt-8 flex flex-col items-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-16 text-center">
                    <i class="fa-solid fa-magnifying-glass text-2xl text-gray-400"></i>
                    <p class="mt-3 text-sm font-medium text-gray-900">No pets match these filters</p>
                    <a href="{{ route('pets.index') }}" class="mt-3 text-sm font-semibold text-maroon-600 hover:underline">
                        Clear filters
                    </a>
                </div>
            @else
                <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($pets as $pet)
                        <article class="group flex flex-col rounded-xl border border-gray-200 bg-white p-3 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-card hover:border-[#c9ae72]">
                            <a href="{{ route('pets.show', $pet) }}"
                            class="relative block overflow-hidden rounded-lg aspect-square bg-gray-100 no-underline border border-[#999]">
                                <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}, {{ $pet->breed }}" class="aspect-square w-full h-full object-cover transition-transform duration-500 ease-out group-hover:scale-110">
                                <div class="absolute inset-0 flex items-end justify-center bg-gradient-to-t from-black/45 via-black/5 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                    <span class="mb-3 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-bold text-[#4a3520] shadow-md translate-y-2 transition-transform duration-300 group-hover:translate-y-0">
                                        <i class="fa-solid fa-paw mr-1"></i> Meet {{ $pet->name }}
                                    </span>
                                </div>
                            </a>

                            <h3 class="mt-3 font-semibold text-gray-900 text-base leading-snug">
                                <a href="{{ route('pets.show', $pet) }}" class="hover:text-maroon-600 transition-colors no-underline mt-2.5 mb-0.5 text-[1.15rem] text-[#2f2327] font-semibold">
                                    {{ $pet->name }}
                                </a>
                            </h3>

                            {{-- Pet Details --}}
                            <p class="text-[#666] text-sm mb-2.5 truncate"> 
                                {{ $pet->species_display }} &middot; {{ $pet->breed ?? 'Mix' }} &middot; {{ $pet->age_years ? $pet->age_years . ' yrs' : ($pet->age_months ? $pet->age_months . ' mos' : $pet->age_group) }} &middot; {{ $pet->sex === 'Female' ? 'F' : 'M' }}
                            </p>

                            <div class="mt-auto flex justify-end pt-3">
                                <a href="{{ route('pets.show', $pet) }}" aria-label="{{ auth()->check() && ! auth()->user()->hasVerifiedEmail() ? 'View pet: ' : 'Adopt Me: ' }}{{ $pet->name }}" class="btn btn-adoptMe">
                                    {{ auth()->check() && ! auth()->user()->hasVerifiedEmail() ? 'View Pet' : 'Adopt Me!' }}
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-8 flex justify-center">
                    {{ $pets->links() }}
                </div>
            @endif
        </main>
</x-public-layout>
