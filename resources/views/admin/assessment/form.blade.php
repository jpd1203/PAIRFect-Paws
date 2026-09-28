@extends('admin.layouts.app')

@section('title', "{$animal->species_display} Pet Assessment - PAIRfect Paws Admin")

@section('content')
@php
    $speciesLower = strtolower($animal->species->value ?? 'pet');
    $sectionDescriptions = [
        'energy' => "How active and playful the {$speciesLower} is day to day.",
        'trainability' => "How readily the {$speciesLower} responds to cues and learns routines.",
        'independence' => "How much the {$speciesLower} seeks out people and their attention.",
        'temperament' => "How the {$speciesLower} reacts to unfamiliar people, objects, and sounds.",
    ];

    $ratingLabels = ['Never', 'Seldom', 'Sometimes', 'Usually', 'Always'];
    $medicalLabels = ['Routine', 'Minor', 'Moderate', 'Significant', 'Intensive'];

    // Calculate total questions count
    $totalBehaviorQuestions = 0;
    $sectionQuestionsCount = [];

    foreach ($categories as $catKey => $cat) {
        $count = 0;
        if ($cat['type'] === 'flat') {
            $count = count($cat['items'] ?? []);
        } else {
            foreach (($cat['columns'] ?? []) as $colItems) {
                $count += count($colItems);
            }
        }
        $sectionQuestionsCount[$catKey] = $count;
        $totalBehaviorQuestions += $count;
    }

    $totalAssessmentRatings = $totalBehaviorQuestions + 1; // +1 for medical_needs
@endphp

<div class="mx-auto w-full max-w-7xl px-2 pb-28 pt-2 sm:px-4 lg:pb-12 lg:pt-4" id="assessmentApp">

    {{-- Back Link --}}
    <div>
        <a href="{{ route('admin.assessments.record') }}" id="backLink"
           class="inline-flex items-center gap-2 rounded-md text-sm font-medium text-ink-muted transition-colors duration-150 hover:text-ink">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            <span>Assessment Record</span>
        </a>
    </div>

    {{-- Page Header --}}
    <header class="mt-3">
        <h1 class="font-primary text-2xl sm:text-3xl font-bold tracking-tight text-ink">Pet Assessment</h1>
        <p class="mt-1 text-sm text-ink-muted">
            {{ $animal->species_display }} behavioral characteristics — <span class="font-semibold text-ink">{{ $animal->name }}</span> · Assessment #{{ $assessmentNumber }} of 3
        </p>
        <p class="mt-3 text-sm text-ink-muted">
            Rate how often <span class="font-medium text-ink">{{ $animal->name }}</span> shows each behavior, from
            <span class="font-medium text-ink">1 Never</span> to
            <span class="font-medium text-ink">5 Always</span>.
        </p>
    </header>

    {{-- Form with Two-Column Layout --}}
    <form action="{{ route('admin.assessments.store', $animal) }}" method="POST" id="assessmentForm" class="mt-6" novalidate>
        @csrf

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

            {{-- Main Column: Question Sections & KNN Flags --}}
            <div class="space-y-6">

                @foreach ($categories as $catKey => $cat)
                    <section id="section-{{ $catKey }}" aria-labelledby="heading-{{ $catKey }}"
                             class="scroll-mt-6 rounded-xl border border-line bg-white shadow-sm overflow-hidden"
                             data-section="{{ $catKey }}" data-section-total="{{ $sectionQuestionsCount[$catKey] }}">
                        
                        {{-- Section Header --}}
                        <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6 bg-white">
                            <div>
                                <h2 id="heading-{{ $catKey }}" class="text-lg font-semibold text-ink font-primary">
                                    {{ $cat['label'] ?? ucfirst($catKey) }}
                                </h2>
                                <p class="mt-0.5 text-sm text-ink-muted">
                                    {{ $sectionDescriptions[$catKey] ?? 'Behavioral observations for this category.' }}
                                </p>
                            </div>
                            <span class="section-progress-badge mt-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums bg-sand text-ink-muted transition-colors duration-200"
                                  data-badge-section="{{ $catKey }}">
                                <span data-section-answered-count="{{ $catKey }}">0</span>/{{ $sectionQuestionsCount[$catKey] }}
                            </span>
                        </header>

                        {{-- Section Body --}}
                        <div class="px-5 sm:px-6">

                            @if ($cat['type'] === 'flat')
                                <div class="divide-y divide-line">
                                    @php $qIndex = 1; @endphp
                                    @foreach ($cat['items'] as $qKey => $text)
                                        @include('admin.assessment._question_row', [
                                            'qKey' => $qKey,
                                            'qNumber' => $qIndex++,
                                            'rawText' => $text,
                                            'labels' => $ratingLabels,
                                            'sectionKey' => $catKey,
                                        ])
                                    @endforeach
                                </div>
                            @else
                                @php $qIndex = 1; @endphp
                                @foreach (($cat['columns'] ?? []) as $colTitle => $colQuestions)
                                    <div class="{{ !$loop->first ? 'border-t border-line mt-2' : '' }}">
                                        @if ($colTitle)
                                            <h3 class="pt-5 pb-1 text-sm font-semibold text-ink">
                                                {{ $colTitle }}
                                            </h3>
                                        @endif
                                        <div class="divide-y divide-line">
                                            @foreach ($colQuestions as $qKey => $text)
                                                @include('admin.assessment._question_row', [
                                                    'qKey' => $qKey,
                                                    'qNumber' => $qIndex++,
                                                    'rawText' => $text,
                                                    'labels' => $ratingLabels,
                                                    'sectionKey' => $catKey,
                                                ])
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                        </div>
                    </section>
                @endforeach

                {{-- KNN Matching Flags & Medical Needs Section --}}
                <section id="section-flags" aria-labelledby="heading-flags"
                         class="scroll-mt-6 rounded-xl border border-line bg-white shadow-sm overflow-hidden"
                         data-section="flags" data-section-total="1">
                    
                    <header class="border-b border-line px-5 py-4 sm:px-6 bg-white">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 id="heading-flags" class="text-lg font-semibold text-ink font-primary">KNN matching flags</h2>
                                <p class="mt-0.5 text-sm text-ink-muted">These fields power the pet-recommendation algorithm. Set them based on your observations above.</p>
                            </div>
                            <span class="section-progress-badge mt-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums bg-sand text-ink-muted transition-colors duration-200"
                                  data-badge-section="flags">
                                <span data-section-answered-count="flags">0</span>/1
                            </span>
                        </div>
                    </header>

                    <div class="px-5 sm:px-6">

                        {{-- Medical Needs Level --}}
                        @php
                            $hasMedAnswer = old('medical_needs') !== null && old('medical_needs') !== '';
                        @endphp
                        <div id="q-medical_needs" class="question-item flex scroll-mt-24 flex-col gap-3 py-3.5 sm:py-4 xl:flex-row xl:items-center xl:gap-8"
                             data-q-key="medical_needs" data-section-key="flags">
                            
                            <div class="flex flex-1 items-center gap-3.5">
                                <span class="q-badge flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 {{ $hasMedAnswer ? 'bg-[#A61D24] text-white is-answered shadow-sm' : 'bg-[#F5EFEB] text-[#7A6E6A]' }}"
                                      data-q-badge="medical_needs">
                                    <span class="q-badge-num {{ $hasMedAnswer ? 'hidden' : '' }}">1</span>
                                    <svg class="h-4 w-4 text-white {{ $hasMedAnswer ? '' : 'hidden' }} q-badge-check" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p id="label-medical_needs" class="text-[15px] sm:text-base font-normal text-[#231D19] leading-snug">
                                        Medical needs level
                                    </p>
                                    <p class="mt-0.5 text-xs text-[#9A918A]">
                                        1 = Routine care only · 5 = Intensive ongoing medical care
                                    </p>
                                    <p class="q-error-msg mt-1 text-xs font-medium text-[#A61D24] hidden">
                                        Select a rating to continue
                                    </p>
                                </div>
                            </div>

                            <div class="pl-10 xl:pl-0">
                                <div role="radiogroup" aria-labelledby="label-medical_needs"
                                     class="rating-radiogroup grid w-full shrink-0 grid-cols-5 gap-1 rounded-2xl bg-[#F5EFEB] p-1.5 sm:w-[440px] md:w-[460px]"
                                     data-rating-group="medical_needs">
                                    @for ($val = 1; $val <= 5; $val++)
                                        @php
                                            $isMedChecked = old('medical_needs') == $val;
                                        @endphp
                                        <label class="rating-pill flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#A61D24] {{ $isMedChecked ? 'is-active bg-[#A61D24] text-white shadow-sm' : 'text-[#382F2D] hover:bg-[#FDF2F2] hover:text-[#A61D24]' }}"
                                               data-val="{{ $val }}">
                                            <input type="radio" name="medical_needs" value="{{ $val }}"
                                                   {{ $isMedChecked ? 'checked' : '' }} class="sr-only rating-input" required>
                                            <span class="text-sm sm:text-base font-bold leading-tight">{{ $val }}</span>
                                            <span class="pill-label text-[11px] sm:text-xs leading-tight mt-0.5 {{ $isMedChecked ? 'text-white/95 font-medium' : 'text-[#7A6E6A] font-normal' }}">{{ $medicalLabels[$val - 1] }}</span>
                                        </label>
                                    @endfor
                                </div>
                            </div>
                        </div>

                        {{-- Safety Flags --}}
                        <fieldset class="border-t border-line py-5">
                            <legend class="float-left mb-3 w-full text-sm font-semibold text-ink">
                                Safety flags <span class="font-normal text-ink-subtle">· optional</span>
                            </legend>

                            <div class="clear-both grid gap-3 md:grid-cols-2">
                                
                                {{-- Animal Reactive Flag --}}
                                @php
                                    $isReactive = (bool) old('is_reactive_to_pets');
                                @endphp
                                <label class="safety-flag-card flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#A61D24] {{ $isReactive ? 'border-red-300 bg-[#FDF2F2]' : 'border-line hover:border-[#A61D24] bg-white' }}">
                                    <input type="checkbox" name="is_reactive_to_pets" value="1" {{ $isReactive ? 'checked' : '' }}
                                           class="safety-flag-checkbox mt-0.5 h-4 w-4 shrink-0 rounded text-[#A61D24] focus:ring-[#A61D24]">
                                    <span>
                                        <span class="block text-sm font-medium text-ink">Reactive or aggressive toward other animals</span>
                                        <span class="mt-0.5 block text-xs text-ink-muted">Excludes matches with households that have other pets.</span>
                                    </span>
                                </label>

                                {{-- Human Aggression History Flag --}}
                                @php
                                    $hasAggression = (bool) old('has_aggression_history');
                                @endphp
                                <label class="safety-flag-card flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#A61D24] {{ $hasAggression ? 'border-red-300 bg-[#FDF2F2]' : 'border-line hover:border-[#A61D24] bg-white' }}">
                                    <input type="checkbox" name="has_aggression_history" value="1" {{ $hasAggression ? 'checked' : '' }}
                                           class="safety-flag-checkbox mt-0.5 h-4 w-4 shrink-0 rounded text-[#A61D24] focus:ring-[#A61D24]">
                                    <span>
                                        <span class="block text-sm font-medium text-ink">Has documented aggression or high-fear history toward people</span>
                                        <span class="mt-0.5 block text-xs text-ink-muted">Limits matches to experienced adopters without young children.</span>
                                    </span>
                                </label>

                            </div>
                        </fieldset>

                    </div>
                </section>

            </div>

            {{-- Sidebar Column: Sticky Progress Checklist & Actions --}}
            <aside class="hidden lg:block" aria-label="Assessment progress">
                <div class="sticky top-6 space-y-4">

                    {{-- Pet Overview Card --}}
                    <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            @if ($animal->photo_url)
                                <img src="{{ $animal->photo_url }}" alt="{{ $animal->name }}"
                                     class="h-12 w-12 rounded-full object-cover shrink-0 border border-line">
                            @else
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sand text-ink-muted text-xl border border-line">
                                    <i class="fa-solid {{ strtolower($animal->species->value ?? '') === 'dog' ? 'fa-dog' : 'fa-cat' }}"></i>
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-semibold text-ink font-primary">{{ $animal->name }}</p>
                                <p class="truncate text-sm text-ink-muted">
                                    {{ $animal->breed ?? 'Unknown breed' }} · {{ $animal->age_display ?? ($animal->age ? $animal->age . ' yrs' : $animal->species_display) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t border-line pt-4">
                            <span class="text-sm text-ink-muted">Assessment #{{ $assessmentNumber }} of 3</span>
                            <div class="flex items-center gap-1" role="img" aria-label="Assessment {{ $assessmentNumber }} of 3">
                                @for ($seg = 1; $seg <= 3; $seg++)
                                    @php
                                        $isSegmentDone = $seg < $assessmentNumber;
                                        $isSegmentCurrent = $seg == $assessmentNumber;
                                    @endphp
                                    <span class="h-1.5 w-6 rounded-full transition-colors {{ $isSegmentDone ? 'bg-[#A61D24]' : ($isSegmentCurrent ? 'bg-[#E5A8AD]' : 'bg-line') }}"></span>
                                @endfor
                            </div>
                        </div>
                    </div>

                    {{-- Progress Breakdown & Jump Links --}}
                    <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
                        <div class="flex items-baseline justify-between">
                            <p class="text-sm font-semibold text-ink font-primary">Progress</p>
                            <p class="text-sm tabular-nums text-ink-muted">
                                <span id="desktopAnsweredCount" class="font-semibold text-ink">0</span> of {{ $totalAssessmentRatings }}
                            </p>
                        </div>

                        {{-- Overall Progress Bar --}}
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <div id="desktopProgressBar" class="h-full rounded-full bg-[#A61D24] transition-[width] duration-300 ease-out" style="width: 0%;"></div>
                        </div>

                        {{-- Section Checklist Jump Links --}}
                        <ul class="mt-4 space-y-0.5">
                            @foreach ($categories as $catKey => $cat)
                                <li>
                                    <button type="button" data-jump-to="section-{{ $catKey }}"
                                            class="flex w-full items-center justify-between gap-3 rounded-md px-2 py-1.5 text-left text-sm transition-colors duration-150 hover:bg-[#FDF2F2] hover:text-[#A61D24] text-ink">
                                        <span class="sidebar-section-title truncate" data-sidebar-title="{{ $catKey }}">
                                            {{ $cat['label'] ?? ucfirst($catKey) }}
                                        </span>
                                        <span class="sidebar-section-status shrink-0 text-xs tabular-nums text-ink-subtle" data-sidebar-status="{{ $catKey }}">
                                            <span data-sidebar-answered="{{ $catKey }}">0</span>/{{ $sectionQuestionsCount[$catKey] }}
                                        </span>
                                        <i class="fa-solid fa-check text-xs text-ok-700 hidden" data-sidebar-check="{{ $catKey }}"></i>
                                    </button>
                                </li>
                            @endforeach

                            {{-- KNN Flags Link --}}
                            <li>
                                <button type="button" data-jump-to="section-flags"
                                        class="flex w-full items-center justify-between gap-3 rounded-md px-2 py-1.5 text-left text-sm transition-colors duration-150 hover:bg-[#FDF2F2] hover:text-[#A61D24] text-ink">
                                    <span class="sidebar-section-title truncate" data-sidebar-title="flags">
                                        Matching flags
                                    </span>
                                    <span class="sidebar-section-status shrink-0 text-xs tabular-nums text-ink-subtle" data-sidebar-status="flags">
                                        <span data-sidebar-answered="flags">0</span>/1
                                    </span>
                                    <i class="fa-solid fa-check text-xs text-ok-700 hidden" data-sidebar-check="flags"></i>
                                </button>
                            </li>
                        </ul>

                        {{-- Action Buttons --}}
                        <div class="mt-5 space-y-2">
                            <button type="submit" id="saveAssessmentBtn"
                                    class="btn-assessment-save flex w-full items-center justify-center gap-2 rounded-xl bg-[#A61D24] hover:bg-[#8D171E] px-4 py-2.5 text-sm font-semibold text-white transition-colors duration-150 shadow-sm cursor-pointer">
                                <i class="fa-solid fa-floppy-disk text-sm"></i>
                                <span>Save assessment</span>
                            </button>
                            <a href="{{ route('admin.assessments.record') }}" id="cancelBtn"
                               class="flex w-full items-center justify-center rounded-xl border border-[#A61D24] text-[#A61D24] hover:bg-[#FDF2F2] px-4 py-2 text-sm font-semibold transition-colors duration-150 cursor-pointer">
                                Cancel
                            </a>
                        </div>
                    </div>

                </div>
            </aside>

        </div>

        {{-- Mobile Fixed Bottom Bar --}}
        <div class="fixed inset-x-0 bottom-0 z-30 flex items-center justify-between gap-4 border-t border-line bg-white/95 backdrop-blur-sm px-4 py-3 lg:hidden shadow-lg">
            <p class="text-sm tabular-nums text-ink-muted">
                <span id="mobileAnsweredCount" class="font-semibold text-ink">0</span> of {{ $totalAssessmentRatings }} rated
            </p>
            <button type="submit"
                    class="btn-assessment-save flex items-center gap-2 rounded-xl bg-[#A61D24] hover:bg-[#8D171E] px-4 py-2 text-sm font-semibold text-white shadow-sm cursor-pointer">
                <i class="fa-solid fa-floppy-disk text-xs"></i>
                <span>Save assessment</span>
            </button>
        </div>

    </form>

</div>

{{-- Leave Confirmation Modal --}}
<div id="leaveConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 hidden">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl border border-line">
        <h3 class="text-lg font-bold text-ink font-primary">Leave assessment?</h3>
        <p class="mt-2 text-sm text-ink-muted">
            You have <span id="unsavedCountText" class="font-semibold text-ink">0</span> rated questions. If you leave now, your progress will not be saved.
        </p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" id="confirmStayBtn"
                    class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-sand transition-colors cursor-pointer">
                Stay and continue
            </button>
            <button type="button" id="confirmLeaveBtn"
                    class="rounded-lg bg-[#A61D24] px-4 py-2 text-sm font-medium text-white hover:bg-[#8D171E] transition-colors cursor-pointer">
                Leave anyway
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const totalRequired = {{ $totalAssessmentRatings }};
    const form = document.getElementById('assessmentForm');
    const sections = Array.from(document.querySelectorAll('[data-section]'));
    let isSubmitting = false;
    let pendingLeaveUrl = null;

    // --- Interactive State & Progress Calculations ---
    function updateProgress() {
        let totalAnswered = 0;

        sections.forEach(section => {
            const sectionKey = section.getAttribute('data-section');
            const totalForSection = parseInt(section.getAttribute('data-section-total'), 10) || 0;
            const items = section.querySelectorAll('.question-item');
            let answeredInSection = 0;

            items.forEach(item => {
                const checked = item.querySelector('.rating-input:checked');
                const badge = item.querySelector('.q-badge');
                const badgeNum = item.querySelector('.q-badge-num');
                const badgeCheck = item.querySelector('.q-badge-check');
                const errorMsg = item.querySelector('.q-error-msg');
                const radioGroup = item.querySelector('.rating-radiogroup');

                if (checked) {
                    answeredInSection++;
                    // Turn badge red with checkmark
                    if (badge) {
                        badge.className = 'q-badge flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 bg-[#A61D24] text-white is-answered shadow-sm';
                    }
                    if (badgeNum) badgeNum.classList.add('hidden');
                    if (badgeCheck) badgeCheck.classList.remove('hidden');
                    if (errorMsg) errorMsg.classList.add('hidden');
                    if (radioGroup) radioGroup.classList.remove('ring-1', 'ring-[#A61D24]');
                } else {
                    if (badge) {
                        badge.className = 'q-badge flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 bg-[#F5EFEB] text-[#7A6E6A]';
                    }
                    if (badgeNum) badgeNum.classList.remove('hidden');
                    if (badgeCheck) badgeCheck.classList.add('hidden');
                }
            });

            totalAnswered += answeredInSection;

            // Update Section Header Badge
            const sectionCountBadge = document.querySelector(`[data-badge-section="${sectionKey}"]`);
            const sectionAnsweredSpan = document.querySelector(`[data-section-answered-count="${sectionKey}"]`);
            if (sectionAnsweredSpan) {
                sectionAnsweredSpan.textContent = answeredInSection;
            }

            const isSectionDone = answeredInSection >= totalForSection && totalForSection > 0;
            if (sectionCountBadge) {
                if (isSectionDone) {
                    sectionCountBadge.className = 'section-progress-badge mt-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums bg-ok-50 text-ok-700 transition-colors duration-200';
                } else {
                    sectionCountBadge.className = 'section-progress-badge mt-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums bg-sand text-ink-muted transition-colors duration-200';
                }
            }

            // Update Sidebar Checklist
            const sidebarAnswered = document.querySelector(`[data-sidebar-answered="${sectionKey}"]`);
            const sidebarStatus = document.querySelector(`[data-sidebar-status="${sectionKey}"]`);
            const sidebarCheck = document.querySelector(`[data-sidebar-check="${sectionKey}"]`);
            const sidebarTitle = document.querySelector(`[data-sidebar-title="${sectionKey}"]`);

            if (sidebarAnswered) {
                sidebarAnswered.textContent = answeredInSection;
            }
            if (isSectionDone) {
                if (sidebarStatus) sidebarStatus.classList.add('hidden');
                if (sidebarCheck) sidebarCheck.classList.remove('hidden');
                if (sidebarTitle) sidebarTitle.classList.replace('text-ink', 'text-ink-muted');
            } else {
                if (sidebarStatus) sidebarStatus.classList.remove('hidden');
                if (sidebarCheck) sidebarCheck.classList.add('hidden');
                if (sidebarTitle) sidebarTitle.classList.replace('text-ink-muted', 'text-ink');
            }
        });

        // Overall progress percentage
        const percent = Math.min(100, Math.round((totalAnswered / totalRequired) * 100));

        // Desktop
        const desktopCount = document.getElementById('desktopAnsweredCount');
        const desktopBar = document.getElementById('desktopProgressBar');
        if (desktopCount) desktopCount.textContent = totalAnswered;
        if (desktopBar) {
            desktopBar.style.width = percent + '%';
            desktopBar.parentElement.setAttribute('aria-valuenow', percent);
        }

        // Mobile
        const mobileCount = document.getElementById('mobileAnsweredCount');
        if (mobileCount) mobileCount.textContent = totalAnswered;

        return totalAnswered;
    }

    // --- Radio Pill Styling on Change & Click ---
    form.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('rating-input')) {
            const radio = e.target;
            const group = radio.closest('.rating-radiogroup');
            if (group) {
                const pills = group.querySelectorAll('.rating-pill');
                pills.forEach(pill => {
                    const input = pill.querySelector('input');
                    const label = pill.querySelector('.pill-label');
                    if (input && input.checked) {
                        pill.className = 'rating-pill is-active flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#A61D24] bg-[#A61D24] text-white shadow-sm';
                        if (label) label.className = 'pill-label text-[11px] sm:text-xs leading-tight mt-0.5 text-white/95 font-medium';
                    } else {
                        pill.className = 'rating-pill flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#A61D24] text-[#382F2D] hover:bg-[#FDF2F2] hover:text-[#A61D24]';
                        if (label) label.className = 'pill-label text-[11px] sm:text-xs leading-tight mt-0.5 text-[#7A6E6A] font-normal';
                    }
                });
            }
            updateProgress();
        }

        // Safety flag checkboxes toggle card styling
        if (e.target && e.target.classList.contains('safety-flag-checkbox')) {
            const cb = e.target;
            const card = cb.closest('.safety-flag-card');
            if (card) {
                if (cb.checked) {
                    card.classList.remove('border-line', 'hover:border-ink-subtle', 'bg-white');
                    card.classList.add('border-brand-200', 'bg-brand-50');
                } else {
                    card.classList.remove('border-brand-200', 'bg-brand-50');
                    card.classList.add('border-line', 'hover:border-ink-subtle', 'bg-white');
                }
            }
        }
    });

    // --- Smooth Jump Links in Sidebar ---
    document.querySelectorAll('[data-jump-to]').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-jump-to');
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // --- Validation on Form Submission ---
    form.addEventListener('submit', function (e) {
        const answered = updateProgress();

        if (answered < totalRequired) {
            e.preventDefault();

            // Find first unanswered question item
            let firstUnanswered = null;
            const allItems = form.querySelectorAll('.question-item');

            allItems.forEach(item => {
                const checked = item.querySelector('.rating-input:checked');
                const errorMsg = item.querySelector('.q-error-msg');
                const radioGroup = item.querySelector('.rating-radiogroup');

                if (!checked) {
                    if (!firstUnanswered) firstUnanswered = item;
                    if (errorMsg) errorMsg.classList.remove('hidden');
                    if (radioGroup) radioGroup.classList.add('ring-1', 'ring-brand-500');
                } else {
                    if (errorMsg) errorMsg.classList.add('hidden');
                    if (radioGroup) radioGroup.classList.remove('ring-1', 'ring-brand-500');
                }
            });

            const needed = totalRequired - answered;
            const msg = `${needed} ${needed === 1 ? 'rating' : 'ratings'} still needed`;
            if (window.PAIRfectAdmin && typeof window.PAIRfectAdmin.showToast === 'function') {
                window.PAIRfectAdmin.showToast(msg, 'error');
            } else if (typeof showToast === 'function') {
                showToast(msg, 'error');
            }

            if (firstUnanswered) {
                firstUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        isSubmitting = true;
    });

    // --- Unsaved Changes Warning ---
    const leaveModal = document.getElementById('leaveConfirmModal');
    const confirmStayBtn = document.getElementById('confirmStayBtn');
    const confirmLeaveBtn = document.getElementById('confirmLeaveBtn');
    const unsavedCountText = document.getElementById('unsavedCountText');

    function handleLeaveAttempt(e, targetHref) {
        if (isSubmitting) return;
        const currentAnswered = updateProgress();
        if (currentAnswered > 0) {
            e.preventDefault();
            pendingLeaveUrl = targetHref;
            if (unsavedCountText) unsavedCountText.textContent = currentAnswered;
            if (leaveModal) leaveModal.classList.remove('hidden');
        }
    }

    const backLink = document.getElementById('backLink');
    if (backLink) {
        backLink.addEventListener('click', function (e) {
            handleLeaveAttempt(e, this.href);
        });
    }

    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function (e) {
            handleLeaveAttempt(e, this.href);
        });
    }

    if (confirmStayBtn) {
        confirmStayBtn.addEventListener('click', function () {
            if (leaveModal) leaveModal.classList.add('hidden');
            pendingLeaveUrl = null;
        });
    }

    if (confirmLeaveBtn) {
        confirmLeaveBtn.addEventListener('click', function () {
            if (pendingLeaveUrl) {
                window.location.href = pendingLeaveUrl;
            } else {
                if (leaveModal) leaveModal.classList.add('hidden');
            }
        });
    }

    // Initial calculation on load
    updateProgress();
});
</script>
@endpush
@endsection