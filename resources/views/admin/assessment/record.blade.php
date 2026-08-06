@extends('admin.layouts.app')

@section('title', 'Assessment Record - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Assessment Record</h2>
        <p>A pet may be assessed a maximum of 3 times.</p>
    </div>

    <div class="my-5">
        <input type="text" data-search-input data-search-scope="assessmentTableBody"
               class="search-input w-full" placeholder="Search">
    </div>

    <div class="records-container custom-scrollbar">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr>
                        <th>Pet</th><th>Assessed By</th><th>Date Last Assessed</th>
                        <th>Status</th><th>Summary</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assessmentTableBody">
                    @forelse ($pets as $pet)
                        <tr data-search-row data-search-text="{{ $pet->name }} {{ $pet->species }}">
                            <td class="font-semibold">{{ $pet->name }} <span class="text-[#999] font-normal">({{ $pet->species }})</span></td>
                            <td>{{ $pet->last_assessed_by ?? '—' }}</td>
                            <td>{{ $pet->last_assessed_at?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $pet->assessment_status === 'complete' ? 'badge-completed' : 'badge-pending' }}">
                                    {{ ucfirst($pet->assessment_status) }}
                                </span>
                                <div class="text-[#999] text-[.75rem] mt-1">{{ $pet->assessment_count }}/3</div>
                            </td>
                            <td>
                                @if ($pet->assessment_status === 'complete')
                                    <button class="btn btn-secondary btn-sm" onclick="openAssessmentSummary({{ $pet->id }})">Summary</button>
                                @else
                                    <span class="text-[#bbb]">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($pet->assessment_count < 3)
                                    <a href="{{ route('admin.assessments.create', $pet) }}" class="btn btn-primary btn-sm">Assess</a>
                                @else
                                    <span class="badge badge-completed">Complete</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-[#888] py-6">No pets on record yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assessment Summary Modal -->
    <div class="custom-modal-backdrop" id="assessmentSummaryModal">
        <div class="custom-modal" id="assessmentSummaryContent">
            <!-- filled dynamically via fetch() -->
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        async function openAssessmentSummary(petId) {
            const content = document.getElementById('assessmentSummaryContent');
            try {
                const res = await fetch(`/admin/animals/${petId}/assessment-summary`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Failed to load summary');
                content.innerHTML = await res.text();
                openModal('assessmentSummaryModal');
            } catch (err) {
                window.PAIRfectAdmin?.showToast('Could not load the assessment summary.', 'error');
            }
        }
    </script>
@endpush
