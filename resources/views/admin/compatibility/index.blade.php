@extends('admin.layouts.app')

@section('title', 'Compatibility - PAIRfect Paws Admin')

@section('content')

<div class="heading-text">
    <h2>Compatibility</h2>
    <p>Pet Recommendation match results for applicants who used the feature.</p>
</div>

<div class="filter-bar" data-filter-bar data-filter-scope="compatList">
    <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
    <button class="filter-btn badge-approved" data-filter-btn="high">High Match</button>
    <button class="filter-btn badge-scheduled" data-filter-btn="good">Good Match</button>
    <button class="filter-btn badge-pending" data-filter-btn="fair">Fair Match</button>
    <button class="filter-btn badge-rejected" data-filter-btn="low">Low Match</button>
</div>

<div id="compatList" class="flex flex-col gap-2 mt-4">

    @forelse ($applications as $app)

        @php
            $overall = $app->compatibility_result['overall'] ?? 0;

            $tier = $overall >= 80 ? 'high'
                    : ($overall >= 60 ? 'good'
                    : ($overall >= 40 ? 'fair'
                    : 'low'));

            $tierLabel = match ($tier) {
                'high' => 'High Match',
                'good' => 'Good Match',
                'fair' => 'Fair Match',
                'low' => 'Low Match',
            };

            $tierColor = match ($tier) {
                'high' => '#295F51',
                'good' => '#2A4877',
                'fair' => '#614E34',
                'low' => '#773E47',
            };

            $tierBg = match ($tier) {
                'high' => '#E5F0EC',
                'good' => '#E8EDF5',
                'fair' => '#FAEEDA',
                'low' => '#FCEBEB',
            };
        @endphp

        <div
            class="pet-card-compat" data-filter-row data-status="{{ $tier }}" >

            {{-- CARD TOP --}}
            <div class="compat-card-top">

                {{-- PET IMAGE --}}
                <img src="{{ $app->pet?->image_url }}" alt="{{ $app->pet?->name }}" class="pet-photo-compat" >

                {{-- PET / APPLICANT INFO --}}
                <div class="compat-info">

                    <div class="pet-title"> <h3>{{ $app->pet?->name }}</h3>
                        <span>{{ $app->pet?->species }}•{{ $app->pet?->age_group }}•{{ $app->pet?->sex }}</span>
                    </div>

                    <p class="matched-user">Matched with
                        <strong>{{ $app->first_name }} {{ $app->last_name }}</strong>
                    </p>

                    <p class="applied-date"><i class="fa-regular fa-calendar"></i>
                        Applied
                        {{ \App\Support\ManilaTime::format($app->created_at, 'M d, Y') }}
                    </p>
                </div>

                {{-- SCORE --}}
                <div class="compat-score">

                    <div class="score-circle"style="--ring: {{ $tierColor }};--ring-bg: {{ $tierBg }};--score: {{ $overall }};">
                        <div class="score-value">
                            <strong>{{ $overall }}</strong>
                            <small>/100</small>
                        </div>
                    </div>

                    <span class="score-badge" style="color: {{ $tierColor }}; background: {{ $tierBg }};">
                        {{ $tierLabel }}
                    </span>
                </div>
            </div>

            {{-- CARD FOOTER --}}
            <div class="compat-card-footer">
                <button type="button" class="btn btn-primary" onclick='openBreakdown( @json($app->compatibility_result), @json("{$app->pet?->name} · {$app->first_name} {$app->last_name}"))'><i class="fa-solid fa-chart-pie"></i>
                    View Breakdown
                </button>
            </div>
        </div>

    @empty

        <div class="empty-state"><i class="fa-solid fa-circle-check"></i>
            <h3>No Compatibility Result</h3>
            <p>No applicants have used Pet Recommendation yet.</p>
        </div>

    @endforelse

</div>


{{-- BREAKDOWN MODAL --}}
<div class="custom-modal-backdrop" id="breakdownModal" >

    <div class="custom-modal">

        <div class="custom-modal-header">
            <h2>Compatibility Breakdown</h2>
            <small id="breakdownSubheading"></small>
        </div>

        <div class="custom-modal-body">
            <div id="breakdownRows" class="flex flex-col gap-3"></div>
        </div>

        <div class="custom-modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('breakdownModal')">
                Close
            </button>

        </div>

    </div>

</div>

@endsection

@push('scripts')

<script>

    function openBreakdown(result, subheading) {

        document.getElementById('breakdownSubheading').textContent = subheading;

        const host = document.getElementById('breakdownRows');

        host.innerHTML = '';

        (result?.rows || []).forEach((row) => {

            const percent = Number(row.percent) || 0;

            const fillColor =
                percent >= 80 ? '#295F51' :
                percent >= 60 ? '#2A4877' :
                percent >= 40 ? '#614E34' :
                '#773E47';

            const trackColor =
                percent >= 80 ? '#E5F0EC' :
                percent >= 60 ? '#E8EDF5' :
                percent >= 40 ? '#FAEEDA' :
                '#FCEBEB';

            host.insertAdjacentHTML('beforeend', `
                <div class="compat-row-grid">
                    <span class="compat-label">${row.label}</span>
                    <div class="compat-bar-bg" style="background: ${trackColor};">
                        <div class="compat-bar-fill-anim" style=" width: ${percent}%; background: ${fillColor};"></div>
                    </div>
                    <span class="compat-pct">${percent}%</span>
                </div>
            `);

        });

        openModal('breakdownModal');
    }

</script>

@endpush
