<x-public-layout :title="'Available Pets - '.config('app.name')">
    <div class="bg-white min-h-[calc(100vh-140px)]">
        <main class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-10">
            <!-- Page Title & Subtitle -->
            <h1 class="font-heading text-3xl font-bold text-gray-900">Available Pets</h1>
            <p class="mt-1 text-sm text-gray-500">Browse animals ready for adoption</p>

            <!-- Find Your Match Button -->
            <div class="mt-4">
                <a href="{{ route('recommendation.intake') }}" 
                   class="inline-flex items-center rounded-full bg-maroon-600 hover:bg-maroon-700 text-white px-5 py-2 text-xs sm:text-sm font-semibold shadow-sm transition-all duration-150 hover:shadow no-underline cursor-pointer">
                    <span>Not sure who fits? Find your match</span>
                </a>
            </div>

            <!-- Filters Bar -->
            <form method="GET" action="{{ route('pets.index') }}" class="mt-6 flex flex-wrap items-center gap-3">
                <label class="relative">
                    <span class="sr-only">Species</span>
                    <select name="species" onchange="this.form.submit()" 
                            class="h-9 rounded-md border border-gray-300 bg-white px-3 pr-8 text-sm text-gray-900 focus:border-maroon-600 focus:outline-none focus:ring-2 focus:ring-maroon-600/15 cursor-pointer">
                        <option value="all" @selected(($speciesFilter ?? 'all') === 'all')>All Species</option>
                        <option value="Dog" @selected(($speciesFilter ?? '') === 'Dog')>Dogs</option>
                        <option value="Cat" @selected(($speciesFilter ?? '') === 'Cat')>Cats</option>
                    </select>
                </label>

                <label class="relative">
                    <span class="sr-only">Age</span>
                    <select name="age" onchange="this.form.submit()" 
                            class="h-9 rounded-md border border-gray-300 bg-white px-3 pr-8 text-sm text-gray-900 focus:border-maroon-600 focus:outline-none focus:ring-2 focus:ring-maroon-600/15 cursor-pointer">
                        <option value="all" @selected(($ageFilter ?? 'all') === 'all')>All Ages</option>
                        <option value="Baby" @selected(($ageFilter ?? '') === 'Baby')>Baby</option>
                        <option value="Young" @selected(($ageFilter ?? '') === 'Young')>Young</option>
                        <option value="Adult" @selected(($ageFilter ?? '') === 'Adult')>Adult</option>
                        <option value="Senior" @selected(($ageFilter ?? '') === 'Senior')>Senior</option>
                    </select>
                </label>

                @if (($speciesFilter ?? 'all') !== 'all' || ($ageFilter ?? 'all') !== 'all')
                    <a href="{{ route('pets.index') }}" class="text-xs font-semibold text-maroon-600 hover:underline ml-2">
                        Reset filters
                    </a>
                @endif

                <p class="ml-auto text-sm text-gray-500" aria-live="polite">
                    {{ $pets->total() }} {{ $pets->total() === 1 ? 'pet' : 'pets' }}
                </p>
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
                        <article class="flex flex-col rounded-xl border border-gray-200 bg-white p-3 shadow-sm hover:shadow-md transition-all duration-200">
                            <a href="{{ route('pets.show', $pet) }}" class="overflow-hidden rounded-lg aspect-square bg-gray-100 block no-underline">
                                <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}, {{ $pet->breed }}" 
                                     class="aspect-square w-full h-full object-cover transition-transform duration-300 hover:scale-105">
                            </a>
                            <h3 class="mt-3 font-semibold text-gray-900 text-base leading-snug">
                                <a href="{{ route('pets.show', $pet) }}" class="hover:text-maroon-600 transition-colors no-underline text-gray-900">
                                    {{ $pet->name }}
                                </a>
                            </h3>
                            <p class="mt-0.5 text-xs text-gray-500 truncate">
                                {{ $pet->species_display }} &middot; {{ $pet->breed ?? 'Mix' }} &middot; {{ $pet->age_years ? $pet->age_years . ' yrs' : ($pet->age_months ? $pet->age_months . ' mos' : $pet->age_group) }} &middot; {{ $pet->sex === 'Female' ? 'F' : 'M' }}
                            </p>
                            <div class="mt-auto flex justify-end pt-3">
                                <a href="{{ route('pets.show', $pet) }}" aria-label="Adopt Me: {{ $pet->name }}"
                                   class="whitespace-nowrap rounded-md border border-[#c9ae72] bg-[#f1dfb4] px-3 py-1.5 text-xs font-semibold text-[#4a3520] transition-colors duration-150 hover:bg-[#e8d197] shadow-sm no-underline">
                                    Adopt Me!
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
    </div>
</x-public-layout>
