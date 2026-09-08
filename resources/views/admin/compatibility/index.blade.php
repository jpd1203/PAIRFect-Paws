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
            <div class="compat-card-footer flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-primary" onclick='openBreakdown( @json($app->compatibility_result), @json("{$app->pet?->name} · {$app->first_name} {$app->last_name}"))'><i class="fa-solid fa-chart-pie"></i>
                    View Breakdown
                </button>
                <button type="button" class="btn btn-secondary" onclick="openCompatAppModal({{ $app->id }})">
                    <i class="fa-solid fa-file-lines"></i> View Application
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

{{-- DETAILED APPLICATION MODAL --}}
<div class="custom-modal-backdrop" id="compatAppModal">
    <div class="custom-modal review-modal">
        <div class="review-header">
            <div>
                <h4 id="camTitle" class="text-xl font-bold font-primary">Application Details</h4>
                <small id="camSubheading" class="text-xs text-[#777]"></small>
            </div>
            <span id="camStatusBadge" class="badge"></span>
        </div>

        <div class="custom-modal-body custom-scrollbar">
            <div class="review-section">
                <h6>Personal Information</h6>
                <div class="review-row"><span>Full Name</span><span id="camFullName" class="font-semibold"></span></div>
                <div class="review-row"><span>Contact</span><span id="camContact"></span></div>
                <div class="review-row"><span>Email</span><span id="camEmail"></span></div>
                <div class="review-row"><span>Address</span><span id="camAddress"></span></div>
            </div>

            <div class="review-section">
                <h6>Adopter Profile</h6>
                <div class="review-row"><span>Physical Activity Level</span><span id="camActivity"></span></div>
                <div class="review-row"><span>Time Availability</span><span id="camTime"></span></div>
                <div class="review-row"><span>Prior Pet Experience</span><span id="camExperience"></span></div>
                <div class="review-row"><span>Housing Type</span><span id="camHousing"></span></div>
                <div class="review-row"><span>Household Composition</span><span id="camHousehold"></span></div>
                <div class="review-row"><span>Monthly Income Range</span><span id="camIncome"></span></div>
                <div class="review-row flex-col items-start gap-1 py-2">
                    <span class="font-semibold text-text-dark">Motivation Statement</span>
                    <p id="camMotivation" class="text-sm text-[#444] bg-[#f8f6f2] border border-[#e8e3dc] p-3 rounded-md w-full whitespace-pre-line m-0 font-normal leading-relaxed"></p>
                </div>
                <div class="review-row">
                    <span>Document Upload</span>
                    <a class="btn btn-secondary btn-sm" id="camDocLink" href="#" target="_blank"><i class="fa-solid fa-file-arrow-down"></i> View Document</a>
                </div>
                <div class="review-row">
                    <span>OCR Verification</span>
                    <span>
                        <strong id="camDocStatus"></strong>
                        <a class="btn btn-secondary btn-sm ml-2" id="camVerifLink" href="#" target="_blank">Details</a>
                    </span>
                </div>
            </div>
        </div>

        <div class="custom-modal-footer flex items-center justify-between">
            <a id="camQueueLink" href="#" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in Applications
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('compatAppModal')">
                Close
            </button>
        </div>
    </div>
</div>

<script id="compatAppData" type="application/json">
    {!! $applications->keyBy('id')->map(function ($app) {
        return [
            'id' => $app->id,
            'applicant' => "{$app->first_name} {$app->last_name}",
            'email' => $app->email,
            'phone' => $app->phone_number,
            'address' => $app->address,
            'pet' => $app->pet?->name,
            'pet_info' => $app->pet ? "{$app->pet->species_display} · {$app->pet->breed} · {$app->pet->age_display}" : '',
            'submitted' => \App\Support\ManilaTime::format($app->created_at, 'F j, Y g:i A'),
            'status' => $app->status_display,
            'status_class' => $app->status_badge_class,
            'motivation' => $app->motivation_statement,
            'activity' => $app->physical_activity_level,
            'time' => $app->time_availability,
            'experience' => $app->prior_pet_experience,
            'housing' => $app->housing_type,
            'household' => $app->household_composition,
            'income' => $app->monthly_income_range,
            'queue_position' => $app->queue_position,
            'is_primary' => $app->is_primary_candidate,
            'document_url' => route('admin.applications.document', $app),
            'document_status' => $app->document_verification_status?->value ?? 'Pending',
            'verification_url' => route('admin.applications.document-verification', $app),
            'applications_url' => route('admin.applications.index', ['search' => $app->first_name]),
        ];
    })->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>

@endsection

@push('scripts')

<script>

    const COMPAT_APPS = JSON.parse(document.getElementById('compatAppData')?.textContent || '{}');

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

    function openCompatAppModal(id) {
        const app = COMPAT_APPS[id];
        if (!app) return;

        document.getElementById('camTitle').textContent = `Application — ${app.pet || 'Pet'}`;
        document.getElementById('camSubheading').textContent = `Applicant: ${app.applicant} · Submitted: ${app.submitted}`;
        
        const badge = document.getElementById('camStatusBadge');
        badge.textContent = app.status;
        badge.className = `badge ${app.status_class || 'badge-pending'}`;

        document.getElementById('camFullName').textContent = app.applicant;
        document.getElementById('camContact').textContent = app.phone || '—';
        document.getElementById('camEmail').textContent = app.email || '—';
        document.getElementById('camAddress').textContent = app.address || '—';
        document.getElementById('camActivity').textContent = app.activity || '—';
        document.getElementById('camTime').textContent = app.time || '—';
        document.getElementById('camExperience').textContent = app.experience || '—';
        document.getElementById('camHousing').textContent = app.housing || '—';
        document.getElementById('camHousehold').textContent = app.household || '—';
        document.getElementById('camIncome').textContent = app.income || '—';
        document.getElementById('camMotivation').textContent = app.motivation || 'No statement provided.';
        document.getElementById('camDocLink').href = app.document_url;
        document.getElementById('camDocStatus').textContent = app.document_status;
        document.getElementById('camVerifLink').href = app.verification_url;
        document.getElementById('camQueueLink').href = app.applications_url;

        openModal('compatAppModal');
    }

</script>

@endpush
