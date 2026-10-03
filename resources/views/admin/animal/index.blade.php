@extends('admin.layouts.app')

@section('title', 'Animal Records - PAIRfect Paws Admin')

@section('content')

    <div class="main-content-header">
        <div class="heading-text">
            <h2>Animal Records</h2>
            <p>Manage all shelter animal profiles</p>
        </div>

        @include('partials.notification-bell')
    </div>
    
    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                showToast(@json(session('success')), 'success');
            });
        </script>
    @endif

    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                showToast(@json($errors->first()), 'error');
            });
        </script>
    @endif

    <div class="flex flex-wrap gap-3 items-center my-5">
        <input type="text" data-search-input data-search-scope="animalTableBody"
               class="search-input flex-1 min-w-[220px]" placeholder="Search by name, species, breed…">
        <button class="btn btn-primary" onclick="openModal('addAnimalModal')">
            <i class="fa-solid fa-plus"></i> Add Animal
        </button>
    </div>

    <div class="filter-section">
        <div class="select-wrapper">
            <select id="speciesFilter">
                <option value="all">All Species</option>
                @foreach ($options::SPECIES as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
        </div>

        <div class="select-wrapper">
            <select id="ageFilter">
                <option value="all">All Ages</option>
                @foreach ($options::AGE_GROUPS as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
        </div>

        <div class="select-wrapper">
            <select id="healthFilter">
                <option value="all">All Health</option>
                @foreach ($options::HEALTH_STATUSES as $h)
                    <option value="{{ $h }}">{{ $h }}</option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
        </div>

        <div class="select-wrapper">
            <select id="statusFilter">
                <option value="all">All Status</option>
                @foreach ($options::ADOPTION_STATUSES as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
        </div>
    </div>

    <div class="records-container mt-4">
        <div class="table-responsive custom-scrollbar">
            <table class="w-full">
                <thead>
                    <tr>
                        <th>Name</th><th>Species</th><th>Breed</th><th>Age</th>
                        <th>Sex</th><th>Health</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="animalTableBody">
                    @forelse ($pets as $pet)
                        <tr data-search-row
                            data-search-text="{{ $pet->name }} {{ $pet->species_display }} {{ $pet->breed }}"
                            data-filter-row
                            data-filters="species:{{ $pet->species_display }}|age:{{ $pet->age_group }}|health:{{ $pet->health_status }}|status:{{ $pet->status }}">

                            {{-- Pet Profile --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-4">

                                    @if ($pet->photo_path)
                                        <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 border border-gray-200 bg-gray-100">
                                            <img
                                                src="{{ $pet->image_url }}"
                                                alt="{{ $pet->name }}"
                                                class="w-full h-full object-cover">
                                        </div>
                                    @else
                                        <div class="w-12 h-12 rounded-xl shrink-0 flex items-center justify-center bg-maroon-50 text-maroon-600 border border-maroon-100 text-sm">
                                            <i class="fa-solid fa-{{ strtolower($pet->species?->value ?? $pet->species) === 'cat' ? 'cat' : 'dog' }}"></i>
                                        </div>
                                    @endif

                                    <p class="font-bold text-left text-gray-900 leading-snug">
                                        {{ $pet->name }}
                                    </p>

                                </div>
                            </td>

                            <td>{{ $pet->species_display }}</td>
                            <td>{{ $pet->breed }}</td>
                            <td>{{ $pet->age_display }}</td>
                            <td>{{ $pet->sex }}</td>

                            <td>
                                <span class="badge badge-{{ $pet->health_status_class }}">
                                    {{ $pet->health_status }}
                                </span>
                            </td>

                            <td>
                                <span class="badge badge-{{ $pet->adoption_status_class }}">
                                    {{ $pet->status }}
                                </span>
                            </td>

                            <td>
                                <button
                                    class="btn btn-secondary btn-sm"
                                    onclick="openViewAnimalModal({{ $pet->id }})">
                                    <i class="fa-solid fa-eye"></i>
                                    View
                                </button>

                                @if(auth()->user()->isAdmin())
                                    @if($pet->is_archived)
                                        <form
                                            action="{{ route('admin.animals.restore', $pet) }}"
                                            method="POST"
                                            class="inline-block"
                                            onsubmit="return confirm('Restore {{ $pet->name }}? This will return them to the active catalog.')">

                                            @csrf

                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fa-solid fa-arrow-rotate-left"></i>
                                                Restore
                                            </button>
                                        </form>
                                    @else
                                        <form
                                            action="{{ route('admin.animals.archive', $pet) }}"
                                            method="POST"
                                            class="inline-block"
                                            onsubmit="return confirm('Archive {{ $pet->name }}? This will hide them from the public catalog.')">

                                            @csrf

                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fa-solid fa-box-archive"></i>
                                                Archive
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="8" class="text-[#888] py-6">
                                No animals on record yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.animal._add-modal')
    @include('admin.animal._view-modal')

    <script id="animalData" type="application/json">
        {!! $pets->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'species' => $p->species?->value ?? $p->species, 'breed' => $p->breed,
            'age_years' => $p->age_years, 'age_months' => $p->age_months, 'sex' => $p->sex,
            'intake' => optional($p->intake_date)->format('Y-m-d'), 'health' => $p->health_status,
            'status' => $p->status, 'vacc' => $p->vaccination_record_status, 'notes' => $p->behavioral_notes,
            'description' => $p->description,
            'physical_size' => $p->physical_size, 'medical_needs' => $p->medical_needs,
            'life_stage' => $p->life_stage,
            'has_aggression_history' => $p->aggression_history_verified_at === null ? null : $p->has_aggression_history,
            'high_vocalization' => $p->high_vocalization,
            'assessment_status' => $p->assessment_status,
            'assessment_count' => $p->assessment_records_count, 'version' => $p->version,
            'assessed_by_current_user' => (bool) $p->assessed_by_current_user,
            'image_url' => $p->image_url,
            'assess_url' => route('admin.assessments.create', $p),
            'update_url' => route('admin.animals.update', $p),
        ])->toJson() !!}
    </script>

@endsection

@push('scripts')
    <script>
        const rows = document.querySelectorAll('#animalTableBody [data-filter-row]');

        const filters = {
            species: document.getElementById('speciesFilter'),
            age: document.getElementById('ageFilter'),
            health: document.getElementById('healthFilter'),
            status: document.getElementById('statusFilter'),
        };

        Object.values(filters).forEach(select => {
            select.addEventListener('change', applyFilters);
        });

        function applyFilters() {
            rows.forEach(row => {
                const data = row.dataset.filters.split('|').reduce((acc, item) => {
                    const [key, value] = item.split(':');
                    acc[key] = value;
                    return acc;
                }, {});

                const visible =
                    (filters.species.value === 'all' || data.species === filters.species.value) &&
                    (filters.age.value === 'all' || data.age === filters.age.value) &&
                    (filters.health.value === 'all' || data.health === filters.health.value) &&
                    (filters.status.value === 'all' || data.status === filters.status.value);

                row.style.display = visible ? '' : 'none';
            });
        }
    </script>
    <script src="{{ asset('js/admin/animal.js') }}" defer></script>
@endpush
