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
                @if ($pet->availability_status->value === 'Soft-Reserved')
                    <p class="text-sm font-semibold text-amber-700">Processing - Under Evaluation</p>
                @endif
                <div class="card-actions">
                    <button type="button" class="btn btn-adoptMe" data-pet-id="{{ $pet->id }}" onclick="openPetModal({{ $pet->id }})">
                        {{ $pet->availability_status->value === 'Soft-Reserved' ? 'View Processing Status' : 'Adopt Me!' }}
                    </button>
                </div>
            </div>
        @endforeach

    </div>
@endif
