@if ($pets->isEmpty())
    <div class="empty-state">
        <i class="fa-solid fa-paw"></i>
        <h3>No pets match these filters</h3>
        <p>Try a different species or age range.</p>
    </div>
@else
    <div class="pet-grid">

        @foreach ($pets as $pet)
            <div class="pet-card">
                <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}">
                <h3>{{ $pet->name }}</h3>
                <p>{{ $pet->card_subtitle }}</p>

                <div class="card-actions">
                    <button type="button" class="btn btn-adoptMe" data-pet-id="{{ $pet->id }}" onclick="openPetModal({{ $pet->id }})">
                        {{ $pet->availability_status->value === 'Soft-Reserved' ? 'View Processing Status' : 'Adopt Me!' }}
                    </button>
                </div>
            </div>
        @endforeach

    </div>
@endif
