<div class="modal-header">
    <div>
        <h2>{{ $pet->name }}</h2>
        <p>Pet Profile</p>
    </div>
</div>

<div class="modal-image">
    <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}">
</div>

<div class="profile-table">

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
        <span>{{ $pet->age_display }}</span>
    </div>

    <div class="profile-row">
        <span>Sex</span>
        <span>{{ $pet->sex_display }}</span>
    </div>

    <div class="profile-row">
        <span>Intake Date</span>
        <span>{{ $pet->intake_date->format('m/d/Y') }}</span>
    </div>

    <div class="profile-row">
        <span>Health Status</span>
        <span>{{ $pet->health_status }}</span>
    </div>

    <div class="profile-row">
        <span>Vaccination Records</span>
        <span>{{ $pet->vaccination_records }}</span>
    </div>

    <div class="profile-row">
        <span>Status</span>
        <span>{{ $pet->status }}</span>
    </div>

    {{-- Added per the Pet Recommendation profile spec --}}
    <div class="profile-row">
        <span>Energy Level</span>
        <span>{{ $pet->energy_level }}/5</span>
    </div>

    <div class="profile-row">
        <span>Independence Level</span>
        <span>{{ $pet->independence_level }}/5</span>
    </div>

    <div class="profile-row">
        <span>Trainability</span>
        <span>{{ $pet->trainability }}/5</span>
    </div>

    <div class="profile-row">
        <span>Physical Size</span>
        <span>{{ $pet->physical_size }}</span>
    </div>

    <div class="profile-row">
        <span>Temperament</span>
        <span>{{ $pet->temperament }}/5</span>
    </div>

</div>

<div class="modal-actions">

    <button type="button" class="btn btn-secondary" onclick="closePetModal()">
        Close
    </button>

    <a class="btn btn-apply" href="{{ route('application.apply', $pet) }}">
        Apply for {{ $pet->name }}
    </a>

</div>
