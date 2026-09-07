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

<!-- Modal Header & Center Image -->
<div class="relative flex flex-col items-center mb-4">
    <div class="w-full text-left mb-2">
        <h2 class="text-2xl font-bold font-primary text-text-dark m-0">{{ $pet->name }}</h2>
        <p class="text-base text-[#777] m-0">Pet Profile</p>
    </div>
    <div class="modal-image -mt-10 mb-4">
        <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}" class="w-[180px] h-[180px] object-cover rounded-2xl border border-[#444] shadow-sm">
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
        <div class="profile-row">
            <span>Status</span>
            <span>{{ $pet->status }}</span>
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
    @if ($pet->availability_status->value === 'Available')
        <a class="btn btn-apply rounded-xl px-6 py-2" href="{{ route('application.apply', $pet) }}">
            Adopt Me!
        </a>
    @else
        <span class="btn btn-secondary rounded-xl px-6 py-2" aria-disabled="true">
            Processing - Under Evaluation
        </span>
    @endif
</div>
