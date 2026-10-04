@extends('admin.layouts.app')

@section('title', 'Adopter Profiles - PAIRfect Paws Admin')

@section('content')
    <div class="main-content-header">
        <div class="heading-text">
            <h2>Adopter Profiles</h2>
            <p>Review each adopter account and its complete application history.</p>
        </div>

        @include('partials.notification-bell')
    </div>

    <div class="my-5">
        <input
            type="search"
            data-search-input
            data-search-scope="profilesList"
            class="search-input w-full"
            placeholder="Search by adopter name, email, or pet..."
            aria-label="Search adopter profiles"
        >
    </div>

    <div class="profiles-container custom-scrollbar" id="profilesList">
        @forelse ($adopters as $adopter)
            @php
                $latestApplication = $adopter->adoptionApplications->first();
            @endphp

            <article
                class="profile-card"
                data-search-row
                data-adopter-profile-id="{{ $adopter->id }}"
                data-search-text="{{ $adopter->full_name }} {{ $adopter->email }} {{ $latestApplication->pet?->name }}"
            >
                <div class="profile-header">
                    <div>
                        <h3>{{ $adopter->full_name }}</h3>
                        <p>
                            Latest attempt for {{ $latestApplication->pet?->name ?? 'an unavailable pet' }}
                            &middot; {{ \App\Support\ManilaTime::format($latestApplication->created_at, 'F j, Y') }}
                            &middot; {{ $adopter->adoptionApplications->count() }}
                            {{ Str::plural('attempt', $adopter->adoptionApplications->count()) }}
                        </p>
                    </div>
                    <span class="badge {{ $latestApplication->status_badge_class }}">{{ $latestApplication->status_display }}</span>
                </div>

                <div class="profile-details">
                    <div class="detail-column">
                        <p><strong>Email:</strong> {{ $adopter->email }}</p>
                        <p><strong>Physical Activity:</strong> {{ $latestApplication->physical_activity_level ?: 'Not provided' }}</p>
                        <p><strong>Time Availability:</strong> {{ $latestApplication->time_availability ?: 'Not provided' }}</p>
                        <p><strong>Prior Experience:</strong> {{ $latestApplication->prior_pet_experience ?: 'Not provided' }}</p>
                    </div>
                    <div class="detail-column">
                        <p><strong>Housing Type:</strong> {{ $latestApplication->housing_type ?: 'Not provided' }}</p>
                        <p><strong>Household:</strong> {{ $latestApplication->household_composition ?: 'Not provided' }}</p>
                        <p><strong>Monthly Income:</strong> {{ $latestApplication->monthly_income_range ?: 'Not provided' }}</p>
                    </div>
                </div>

                <div class="profile-actions">
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        data-history-url="{{ route('admin.adopter-profiles.history', $adopter) }}"
                        aria-haspopup="dialog"
                        aria-controls="profileHistoryModal"
                        onclick="openProfileHistory(this.dataset.historyUrl, this)"
                    >
                        <i class="fa-solid fa-clock-rotate-left"></i> View Adoption Record History
                    </button>

                    @if ($latestApplication->document_path)
                        <a href="{{ route('admin.adopter-profiles.document', $latestApplication) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-file-shield"></i> View Latest Document
                        </a>
                    @else
                        <span class="text-sm text-[#888] self-center"><i class="fa-solid fa-file-circle-xmark"></i> No document on latest attempt</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state">
                <i class="fa-solid fa-user-clock"></i>
                <h3>No adopter profiles yet.</h3>
                <p>Profiles will appear here after an adopter submits an application.</p>
            </div>
        @endforelse
    </div>

    @if ($adopters->hasPages())
        <div class="mt-5">{{ $adopters->links() }}</div>
    @endif

    <div
        class="custom-modal-backdrop"
        id="profileHistoryModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profileHistoryTitle"
    >
        <div class="custom-modal adoption-history-modal" id="profileHistoryContent"></div>
    </div>

@endsection

@push('scripts')
    <script>
        let profileHistoryTrigger = null;
        let profileHistoryRequest = null;

        function openProfileHistory(url, trigger) {
            profileHistoryTrigger = trigger;
            openModal('profileHistoryModal');
            loadProfileHistory(url, true);
        }

        function switchProfileHistory(url) {
            loadProfileHistory(url, false);
        }

        async function loadProfileHistory(url, focusCloseButton) {
            const content = document.getElementById('profileHistoryContent');
            profileHistoryRequest?.abort();
            profileHistoryRequest = new AbortController();

            content.innerHTML = `
                <div class="custom-modal-header">
                    <h2 id="profileHistoryTitle">Adoption History</h2>
                </div>
                <div class="custom-modal-body py-10 text-center text-[#777]" role="status">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-3"></i>
                    <p>Loading post-adoption monitoring history...</p>
                </div>
            `;

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    signal: profileHistoryRequest.signal,
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('The adoption history request failed.');
                }

                content.innerHTML = await response.text();
                if (focusCloseButton) {
                    content.querySelector('[data-history-close]')?.focus();
                } else {
                    content.querySelector('[data-placement-switcher]')?.focus();
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                content.innerHTML = `
                    <div class="custom-modal-header"><h2 id="profileHistoryTitle">Adoption History</h2></div>
                    <div class="custom-modal-body">
                        <div class="modal-note warning">The post-adoption monitoring history could not be loaded. Please try again.</div>
                    </div>
                    <div class="custom-modal-footer-1">
                        <button type="button" class="btn btn-secondary" onclick="closeProfileHistory()">Close</button>
                    </div>
                `;
                window.PAIRfectAdmin?.showToast('Could not load post-adoption monitoring history.', 'error');
            }
        }

        function closeProfileHistory() {
            profileHistoryRequest?.abort();
            profileHistoryRequest = null;
            closeModal('profileHistoryModal');
            profileHistoryTrigger?.focus();
        }

        document.addEventListener('keydown', (event) => {
            const modal = document.getElementById('profileHistoryModal');
            if (!modal?.classList.contains('active')) {
                return;
            }

            if (event.key === 'Escape') {
                closeProfileHistory();
                return;
            }

            if (event.key === 'Tab') {
                const focusable = [...modal.querySelectorAll(
                    'button:not([disabled]), select:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )].filter((element) => element.offsetParent !== null);

                if (focusable.length === 0) {
                    event.preventDefault();
                    return;
                }

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        document.getElementById('profileHistoryModal')?.addEventListener('click', (event) => {
            if (event.target.id === 'profileHistoryModal') {
                closeProfileHistory();
            }
        });
    </script>
@endpush
