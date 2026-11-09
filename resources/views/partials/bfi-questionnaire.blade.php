@php
    $bfiScale = [
        1 => ['label' => 'Very Inacc.', 'full' => 'Very Inaccurate'],
        2 => ['label' => 'Mod. Inacc.', 'full' => 'Moderately Inaccurate'],
        3 => ['label' => 'Neutral', 'full' => 'Neither Accurate nor Inaccurate'],
        4 => ['label' => 'Mod. Acc.', 'full' => 'Moderately Accurate'],
        5 => ['label' => 'Very Acc.', 'full' => 'Very Accurate'],
    ];

    $dimensions = [
        'extraversion' => [
            'label' => 'Extraversion',
            'desc' => 'How energetic, talkative, and assertive you are in daily life.',
            'keys' => config('matching.bfi.dimensions.extraversion', []),
        ],
        'conscientiousness' => [
            'label' => 'Conscientiousness',
            'desc' => 'How organized, dependable, and persistent your habits and routines are.',
            'keys' => config('matching.bfi.dimensions.conscientiousness', []),
        ],
        'neuroticism' => [
            'label' => 'Emotional Stability',
            'desc' => 'How calmly, steadily, and resiliently you respond to stress or changes.',
            'keys' => config('matching.bfi.dimensions.neuroticism', []),
        ],
        'openness' => [
            'label' => 'Openness',
            'desc' => 'How curious you are about new experiences, ideas, and environments.',
            'keys' => config('matching.bfi.dimensions.openness', []),
        ],
    ];

    $prompts = config('matching.bfi.prompts', []);
    $globalIndex = 0;
@endphp

<div class="space-y-6" id="personalityQuestionnaireContainer">
    @foreach ($dimensions as $dimKey => $dim)
        <section id="section-{{ $dimKey }}" aria-labelledby="heading-{{ $dimKey }}"
                 class="scroll-mt-6 rounded-xl border border-line bg-white shadow-sm overflow-hidden bfi-section"
                 data-section="{{ $dimKey }}" data-section-total="{{ count($dim['keys']) }}">

            {{-- Section Header matching Pet Assessment Category Header --}}
            <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6 bg-white">
                <div>
                    <h2 id="heading-{{ $dimKey }}" class="text-lg font-semibold text-ink font-primary">
                        {{ $dim['label'] }}
                    </h2>
                    <p class="mt-0.5 text-sm text-ink-muted">
                        {{ $dim['desc'] }}
                    </p>
                </div>
                <span class="section-progress-badge mt-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums bg-sand text-ink-muted transition-colors duration-200"
                      data-badge-section="{{ $dimKey }}">
                    <span data-section-answered-count="{{ $dimKey }}">0</span>/{{ count($dim['keys']) }}
                </span>
            </header>

            {{-- Section Body: Clean Divided Rows matching Pet Assessment --}}
            <div class="px-5 sm:px-6 divide-y divide-line">
                @foreach ($dim['keys'] as $key)
                    @php
                        $globalIndex++;
                        $prompt = $prompts[$key] ?? '';
                        $answer = old('bfi_responses.'.$key, $profile?->bfi_responses[$key] ?? null);
                        $hasAnswer = $answer !== null && $answer !== '';
                    @endphp

                    <div id="bfi_row_{{ $key }}"
                         class="question-item bfi-question-item flex scroll-mt-24 flex-col gap-3 py-3.5 sm:py-4 xl:flex-row xl:items-center xl:gap-8 rounded-xl px-2 sm:px-2.5 -mx-2 sm:-mx-2.5 transition-all duration-200 focus-within:bg-[#FDF2F2]/40 focus-within:ring-1 focus-within:ring-[#A61D24]/30 {{ $hasAnswer ? 'is-answered' : '' }}"
                         tabindex="0"
                         data-q-key="{{ $key }}"
                         data-dimension="{{ $dimKey }}">

                        {{-- Left: Badge + Question text --}}
                        <div class="flex flex-1 items-start sm:items-center gap-3">
                            <span class="q-badge flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 mt-0.5 sm:mt-0 {{ $hasAnswer ? 'bg-[#A61D24] text-white is-answered shadow-sm' : 'bg-[#F5EFEB] text-[#7A6E6A]' }}"
                                  data-q-badge="{{ $key }}">
                                <span class="q-badge-num {{ $hasAnswer ? 'hidden' : '' }}">{{ $globalIndex }}</span>
                                <svg class="h-4 w-4 text-white {{ $hasAnswer ? '' : 'hidden' }} q-badge-check" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <label for="bfi_{{ $key }}" id="label-{{ $key }}" class="text-[15px] sm:text-base font-normal text-[#231D19] leading-snug cursor-pointer select-none">
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

                        {{-- Right: 1-5 Segmented Rating Pill Selector matching Pet Assessment exact proportions --}}
                        <div class="w-full xl:w-auto">
                            <div role="radiogroup" aria-labelledby="label-{{ $key }}"
                                 class="rating-radiogroup grid w-full grid-cols-5 gap-1 rounded-2xl bg-[#F5EFEB] p-1.5 sm:w-[440px] md:w-[460px]"
                                 data-rating-group="{{ $key }}">
                                @foreach ($bfiScale as $val => $meta)
                                    @php
                                        $isSelected = $hasAnswer && (string) $answer === (string) $val;
                                    @endphp
                                    <button type="button"
                                            class="rating-pill flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 active:scale-95 touch-manipulation focus:outline-none focus-visible:ring-2 focus-visible:ring-[#A61D24] {{ $isSelected ? 'is-active bg-[#A61D24] text-white shadow-sm' : 'text-[#382F2D] hover:bg-[#FDF2F2] hover:text-[#A61D24]' }}"
                                            data-val="{{ $val }}"
                                            data-target="bfi_{{ $key }}"
                                            role="radio"
                                            aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                            title="{{ $val }} — {{ $meta['full'] }}">
                                        <span class="text-sm sm:text-base font-bold leading-tight">{{ $val }}</span>
                                        <span class="pill-label text-[11px] sm:text-xs leading-tight mt-0.5 text-center select-none whitespace-nowrap {{ $isSelected ? 'text-white/95 font-medium' : 'text-[#7A6E6A] font-normal' }}">
                                            {{ $meta['label'] }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<script>
(function () {
    function initBfiQuestionnaire() {
        const container = document.getElementById('personalityQuestionnaireContainer');
        if (!container) return;

        const form = document.getElementById('recoForm') || container.closest('form');
        if (!form) return;

        // Reset and enforce outer window top position to prevent header cutoff
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;

        const dimTotals = {
            extraversion: 6,
            conscientiousness: 6,
            neuroticism: 4,
            openness: 4
        };

        // Smooth scroll strictly within the content scroll container, avoiding window drift
        function smoothScrollTo(targetEl) {
            if (!targetEl) return;
            const scrollHost = document.querySelector('.nonsticky-header') || window;
            if (scrollHost && scrollHost !== window) {
                const hostRect = scrollHost.getBoundingClientRect();
                const targetRect = targetEl.getBoundingClientRect();
                const targetScroll = scrollHost.scrollTop + (targetRect.top - hostRect.top) - 24;
                scrollHost.scrollTo({ top: Math.max(0, targetScroll), behavior: 'smooth' });
            } else {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function updateBfiProgress() {
            const items = container.querySelectorAll('.bfi-question-item');
            let answered = 0;
            const total = items.length;
            const dimCounts = {
                extraversion: 0,
                conscientiousness: 0,
                neuroticism: 0,
                openness: 0
            };

            items.forEach(item => {
                const select = item.querySelector('.rating-select');
                const hasValue = select && select.value !== '';
                const dim = item.getAttribute('data-dimension');
                const badge = item.querySelector('.q-badge');
                const badgeNum = item.querySelector('.q-badge-num');
                const badgeCheck = item.querySelector('.q-badge-check');
                const errorMsg = item.querySelector('.q-error-msg');

                if (hasValue) {
                    answered++;
                    if (dim && dimCounts[dim] !== undefined) {
                        dimCounts[dim]++;
                    }
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

            // Update section header badges
            for (const [dim, count] of Object.entries(dimCounts)) {
                const countSpan = document.querySelector(`[data-section-answered-count="${dim}"]`);
                if (countSpan) countSpan.textContent = count;

                const badgePill = document.querySelector(`[data-badge-section="${dim}"]`);
                if (badgePill) {
                    if (count === dimTotals[dim]) {
                        badgePill.className = 'section-progress-badge mt-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums bg-emerald-50 text-emerald-700 border border-emerald-200 transition-colors duration-200';
                    } else if (count > 0) {
                        badgePill.className = 'section-progress-badge mt-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums bg-[#FDF2F2] text-[#A61D24] border border-[#F5D0D3] transition-colors duration-200';
                    } else {
                        badgePill.className = 'section-progress-badge mt-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums bg-sand text-ink-muted transition-colors duration-200';
                    }
                }
            }

            // Update desktop sidebar count & progress bar
            const desktopCountEl = document.getElementById('desktopBfiAnsweredCount');
            if (desktopCountEl) desktopCountEl.textContent = answered;

            const desktopBarEl = document.getElementById('desktopBfiProgressBar');
            if (desktopBarEl && total > 0) {
                const pct = Math.round((answered / total) * 100);
                desktopBarEl.style.width = pct + '%';
                desktopBarEl.parentElement?.setAttribute('aria-valuenow', pct);
            }

            // Update mobile floating bar count & progress bar
            const mobileCountEl = document.getElementById('mobileBfiAnsweredCount');
            if (mobileCountEl) mobileCountEl.textContent = answered;

            const mobileBarEl = document.getElementById('mobileBfiProgressBar');
            if (mobileBarEl && total > 0) {
                const pct = Math.round((answered / total) * 100);
                mobileBarEl.style.width = pct + '%';
                mobileBarEl.parentElement?.setAttribute('aria-valuenow', pct);

                if (answered === total) {
                    mobileBarEl.className = 'h-full rounded-full bg-emerald-600 transition-all duration-300 ease-out';
                    const mobileBtn = document.getElementById('mobileStartMatchingBtn');
                    if (mobileBtn && !mobileBtn.getAttribute('data-completed-styled')) {
                        mobileBtn.setAttribute('data-completed-styled', 'true');
                        mobileBtn.classList.add('ring-2', 'ring-emerald-400');
                    }
                } else {
                    mobileBarEl.className = 'h-full rounded-full bg-[#A61D24] transition-all duration-300 ease-out';
                }
            }

            // Update sidebar dimension jump checklist
            for (const [dim, count] of Object.entries(dimCounts)) {
                const dimEl = document.querySelector(`[data-bfi-dim-count="${dim}"]`);
                if (dimEl) dimEl.textContent = count;

                const checkEl = document.querySelector(`[data-bfi-dim-check="${dim}"]`);
                if (checkEl) {
                    if (count === dimTotals[dim]) {
                        checkEl.style.display = 'inline-block';
                    } else {
                        checkEl.style.display = 'none';
                    }
                }
            }

            const stagePill = document.getElementById('sidebarStagePill');
            if (stagePill) {
                if (answered === total && total > 0) {
                    stagePill.className = 'h-1.5 w-6 rounded-full bg-emerald-600 transition-colors';
                } else if (answered > 0) {
                    stagePill.className = 'h-1.5 w-6 rounded-full bg-[#E5A8AD] transition-colors';
                } else {
                    stagePill.className = 'h-1.5 w-6 rounded-full bg-line transition-colors';
                }
            }

            return answered;
        }

        // --- Rating Selection Logic ---
        function selectRating(btn) {
            if (!btn) return;

            // Mobile haptics
            if (navigator.vibrate) {
                try { navigator.vibrate(12); } catch (_) {}
            }

            const targetSelectId = btn.getAttribute('data-target');
            const val = btn.getAttribute('data-val');
            const select = document.getElementById(targetSelectId);
            if (!select) return;

            select.value = val;
            select.dispatchEvent(new Event('change', { bubbles: true }));

            // Update sibling pills in group
            const group = btn.closest('.rating-radiogroup');
            if (group) {
                group.querySelectorAll('.rating-pill').forEach(pill => {
                    const isCurrent = pill === btn;
                    pill.setAttribute('aria-checked', isCurrent ? 'true' : 'false');
                    const label = pill.querySelector('.pill-label');
                    if (isCurrent) {
                        pill.className = 'rating-pill is-active flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 active:scale-95 touch-manipulation focus:outline-none focus-visible:ring-2 focus-visible:ring-[#A61D24] bg-[#A61D24] text-white shadow-sm';
                        if (label) {
                            label.classList.remove('text-[#7A6E6A]', 'font-normal');
                            label.classList.add('text-white/95', 'font-medium');
                        }
                    } else {
                        pill.className = 'rating-pill flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 active:scale-95 touch-manipulation focus:outline-none focus-visible:ring-2 focus-visible:ring-[#A61D24] text-[#382F2D] hover:bg-[#FDF2F2] hover:text-[#A61D24]';
                        if (label) {
                            label.classList.remove('text-white/95', 'font-medium');
                            label.classList.add('text-[#7A6E6A]', 'font-normal');
                        }
                    }
                });
            }

            updateBfiProgress();

            // Mobile / Narrow Tab Smart Auto-Nudge to next question
            if (window.innerWidth < 1024) {
                const currentRow = btn.closest('.bfi-question-item');
                if (currentRow) {
                    const allItems = Array.from(container.querySelectorAll('.bfi-question-item'));
                    const currentIndex = allItems.indexOf(currentRow);
                    const nextItem = allItems.slice(currentIndex + 1).find(item => {
                        const sel = item.querySelector('.rating-select');
                        return !sel || sel.value === '';
                    });

                    if (nextItem) {
                        setTimeout(() => {
                            smoothScrollTo(nextItem);
                        }, 180);
                    }
                }
            }
        }

        // Tap/click handler
        container.addEventListener('click', function (e) {
            const btn = e.target.closest('.rating-pill');
            if (btn) {
                e.preventDefault();
                selectRating(btn);
            }
        });

        // Sidebar Jump Links Handler (matching Pet Assessment data-jump-to)
        document.querySelectorAll('[data-jump-to]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-jump-to');
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    smoothScrollTo(targetEl);
                }
            });
        });

        // Keyboard Shortcuts (1-5 to rate focused question, Up/Down to navigate)
        container.addEventListener('keydown', function (e) {
            const activeRow = document.activeElement ? document.activeElement.closest('.bfi-question-item') : null;
            if (!activeRow) return;

            if (['1', '2', '3', '4', '5'].includes(e.key)) {
                e.preventDefault();
                const targetBtn = activeRow.querySelector(`.rating-pill[data-val="${e.key}"]`);
                if (targetBtn) {
                    selectRating(targetBtn);
                    const allItems = Array.from(container.querySelectorAll('.bfi-question-item'));
                    const nextIndex = allItems.indexOf(activeRow) + 1;
                    if (nextIndex < allItems.length) {
                        allItems[nextIndex].focus();
                    }
                }
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                const allItems = Array.from(container.querySelectorAll('.bfi-question-item'));
                const nextIndex = allItems.indexOf(activeRow) + 1;
                if (nextIndex < allItems.length) allItems[nextIndex].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const allItems = Array.from(container.querySelectorAll('.bfi-question-item'));
                const prevIndex = allItems.indexOf(activeRow) - 1;
                if (prevIndex >= 0) allItems[prevIndex].focus();
            }
        });

        // Form submit validation assist: smoothly scrolls container to first unanswered question
        if (form) {
            form.addEventListener('submit', function (e) {
                const items = Array.from(container.querySelectorAll('.bfi-question-item'));
                const firstUnanswered = items.find(item => {
                    const sel = item.querySelector('.rating-select');
                    return !sel || sel.value === '';
                });

                if (firstUnanswered) {
                    smoothScrollTo(firstUnanswered);
                    firstUnanswered.classList.add('ring-2', 'ring-[#A61D24]', 'bg-[#FDF2F2]');
                    setTimeout(() => {
                        firstUnanswered.classList.remove('ring-2', 'ring-[#A61D24]', 'bg-[#FDF2F2]');
                    }, 1800);
                }
            });
        }

        // Listen for change events on selects
        form.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('rating-select')) {
                updateBfiProgress();
            }
        });

        // Run initial calculation
        updateBfiProgress();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBfiQuestionnaire);
    } else {
        initBfiQuestionnaire();
    }
})();
</script>
