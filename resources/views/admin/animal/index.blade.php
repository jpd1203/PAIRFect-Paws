@extends('admin.layouts.app')

@section('title', 'Animal Records - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Animal Records</h2>
        <p>Manage all shelter animal profiles</p>
    </div>

    <div class="flex flex-wrap gap-3 items-center my-3">
        <input type="text" data-search-input data-search-scope="animalTableBody"
               class="search-input flex-1 min-w-[220px]" placeholder="Search by name, species, breed…">
        <button class="btn btn-primary" onclick="openModal('addAnimalModal')">
            <i class="fa-solid fa-plus"></i> Add Animal
        </button>
    </div>

    <div class="filter-bar" data-filter-bar-multi data-filter-group="species">
        <button class="filter-btn filter-all active" data-filter-btn="all">All Species</button>
        @foreach ($options::SPECIES as $s)
            <button class="filter-btn" data-filter-btn="species:{{ $s }}">{{ $s }}</button>
        @endforeach
    </div>
    <div class="filter-bar" data-filter-bar-multi data-filter-group="age">
        <button class="filter-btn filter-all active" data-filter-btn="all">All Ages</button>
        @foreach ($options::AGE_GROUPS as $a)
            <button class="filter-btn" data-filter-btn="age:{{ $a }}">{{ $a }}</button>
        @endforeach
    </div>
    <div class="filter-bar" data-filter-bar-multi data-filter-group="health">
        <button class="filter-btn filter-all active" data-filter-btn="all">All Health</button>
        @foreach ($options::HEALTH_STATUSES as $h)
            <button class="filter-btn" data-filter-btn="health:{{ $h }}">{{ $h }}</button>
        @endforeach
    </div>
    <div class="filter-bar" data-filter-bar-multi data-filter-group="status">
        <button class="filter-btn filter-all active" data-filter-btn="all">All Status</button>
        @foreach ($options::ADOPTION_STATUSES as $s)
            <button class="filter-btn" data-filter-btn="status:{{ $s }}">{{ $s }}</button>
        @endforeach
    </div>

    <div class="records-container mt-4">
        <div class="table-responsive">
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
                            data-search-text="{{ $pet->name }} {{ $pet->species }} {{ $pet->breed }}"
                            data-filter-row
                            data-filters="species:{{ $pet->species }}|age:{{ $pet->age_group }}|health:{{ $pet->health_status }}|status:{{ $pet->status }}">
                            <td class="!text-left font-semibold">{{ $pet->name }}</td>
                            <td>{{ $pet->species }}</td>
                            <td>{{ $pet->breed }}</td>
                            <td>{{ $pet->age_display }}</td>
                            <td>{{ $pet->sex }}</td>
                            <td><span class="badge badge-{{ $pet->health_status_class }}">{{ $pet->health_status }}</span></td>
                            <td><span class="badge badge-{{ $pet->adoption_status_class }}">{{ $pet->status }}</span></td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="openViewAnimalModal({{ $pet->id }})">View</button>
                                <form action="{{ route('admin.animals.destroy', $pet) }}" method="POST" class="inline-block"
                                      onsubmit="return confirm('Delete {{ $pet->name }}? This cannot be undone.')">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-[#888] py-6">No animals on record yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.animal._add-modal')
    @include('admin.animal._view-modal')

    <script id="animalData" type="application/json">
        {!! $pets->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'species' => $p->species, 'breed' => $p->breed,
            'age_group' => $p->age_group, 'age_years' => $p->age_years, 'sex' => $p->sex,
            'intake' => optional($p->intake_date)->format('Y-m-d'), 'health' => $p->health_status,
            'status' => $p->status, 'vacc' => $p->vaccination_record_status, 'notes' => $p->notes,
            'physical_size' => $p->physical_size, 'assessment_status' => $p->assessment_status,
            'assessment_count' => $p->assessment_count,
            'assess_url' => route('admin.assessments.create', $p),
            'update_url' => route('admin.animals.update', $p),
        ])->toJson() !!}
    </script>

@endsection

@push('scripts')
    <script>
        // filter-row uses a compound data-filters attr since a pet must match
        // ALL 4 independent filter groups at once, not just the last clicked one.
        (function () {
            const rows = document.querySelectorAll('#animalTableBody [data-filter-row]');
            const active = { species: 'all', age: 'all', health: 'all', status: 'all' };

            document.querySelectorAll('[data-filter-bar-multi]').forEach((bar) => {
                bar.querySelectorAll('[data-filter-btn]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        bar.querySelectorAll('[data-filter-btn]').forEach((b) => b.classList.remove('active'));
                        btn.classList.add('active');

                        const val = btn.dataset.filterBtn;
                        const group = bar.dataset.filterGroup;
                        const value = val === 'all' ? 'all' : val.split(':')[1];
                        active[group] = value;
                        applyFilters();
                    });
                });
            });

            function applyFilters() {
                rows.forEach((row) => {
                    const filters = row.dataset.filters.split('|').reduce((acc, f) => {
                        const [k, v] = f.split(':');
                        acc[k] = v;
                        return acc;
                    }, {});
                    const match = Object.entries(active).every(([k, v]) => v === 'all' || filters[k] === v);
                    row.style.display = match ? '' : 'none';
                });
            }
        })();
    </script>
    <script src="{{ asset('js/admin/animal.js') }}" defer></script>
@endpush
