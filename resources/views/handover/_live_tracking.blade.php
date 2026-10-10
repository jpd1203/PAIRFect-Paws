@if ($record->released_at && $record->release_method === 'delivery' && $record->tracking_url && $record->adopter_outcome !== 'received')
    <div class="rounded-xl border border-primary/30 bg-primary-muted/20 p-4">
        <a href="{{ $record->tracking_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
            <i class="fa-solid fa-location-dot mr-1.5" aria-hidden="true"></i> Track Live Delivery
            <i class="fa-solid fa-arrow-up-right-from-square ml-1.5" aria-hidden="true"></i>
        </a>
        <p class="mt-2 mb-0 text-xs text-text-muted">Live tracking is provided by {{ $record->courier }} and opens on the courier's tracking page in a new tab.</p>
    </div>
@endif
