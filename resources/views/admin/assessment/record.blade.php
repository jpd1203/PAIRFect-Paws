@extends('admin.layouts.app')

@section('title', 'Assessment Record - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Assessment Record</h2>
        <p>Behavioral assessments for each pet. Matching requires {{ config('matching.min_observers') }} distinct observers.</p>
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
        <input type="text" data-search-input data-search-scope="assessmentTableBody"
               class="search-input flex-1 min-w-[220px]" placeholder="Search by name, species, breed…">
    </div>

    @php
        $counts = [
            'all' => $pets->count(),
            'pending' => $pets->filter(fn($p) => $p->assessment_records_count == 0)->count(),
            'inprogress' => $pets->filter(fn($p) => $p->assessment_records_count > 0 && ! $p->matching_behavior_complete)->count(),
            'complete' => $pets->filter(fn($p) => $p->matching_behavior_complete)->count(),
        ];
    @endphp

    <div class="filter-bar" data-filter-bar data-filter-scope="assessmentTableBody">
        <button type="button" class="filter-btn filter-all active" data-filter-btn="all">
            All ({{ $counts['all'] }})
        </button>
        <button type="button" class="filter-btn badge-pending" data-filter-btn="pending">
            Pending ({{ $counts['pending'] }})
        </button>
        <button type="button" class="filter-btn badge-scheduled" data-filter-btn="inprogress">
            In progress ({{ $counts['inprogress'] }})
        </button>
        <button type="button" class="filter-btn badge-approved" data-filter-btn="complete">
            Complete ({{ $counts['complete'] }})
        </button>
    </div>

    <div class="records-container mt-4">
        <div class="table-responsive custom-scrollbar">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-5 py-3">Pet</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Last Assessment</th>
                        <th class="px-5 py-3">Summary</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="assessmentTableBody">
                    @forelse ($pets as $pet)
                        @php
                            $petStatusSlug = match(true) {
                                $pet->matching_behavior_complete => 'complete',
                                $pet->assessment_records_count > 0 => 'inprogress',
                                default => 'pending',
                            };
                            $doneCount = min(3, (int) $pet->assessment_records_count);
                        @endphp
                        <tr data-search-row data-search-text="{{ $pet->name }} {{ $pet->species_display }} {{ $pet->breed }}"
                            data-filter-row data-status="{{ $petStatusSlug }}"
                            class="transition-colors hover:bg-neutral-light/50">
                            
                            <!-- 1. Pet Info with Icon/Avatar -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-4">
                                    @if ($pet->photo_path)
                                        <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 border border-gray-200 bg-gray-100">
                                            <img src="{{ $pet->image_url }}" alt="{{ $pet->name }}" class="w-full h-full object-cover">
                                        </div>
                                    @else
                                        <div class="w-12 h-12 rounded-xl shrink-0 flex items-center justify-center bg-maroon-50 text-maroon-600 border border-maroon-100 text-sm">
                                            <i class="fa-solid fa-{{ strtolower($pet->species?->value ?? $pet->species) === 'cat' ? 'cat' : 'dog' }}"></i>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-bold text-left text-gray-900 leading-snug">{{ $pet->name }}</p>
                                        <p class="text-xs text-gray-500 capitalize truncate">
                                            {{ $pet->species_display }} &middot; {{ $pet->breed ?? 'Mix' }} &middot; {{ $pet->age_display }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. Status Badge with Dot + Segmented 3-Bar Indicator -->
                            <td class="px-5 py-3.5">
                                <div class="flex flex-col items-start gap-1.5">
                                    @if ($petStatusSlug === 'complete')
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#E1F5EE] text-[#295F51] border border-[#295F51]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#295F51]"></span>
                                            Complete
                                        </span>
                                    @elseif ($petStatusSlug === 'inprogress')
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#E6F1FB] text-[#2A4877] border border-[#2A4877]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#2A4877]"></span>
                                            In progress
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#FAEEDA] text-[#614E34] border border-[#614E34]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#614E34]"></span>
                                            Pending
                                        </span>
                                    @endif

                                    <!-- 3-Segment Progress Indicator Bar -->
                                    <div class="flex items-center gap-2 mt-0.5" title="{{ $doneCount }} of 3 assessments completed">
                                        <div class="flex items-center gap-1" role="img" aria-label="{{ $doneCount }} of 3 assessments completed">
                                            @for ($i = 0; $i < 3; $i++)
                                                <span class="h-1.5 w-5 rounded-full transition-colors {{ $i < $doneCount ? 'bg-maroon-600' : 'bg-gray-200' }}"></span>
                                            @endfor
                                        </div>
                                        <span class="text-xs tabular-nums text-gray-500 font-medium">{{ $doneCount }}/3</span>
                                    </div>
                                </div>
                            </td>

                            <!-- 3. Last Assessment Details -->
                            <td class="px-5 py-3.5">
                                @if ($pet->last_assessed_at)
                                    <div>
                                        <p class="font-medium text-gray-900 text-sm">
                                            {{ \App\Support\ManilaTime::format($pet->last_assessed_at, 'M j, Y') }}
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            by {{ $pet->last_assessed_by ?? 'Staff' }}
                                        </p>
                                    </div>
                                @else
                                    <span class="text-gray-400 text-sm font-normal">Not yet assessed</span>
                                @endif
                            </td>

                            <!-- 4. Summary Button with Pop Up Modal -->
                            <td class="px-5 py-3.5">
                                @if ($pet->assessment_records_count > 0 || $pet->assessment_status === 'complete')
                                    <button type="button" class="btn btn-sm whitespace-nowrap inline-flex items-center gap-1.5 border border-[#A61D24] text-[#A61D24] hover:bg-[#A61D24] hover:text-white transition-colors cursor-pointer" onclick="openAssessmentSummary({{ $pet->id }})">
                                        <i class="fa-solid fa-clipboard-list"></i>
                                        <span>Summary</span>
                                    </button>
                                @else
                                    <span class="text-gray-300 font-medium">—</span>
                                @endif
                            </td>

                            <!-- 5. Actions Button -->
                            <td class="px-5 py-3.5 text-right">
                                @if ($pet->matching_behavior_complete)
                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#E1F5EE] text-[#295F51] border border-[#295F51]" aria-label="Assessment complete">
                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                        Complete
                                    </span>
                                @elseif ($pet->assessed_by_current_user)
                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold bg-[#E1F5EE] text-[#295F51] border border-[#295F51]" aria-label="You already assessed this pet">
                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                        Your assessment complete
                                    </span>
                                @else
                                    <a href="{{ route('admin.assessments.create', $pet) }}"
                                       class="btn btn-primary btn-sm whitespace-nowrap inline-flex items-center gap-1.5">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        <span>Assess</span>
                                        <span class="font-normal text-white/80">#{{ min(config('matching.min_observers'), $pet->assessment_records_count + 1) }}</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-10 text-gray-500">
                                <p class="font-medium text-gray-900">No pets on record yet.</p>
                                <p class="text-xs text-gray-400 mt-1">Animals added to the shelter will appear here for assessment.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assessment Summary Modal -->
    <div class="custom-modal-backdrop" id="assessmentSummaryModal">
        <div class="custom-modal custom-modal-wide" id="assessmentSummaryContent">
            <!-- filled dynamically via fetch() -->
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        async function openAssessmentSummary(petId) {
            const content = document.getElementById('assessmentSummaryContent');
            try {
                content.innerHTML = `
                    <div class="p-8 text-center text-gray-500">
                        <i class="fa-solid fa-spinner fa-spin text-2xl text-maroon-600 mb-2"></i>
                        <p class="text-sm">Loading assessment summary…</p>
                    </div>
                `;
                openModal('assessmentSummaryModal');

                const res = await fetch(`/admin/animals/${petId}/assessment-summary`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Failed to load summary');
                content.innerHTML = await res.text();
            } catch (err) {
                closeModal('assessmentSummaryModal');
                window.PAIRfectAdmin?.showToast('Could not load the assessment summary.', 'error');
            }
        }
    </script>
@endpush
