@php
    $bfiScale = [
        1 => ['label' => 'Very Inaccurate', 'short' => 'Very Inacc.'],
        2 => ['label' => 'Mod. Inaccurate', 'short' => 'Mod. Inacc.'],
        3 => ['label' => 'Neutral', 'short' => 'Neutral'],
        4 => ['label' => 'Mod. Accurate', 'short' => 'Mod. Acc.'],
        5 => ['label' => 'Very Accurate', 'short' => 'Very Acc.'],
    ];
@endphp

<section class="info-card shadow-card mt-6" id="personalityQuestionnaireSection">
    {{-- Header with Title & Live Progress Indicator --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="!mb-1">Personality Questionnaire</h3>
            <p class="text-sm text-ink-muted mb-0">
                How accurately does each statement describe you? Answer all 20 statements.
                Your answers help tailor pet recommendations.
            </p>
        </div>

        <div class="flex items-center gap-2 rounded-full bg-[#F5EFEB] px-3.5 py-1 text-xs font-semibold text-[#7A6E6A] shrink-0 self-start sm:self-auto shadow-2xs">
            <span class="inline-block h-2 w-2 rounded-full bg-[#A61D24]" id="bfiProgressDot"></span>
            <span><span id="bfiAnsweredCount" class="font-bold text-[#231D19]">0</span>/20 answered</span>
        </div>
    </div>

    {{-- Overall Progress Bar --}}
    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-[#F5EFEB]" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
        <div id="bfiProgressBar" class="h-full rounded-full bg-[#A61D24] transition-[width] duration-300 ease-out" style="width: 0%;"></div>
    </div>

    {{-- Rating Scale Legend Guide --}}
    <div class="mt-4 rounded-xl bg-[#F5EFEB]/60 border border-[#EBE3DE] p-3 sm:p-4 text-xs sm:text-sm text-[#5C534E]">
        <div class="flex items-center gap-2 mb-2 font-medium text-[#231D19]">
            <i class="fa-solid fa-circle-info text-[#A61D24] text-xs"></i>
            <span>Rating Scale: Select 1 to 5 with a single click</span>
        </div>
        <div class="grid grid-cols-5 gap-1.5 sm:gap-2 text-center text-xs">
            <div class="p-1.5 sm:p-2 rounded-lg bg-white border border-[#EBE3DE] shadow-2xs">
                <span class="block font-bold text-sm text-[#231D19]">1</span>
                <span class="text-[11px] text-[#7A6E6A] leading-tight">Very Inaccurate</span>
            </div>
            <div class="p-1.5 sm:p-2 rounded-lg bg-white border border-[#EBE3DE] shadow-2xs">
                <span class="block font-bold text-sm text-[#231D19]">2</span>
                <span class="text-[11px] text-[#7A6E6A] leading-tight">Moderately Inaccurate</span>
            </div>
            <div class="p-1.5 sm:p-2 rounded-lg bg-white border border-[#EBE3DE] shadow-2xs">
                <span class="block font-bold text-sm text-[#231D19]">3</span>
                <span class="text-[11px] text-[#7A6E6A] leading-tight">Neutral / Neither</span>
            </div>
            <div class="p-1.5 sm:p-2 rounded-lg bg-white border border-[#EBE3DE] shadow-2xs">
                <span class="block font-bold text-sm text-[#231D19]">4</span>
                <span class="text-[11px] text-[#7A6E6A] leading-tight">Moderately Accurate</span>
            </div>
            <div class="p-1.5 sm:p-2 rounded-lg bg-white border border-[#EBE3DE] shadow-2xs">
                <span class="block font-bold text-sm text-[#231D19]">5</span>
                <span class="text-[11px] text-[#7A6E6A] leading-tight">Very Accurate</span>
            </div>
        </div>
    </div>

    {{-- 20 Personality Questions (Two-Column Half-Half Layout) --}}
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
        {{-- Column 1: Questions 1 to 10 --}}
        <div class="flex flex-col gap-4">
            @foreach (config('matching.bfi.prompts') as $key => $prompt)
                @if ($loop->iteration <= 10)
                    @php
                        $answer = old('bfi_responses.'.$key, $profile?->bfi_responses[$key] ?? null);
                        $hasAnswer = $answer !== null && $answer !== '';
                    @endphp

                    <div id="bfi_row_{{ $key }}"
                         class="question-item bfi-question-item flex flex-col justify-between gap-3 p-3.5 sm:p-4 rounded-xl border border-[#EFE9E5] bg-white transition-all duration-150 hover:border-[#DDD3CC] hover:shadow-xs {{ $hasAnswer ? 'is-answered' : '' }}"
                         data-q-key="{{ $key }}">
                        
                        {{-- Top: Badge + Question Prompt --}}
                        <div class="flex items-start gap-3">
                            <span class="q-badge flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 mt-0.5 {{ $hasAnswer ? 'bg-[#A61D24] text-white is-answered shadow-sm' : 'bg-[#F5EFEB] text-[#7A6E6A]' }}"
                                  data-q-badge="{{ $key }}">
                                <span class="q-badge-num {{ $hasAnswer ? 'hidden' : '' }}">{{ $loop->iteration }}</span>
                                <svg class="h-4 w-4 text-white {{ $hasAnswer ? '' : 'hidden' }} q-badge-check" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <label for="bfi_{{ $key }}" id="label-{{ $key }}" class="text-[14px] sm:text-[15px] font-medium text-[#231D19] leading-snug cursor-pointer">
                                    {{ $prompt }} <span class="text-[#A61D24] font-semibold" aria-hidden="true">*</span>
                                </label>
                                @error('bfi_responses.'.$key)
                                    <p id="bfi_{{ $key }}_error" class="q-error-msg mt-1 text-xs font-medium text-[#A61D24]">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{-- Hidden Select: Exact single occurrence of name="bfi_responses[KEY]" required for backend/test compatibility --}}
                        <select id="bfi_{{ $key }}" name="bfi_responses[{{ $key }}]" required class="sr-only rating-select" tabindex="-1"
                            @error('bfi_responses.'.$key)
                                aria-invalid="true"
                                aria-describedby="bfi_{{ $key }}_error"
                            @enderror
                        >
                            <option value="">Select a response</option>
                            @foreach ([
                                1 => 'Very Inaccurate',
                                2 => 'Moderately Inaccurate',
                                3 => 'Neither Accurate nor Inaccurate',
                                4 => 'Moderately Accurate',
                                5 => 'Very Accurate'
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected((string) $answer === (string) $value)>
                                    {{ $value }} — {{ $label }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Bottom: 1-5 Segmented Rating Pill Buttons (One-Click Selection matching Admin Pet Assessment) --}}
                        <div role="radiogroup" aria-labelledby="label-{{ $key }}"
                             class="rating-radiogroup grid w-full grid-cols-5 gap-1 rounded-xl bg-[#F5EFEB] p-1.5"
                             data-rating-group="{{ $key }}">
                            @foreach ($bfiScale as $val => $meta)
                                @php
                                    $isSelected = $hasAnswer && (string) $answer === (string) $val;
                                @endphp
                                <button type="button"
                                        class="rating-pill flex cursor-pointer flex-col items-center justify-center rounded-lg py-1.5 px-0.5 transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#A61D24] {{ $isSelected ? 'is-active bg-[#A61D24] text-white shadow-sm' : 'text-[#382F2D] hover:bg-[#FDF2F2] hover:text-[#A61D24]' }}"
                                        data-val="{{ $val }}"
                                        data-target="bfi_{{ $key }}"
                                        role="radio"
                                        aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                        title="{{ $val }} — {{ $meta['label'] }}">
                                    <span class="text-sm font-bold leading-tight">{{ $val }}</span>
                                    <span class="pill-label text-[10px] leading-tight mt-0.5 text-center select-none {{ $isSelected ? 'text-white/95 font-medium' : 'text-[#7A6E6A] font-normal' }}">
                                        <span class="hidden sm:inline">{{ $meta['label'] }}</span>
                                        <span class="sm:hidden">{{ $meta['short'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Column 2: Questions 11 to 20 --}}
        <div class="flex flex-col gap-4">
            @foreach (config('matching.bfi.prompts') as $key => $prompt)
                @if ($loop->iteration > 10)
                    @php
                        $answer = old('bfi_responses.'.$key, $profile?->bfi_responses[$key] ?? null);
                        $hasAnswer = $answer !== null && $answer !== '';
                    @endphp

                    <div id="bfi_row_{{ $key }}"
                         class="question-item bfi-question-item flex flex-col justify-between gap-3 p-3.5 sm:p-4 rounded-xl border border-[#EFE9E5] bg-white transition-all duration-150 hover:border-[#DDD3CC] hover:shadow-xs {{ $hasAnswer ? 'is-answered' : '' }}"
                         data-q-key="{{ $key }}">
                        
                        {{-- Top: Badge + Question Prompt --}}
                        <div class="flex items-start gap-3">
                            <span class="q-badge flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 mt-0.5 {{ $hasAnswer ? 'bg-[#A61D24] text-white is-answered shadow-sm' : 'bg-[#F5EFEB] text-[#7A6E6A]' }}"
                                  data-q-badge="{{ $key }}">
                                <span class="q-badge-num {{ $hasAnswer ? 'hidden' : '' }}">{{ $loop->iteration }}</span>
                                <svg class="h-4 w-4 text-white {{ $hasAnswer ? '' : 'hidden' }} q-badge-check" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <label for="bfi_{{ $key }}" id="label-{{ $key }}" class="text-[14px] sm:text-[15px] font-medium text-[#231D19] leading-snug cursor-pointer">
                                    {{ $prompt }} <span class="text-[#A61D24] font-semibold" aria-hidden="true">*</span>
                                </label>
                                @error('bfi_responses.'.$key)
                                    <p id="bfi_{{ $key }}_error" class="q-error-msg mt-1 text-xs font-medium text-[#A61D24]">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{-- Hidden Select: Exact single occurrence of name="bfi_responses[KEY]" required for backend/test compatibility --}}
                        <select id="bfi_{{ $key }}" name="bfi_responses[{{ $key }}]" required class="sr-only rating-select" tabindex="-1"
                            @error('bfi_responses.'.$key)
                                aria-invalid="true"
                                aria-describedby="bfi_{{ $key }}_error"
                            @enderror
                        >
                            <option value="">Select a response</option>
                            @foreach ([
                                1 => 'Very Inaccurate',
                                2 => 'Moderately Inaccurate',
                                3 => 'Neither Accurate nor Inaccurate',
                                4 => 'Moderately Accurate',
                                5 => 'Very Accurate'
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected((string) $answer === (string) $value)>
                                    {{ $value }} — {{ $label }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Bottom: 1-5 Segmented Rating Pill Buttons (One-Click Selection matching Admin Pet Assessment) --}}
                        <div role="radiogroup" aria-labelledby="label-{{ $key }}"
                             class="rating-radiogroup grid w-full grid-cols-5 gap-1 rounded-xl bg-[#F5EFEB] p-1.5"
                             data-rating-group="{{ $key }}">
                            @foreach ($bfiScale as $val => $meta)
                                @php
                                    $isSelected = $hasAnswer && (string) $answer === (string) $val;
                                @endphp
                                <button type="button"
                                        class="rating-pill flex cursor-pointer flex-col items-center justify-center rounded-lg py-1.5 px-0.5 transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#A61D24] {{ $isSelected ? 'is-active bg-[#A61D24] text-white shadow-sm' : 'text-[#382F2D] hover:bg-[#FDF2F2] hover:text-[#A61D24]' }}"
                                        data-val="{{ $val }}"
                                        data-target="bfi_{{ $key }}"
                                        role="radio"
                                        aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                        title="{{ $val }} — {{ $meta['label'] }}">
                                    <span class="text-sm font-bold leading-tight">{{ $val }}</span>
                                    <span class="pill-label text-[10px] leading-tight mt-0.5 text-center select-none {{ $isSelected ? 'text-white/95 font-medium' : 'text-[#7A6E6A] font-normal' }}">
                                        <span class="hidden sm:inline">{{ $meta['label'] }}</span>
                                        <span class="sm:hidden">{{ $meta['short'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>

<script>
(function () {
    function initBfiQuestionnaire() {
        const section = document.getElementById('personalityQuestionnaireSection');
        if (!section) return;

        const form = section.closest('form');
        if (!form) return;

        function updateBfiProgress() {
            const items = section.querySelectorAll('.bfi-question-item');
            let answered = 0;
            const total = items.length;

            items.forEach(item => {
                const select = item.querySelector('.rating-select');
                const hasValue = select && select.value !== '';
                const badge = item.querySelector('.q-badge');
                const badgeNum = item.querySelector('.q-badge-num');
                const badgeCheck = item.querySelector('.q-badge-check');
                const errorMsg = item.querySelector('.q-error-msg');

                if (hasValue) {
                    answered++;
                    item.classList.add('is-answered');
                    if (badge) {
                        badge.className = 'q-badge flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 bg-[#A61D24] text-white is-answered shadow-sm';
                    }
                    if (badgeNum) badgeNum.classList.add('hidden');
                    if (badgeCheck) badgeCheck.classList.remove('hidden');
                    if (errorMsg) errorMsg.classList.add('hidden');
                } else {
                    item.classList.remove('is-answered');
                    if (badge) {
                        badge.className = 'q-badge flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 bg-[#F5EFEB] text-[#7A6E6A]';
                    }
                    if (badgeNum) badgeNum.classList.remove('hidden');
                    if (badgeCheck) badgeCheck.classList.add('hidden');
                }
            });

            const countEl = document.getElementById('bfiAnsweredCount');
            if (countEl) countEl.textContent = answered;

            const barEl = document.getElementById('bfiProgressBar');
            if (barEl && total > 0) {
                const pct = Math.round((answered / total) * 100);
                barEl.style.width = pct + '%';
                barEl.parentElement?.setAttribute('aria-valuenow', pct);
            }

            const dotEl = document.getElementById('bfiProgressDot');
            if (dotEl) {
                if (answered === total && total > 0) {
                    dotEl.className = 'inline-block h-2 w-2 rounded-full bg-emerald-600';
                } else {
                    dotEl.className = 'inline-block h-2 w-2 rounded-full bg-[#A61D24]';
                }
            }

            return answered;
        }

        // Handle one-click rating pill button clicks
        section.addEventListener('click', function (e) {
            const btn = e.target.closest('.rating-pill');
            if (!btn) return;

            e.preventDefault();
            const targetSelectId = btn.getAttribute('data-target');
            const val = btn.getAttribute('data-val');
            const select = document.getElementById(targetSelectId);
            if (!select) return;

            select.value = val;
            select.dispatchEvent(new Event('change', { bubbles: true }));

            // Update sibling pills inside this rating group
            const group = btn.closest('.rating-radiogroup');
            if (group) {
                group.querySelectorAll('.rating-pill').forEach(pill => {
                    const isCurrent = pill === btn;
                    pill.setAttribute('aria-checked', isCurrent ? 'true' : 'false');
                    const label = pill.querySelector('.pill-label');
                    if (isCurrent) {
                        pill.className = 'rating-pill is-active flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#A61D24] bg-[#A61D24] text-white shadow-sm';
                        if (label) {
                            label.classList.remove('text-[#7A6E6A]', 'font-normal');
                            label.classList.add('text-white/95', 'font-medium');
                        }
                    } else {
                        pill.className = 'rating-pill flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#A61D24] text-[#382F2D] hover:bg-[#FDF2F2] hover:text-[#A61D24]';
                        if (label) {
                            label.classList.remove('text-white/95', 'font-medium');
                            label.classList.add('text-[#7A6E6A]', 'font-normal');
                        }
                    }
                });
            }

            updateBfiProgress();
        });

        // Listen for change events on selects (e.g. from programmatic changes)
        form.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('rating-select')) {
                updateBfiProgress();
            }
        });

        // Run initial calculation for pre-filled or restored responses
        updateBfiProgress();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBfiQuestionnaire);
    } else {
        initBfiQuestionnaire();
    }
})();
</script>


