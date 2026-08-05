@extends('admin.layouts.app')

@section('title', 'Adoption Profile - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Adoption Profile</h2>
        <p>Adopter lifestyle profiles collected from applications.</p>
    </div>

    <div class="my-5">
        <input type="text" data-search-input data-search-scope="profilesList" class="search-input w-full" placeholder="Search by applicant name…">
    </div>

    <div class="profiles-container custom-scrollbar" id="profilesList">
        @forelse ($applications as $app)
            <div class="profile-card" data-search-row data-search-text="{{ $app->first_name }} {{ $app->last_name }}">

                <div class="profile-header">
                    <div>
                        <h3>{{ $app->first_name }} {{ $app->last_name }}</h3>
                        <p>Applied for {{ $app->pet?->name }} &middot; {{ $app->created_at->format('F j, Y') }}</p>
                    </div>
                    <span class="badge {{ $app->status_badge_class }}">{{ $app->status_display }}</span>
                </div>

                <div class="profile-details">
                    <div class="detail-column">
                        <p><strong>Physical Activity:</strong> {{ $app->physical_activity_level }}</p>
                        <p><strong>Time Availability:</strong> {{ $app->time_availability }}</p>
                        <p><strong>Prior Experience:</strong> {{ $app->prior_pet_experience }}</p>
                    </div>
                    <div class="detail-column">
                        <p><strong>Housing Type:</strong> {{ $app->housing_type }}</p>
                        <p><strong>Household:</strong> {{ $app->household_composition }}</p>
                        <p><strong>Monthly Income:</strong> {{ $app->monthly_income_range }}</p>
                    </div>
                </div>

                <div class="profile-actions">
                    @if ($app->priorHistory)
                        <button type="button" class="btn btn-secondary btn-sm"
                                onclick="openProfileHistory('{{ route('admin.applications.history', $app) }}')">
                            <i class="fa-solid fa-clock-rotate-left"></i> Adoption Record History
                        </button>
                    @endif
                    <a href="{{ route('admin.adopter-profiles.document', $app) }}" target="_blank" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-file"></i> Document Upload
                    </a>
                </div>

            </div>
        @empty
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                <h3>No adopter profiles yet.</h3>
                <p>No adopter profiles have been created yet. Profiles will appear here once applications are submitted.</p>
            </div>
        @endforelse
    </div>

    <div class="custom-modal-backdrop" id="profileHistoryModal">
        <div class="custom-modal" id="profileHistoryContent"></div>
    </div>

@endsection

@push('scripts')
    <script>
        async function openProfileHistory(url) {
            const content = document.getElementById('profileHistoryContent');
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error('failed');
                content.innerHTML = (await res.text()).replaceAll('adoptionHistoryModal', 'profileHistoryModal');
                openModal('profileHistoryModal');
            } catch (err) {
                window.PAIRfectAdmin?.showToast('Could not load adoption history.', 'error');
            }
        }
    </script>
@endpush
