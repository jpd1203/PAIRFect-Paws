@extends('layouts.app')

@section('title', 'Browse Pets - PAIRfect Paws')

@section('content')

    <div class="nonsticky-header custom-scrollbar">
        <div class="heading-text">
            <h2>Available Pets</h2>
            <p>Browse animals ready for adoption</p>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="select-wrapper">
                <select id="speciesFilter">
                    <option value="All Species" @selected($speciesFilter === 'All Species')>All Species</option>
                    <option value="Dog" @selected($speciesFilter === 'Dog')>Dog</option>
                    <option value="Cat" @selected($speciesFilter === 'Cat')>Cat</option>
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>

            <div class="select-wrapper">
                <select id="ageFilter">
                    <option value="All Ages" @selected($ageFilter === 'All Ages')>All Ages</option>
                    <option value="Baby" @selected($ageFilter === 'Baby')>Baby</option>
                    <option value="Young" @selected($ageFilter === 'Young')>Young</option>
                    <option value="Adult" @selected($ageFilter === 'Adult')>Adult</option>
                    <option value="Senior" @selected($ageFilter === 'Senior')>Senior</option>
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>

            <button type="button" id="resetFiltersBtn" class="reset-filter-btn text-sm font-semibold text-maroon-600 hover:underline flex items-center justify-center">
                <i class="fa-solid fa-rotate-left pr-1"></i> Reset Filter
            </button>
        </div>
    

        <div class="content-area-nonsticky">
            <div id="petGridContainer">
                @include('animal._pet-grid', ['pets' => $pets])
            </div>
        </div>
    </div>

    <!-- Modal Overlay -->
    <div class="modal-overlay" id="petModal">
        <div class="pet-modal" id="petModalContent">
            <!-- Filled in dynamically via fetch() -->
        </div>
    </div>
    

@endsection

@push('scripts')
    <script src="{{ asset('js/browse-pets.js') }}" defer></script>
@endpush
