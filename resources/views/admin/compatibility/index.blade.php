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

    <div id="compatList" class="flex flex-col gap-0 mt-4">
        @forelse ($applications as $app)
            @php
                $overall = $app->compatibility_result['overall'] ?? 0;
                $tier = $overall >= 80 ? 'high' : ($overall >= 60 ? 'good' : ($overall >= 40 ? 'fair' : 'low'));
                $tierLabel = ucfirst($tier) . ' Match';
                $color = $overall >= 60 ? '#295F51' : ($overall >= 40 ? '#614E34' : '#773E47');
            @endphp
            <div class="pet-card-compat" data-filter-row data-status="{{ $tier }}">
                <img src="{{ $app->pet?->image_url }}" alt="{{ $app->pet?->name }}" class="pet-photo-compat">
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start gap-3">
                        <div>
                            <strong>{{ $app->first_name }} {{ $app->last_name }}</strong>
                            <span class="text-[#888] text-[.85rem]"> applied for {{ $app->pet?->name }}</span>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="score-num" style="color: {{ $color }}">{{ $overall }}/100</div>
                            <div class="score-tag">{{ $tierLabel }}</div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-secondary btn-sm" onclick='openBreakdown(@json($app->compatibility_result), @json("{$app->first_name} {$app->last_name} · {$app->pet?->name}"))'>
                            View Breakdown
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="empty-state text-center py-16 text-[#888]">
                <i class="fa-solid fa-percent text-4xl mb-3 block text-[#c9c2b8]"></i>
                No applicants have used Pet Recommendation yet.
            </div>
        @endforelse
    </div>

    <div class="custom-modal-backdrop" id="breakdownModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2>Compatibility Breakdown</h2>
                <small id="breakdownSubheading"></small>
            </div>
            <div class="custom-modal-body">
                <div id="breakdownRows" class="flex flex-col gap-3"></div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('breakdownModal')">Close</button>
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
            (result.rows || []).forEach((row) => {
                const color = row.percent >= 60 ? '#295F51' : row.percent >= 35 ? '#614E34' : '#773E47';
                host.insertAdjacentHTML('beforeend', `
                    <div class="compat-row-grid">
                        <span class="compat-label">${row.label}</span>
                        <div class="compat-bar-bg"><div class="compat-bar-fill-anim" style="width:${row.percent}%;background:${color}"></div></div>
                        <span class="compat-pct">${row.percent}%</span>
                    </div>
                `);
            });
            openModal('breakdownModal');
        }
    </script>
@endpush
