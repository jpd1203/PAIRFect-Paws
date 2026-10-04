@if ($pets->isEmpty())
    <div class="empty-state">
        <i class="fa-solid fa-paw"></i>
        <h3>No pets match these filters</h3>
        <p>Try a different species or age range.</p>
    </div>
@else
    <div class="pet-grid">

    @foreach ($pets as $pet)
        <div class="pet-card group flex flex-col hover:-translate-y-1 hover:shadow-card hover:border-[#c9ae72]">

            {{-- Pet Image --}}
            <a href="javascript:void(0);"
            onclick="openPetModal({{ $pet->id }})"
            class="relative block overflow-hidden rounded-lg aspect-square bg-gray-100 cursor-pointer border">
                @if ($pet->photo_path)
                    <img src="{{ $pet->image_url }}"
                        alt="{{ $pet->name }}"
                        class="w-full h-full object-cover rounded-lg transition-transform duration-500 ease-out group-hover:scale-110 hover:border-[#777]">
                @else
                    <div class="w-full h-full flex items-center justify-center bg-maroon-50 text-maroon-600" role="img" aria-label="No photo available for {{ $pet->name }}">
                        <i class="fa-solid fa-{{ strtolower($pet->species?->value ?? $pet->species) === 'cat' ? 'cat' : 'dog' }} text-5xl" aria-hidden="true"></i>
                    </div>
                @endif

                {{-- Meet Pet Overlay --}}
                <div class="absolute inset-0 flex items-end justify-center bg-gradient-to-t from-black/45 via-black/5 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                    <span class="mb-3 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-bold text-[#4a3520] shadow-md translate-y-2 transition-transform duration-300 group-hover:translate-y-0">
                        <i class="fa-solid fa-paw mr-1"></i>
                        Meet {{ $pet->name }}
                    </span>
                </div>
            </a>

            {{-- Pet Name --}}
            <h3 class="hover:text-maroon-600 transition-colors no-underline"> {{ $pet->name }} </h3>

            {{-- Pet Details --}}
            <p>{{ $pet->card_subtitle }}</p>

            {{-- Action Button --}}
            <div class="card-actions mt-auto">
                <button type="button" class="btn btn-adoptMe" data-pet-id="{{ $pet->id }}" onclick="openPetModal({{ $pet->id }})">
                    {{ $pet->availability_status->value === 'Soft-Reserved'
                        ? 'View Processing Status'
                        : (auth()->check() && ! auth()->user()->hasVerifiedEmail() ? 'View Pet' : 'Adopt Me!') }}
                </button>
            </div>

        </div>
    @endforeach
</div>
@endif
