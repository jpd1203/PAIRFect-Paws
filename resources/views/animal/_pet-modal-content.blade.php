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

    $energyVal = ($pet->energy_level > 0) ? ($energyLabels[(int)$pet->energy_level] ?? $pet->energy_level) : 'N/A';
    $independenceVal = ($pet->independence > 0) ? ($independenceLabels[(int)$pet->independence] ?? $pet->independence) : 'N/A';
    $trainabilityVal = ($pet->trainability > 0) ? ($trainabilityLabels[(int)$pet->trainability] ?? $pet->trainability) : 'N/A';
    $temperamentVal = $pet->temperament_display;
@endphp

<!-- Modal Header: Title, Status & Close Button -->
<div class="flex items-start justify-between mb-4 gap-2">
    <div>
        <h2 class="text-2xl sm:text-3xl font-bold font-primary text-text-dark m-0 leading-tight">{{ $pet->name }}</h2>
        <p class="text-sm font-medium text-[#777] mt-0.5">Pet Profile</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <span class="badge badge-{{ $pet->adoption_status_class }}">
            {{ $pet->status }}
        </span>
    </div>
</div>

<!-- Equal-Height Twin Section: Interactive Photo & Story Box -->
<div class="flex flex-col sm:flex-row items-stretch gap-5 mb-6 pb-5 border-b border-[#eee8df]">
    <!-- Left: Interactive Pet Picture (Equal height to story box, hover zoom & lightbox) -->
    <div class="w-full sm:w-[170px] md:w-[195px] shrink-0 self-stretch flex flex-col">
        @if ($pet->photo_path)
            <div class="relative w-full h-full min-h-[140px] rounded-2xl overflow-hidden border border-gray-200 shadow-sm cursor-pointer group bg-[#f5f3ef]"
                 onclick="openPetPhotoLightbox('{{ $pet->image_url }}', '{{ addslashes($pet->name) }}')"
                 title="Click to view full photo">
                <img src="{{ $pet->image_url }}"
                     alt="{{ $pet->name }}"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                <!-- Interactive Hover Overlay -->
                <div class="absolute inset-0 bg-black/35 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex flex-col items-center justify-center gap-1 text-white text-xs font-semibold backdrop-blur-[1px]">
                    <i class="fa-solid fa-magnifying-glass-plus text-base"></i>
                    <span>View Full Photo</span>
                </div>
                <span class="absolute bottom-2 right-2 bg-black/60 text-white text-[10px] px-2 py-0.5 rounded-full backdrop-blur-sm pointer-events-none flex items-center gap-1">
                    <i class="fa-regular fa-image"></i>
                    <span>Enlarge</span>
                </span>
            </div>
        @else
            <div class="w-full h-full min-h-[140px] rounded-2xl border border-maroon-100 bg-maroon-50 text-maroon-600 flex items-center justify-center" role="img" aria-label="No photo available for {{ $pet->name }}">
                <i class="fa-solid fa-{{ strtolower($pet->species?->value ?? $pet->species) === 'cat' ? 'cat' : 'dog' }} text-5xl" aria-hidden="true"></i>
            </div>
        @endif
    </div>

    <!-- Right: Story Box (Adjusts height based on content; photo matches automatically) -->
    <div class="flex-1 min-w-0 bg-[#FAF8F5] rounded-2xl p-4 border border-[#EEE8DF] self-stretch flex flex-col justify-center">
        <span class="block text-xs font-bold text-[#888] uppercase tracking-wider mb-2">
            Story & About {{ $pet->name }}
        </span>
        <p class="m-0 text-sm leading-relaxed text-text-dark">
            {{ $pet->description ?: ($pet->behavioral_notes ?: 'Meet ' . $pet->name . '! A wonderful ' . strtolower($pet->breed ?? $pet->species_display) . ' currently looking for a loving forever home.') }}
        </p>
    </div>
</div>

<!-- Landscape 2-Column Profile Table -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1">

    <!-- Left Column -->
    <div>
        <div class="profile-row">
            <span>Species</span>
            <span>{{ $pet->species_display }}</span>
        </div>
        <div class="profile-row">
            <span>Breed</span>
            <span>{{ $pet->breed }}</span>
        </div>
        <div class="profile-row">
            <span>Age</span>
            <span>{{ $pet->age_years ? $pet->age_years . ' yrs' : $pet->age_group }}</span>
        </div>
        <div class="profile-row">
            <span>Sex</span>
            <span>{{ $pet->sex_display }}</span>
        </div>
        <div class="profile-row">
            <span>Intake Date</span>
            <span>{{ $pet->intake_date ? $pet->intake_date->format('m/d/Y') : 'mm/dd/yyyy' }}</span>
        </div>
        <div class="profile-row">
            <span>Health Status</span>
            <span>{{ $pet->health_status }}</span>
        </div>
        <div class="profile-row">
            <span>Vaccination Records</span>
            <span>{{ $pet->vaccination_record_status ?: 'Unknown' }}</span>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <div class="profile-row items-center">
            <span>Status</span>
            <span class="badge badge-{{ $pet->adoption_status_class }} text-[11px] py-0.5 px-2.5">
                {{ $pet->status }}
            </span>
        </div>
        <div class="profile-row">
            <span>Energy Level</span>
            <span>{{ $energyVal }}</span>
        </div>
        <div class="profile-row">
            <span>Independence Level</span>
            <span>{{ $independenceVal }}</span>
        </div>
        <div class="profile-row">
            <span>Trainability</span>
            <span>{{ $trainabilityVal }}</span>
        </div>
        <div class="profile-row">
            <span>Physical Size</span>
            <span>{{ $pet->physical_size ?: 'Unknown' }}</span>
        </div>
        <div class="profile-row">
            <span>Temperament</span>
            <span>{{ $temperamentVal }}</span>
        </div>
    </div>

</div>

<!-- Modal Action Buttons -->
<div class="modal-actions mt-6 flex justify-end gap-3">
    <button type="button" class="btn btn-secondary rounded-xl px-6 py-2" onclick="closePetModal()">
        Close
    </button>
    @if (isset($match) && ! $match->eligible && $match->exclusionReason !== 'ADOPTER_PROFILE_INCOMPLETE')
        <p class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            <strong>Ineligible:</strong> {{ \App\Services\Matching\MatchPresenter::reason($match->exclusionReason) }}
        </p>
        <button type="button" class="btn btn-apply rounded-xl px-6 py-2 opacity-50 cursor-not-allowed" disabled>Ineligible to adopt</button>
    @elseif ($pet->availability_status->value === 'Available')
        @if (auth()->check() && auth()->user()->isAdopter() && ! auth()->user()->hasVerifiedEmail())
            <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                Verify your email address before you can apply to adopt a pet.
                <a href="{{ route('verification.notice') }}" class="font-bold underline">Verify email</a>
            </div>
            <button type="button" class="btn btn-apply rounded-xl px-6 py-2 opacity-50 cursor-not-allowed" disabled>Adopt Me!</button>
        @else
            <a class="btn btn-apply rounded-xl px-6 py-2" href="{{ route('application.apply', $pet) }}">
                Adopt Me!
            </a>
        @endif
    @else
        <span class="btn btn-secondary rounded-xl px-6 py-2" aria-disabled="true">
            Processing - Under Evaluation
        </span>
    @endif
</div>
