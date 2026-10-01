@php
    $rawText = $rawText ?? '';
    $cleanText = $rawText;
    $note = null;
    if (preg_match('/^(.*?)\s*\(([^)]+)\)$/', $rawText, $matches)) {
        $cleanText = $matches[1];
        $note = $matches[2];
    }
    $answer = old('responses.'.$sectionKey.'.'.$qKey);
    $hasAnswer = $answer !== null && $answer !== '';
@endphp

<div id="q-{{ $qKey }}" class="question-item flex scroll-mt-24 flex-col gap-3 py-3.5 sm:py-4 2xl:flex-row 2xl:items-center 2xl:gap-8"
     data-q-key="{{ $qKey }}" data-section-key="{{ $sectionKey ?? '' }}">
    
    {{-- Left: Badge + Question text + note + error prompt --}}
    <div class="flex flex-1 items-center gap-3.5">
        <span class="q-badge flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all duration-200 {{ $hasAnswer ? 'bg-[#A61D24] text-white is-answered shadow-sm' : 'bg-[#F5EFEB] text-[#7A6E6A]' }}"
              data-q-badge="{{ $qKey }}">
            <span class="q-badge-num {{ $hasAnswer ? 'hidden' : '' }}">{{ $qNumber }}</span>
            <svg class="h-4 w-4 text-white {{ $hasAnswer ? '' : 'hidden' }} q-badge-check" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </span>
        <div class="min-w-0 flex-1">
            <p id="label-{{ $qKey }}" class="text-[15px] sm:text-base font-normal text-[#231D19] leading-snug">
                {{ $cleanText }}
            </p>
            @if ($note)
                <p class="mt-0.5 text-xs text-[#9A918A]">
                    {{ $note }}
                </p>
            @endif
            <p class="q-error-msg mt-1 text-xs font-medium text-[#A61D24] hidden">
                Select a rating to continue
            </p>
        </div>
    </div>

    {{-- Right: 1-5 Segmented Rating Pill Selector --}}
    <div class="pl-10 2xl:pl-0">
        <div role="radiogroup" aria-labelledby="label-{{ $qKey }}"
             class="rating-radiogroup grid w-full shrink-0 grid-cols-5 gap-1 rounded-2xl bg-[#F5EFEB] p-1.5 sm:w-[440px] md:w-[460px]"
             data-rating-group="{{ $qKey }}">
            @for ($val = 0; $val <= 4; $val++)
                @php
                    $isSelected = $hasAnswer && (string) $answer === (string) $val;
                @endphp
                <label class="rating-pill flex cursor-pointer flex-col items-center justify-center rounded-xl py-2 px-1 transition-all duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#A61D24] {{ $isSelected ? 'is-active bg-[#A61D24] text-white shadow-sm' : 'text-[#382F2D] hover:bg-[#FDF2F2] hover:text-[#A61D24]' }}"
                       data-val="{{ $val }}">
                    <input type="radio" name="responses[{{ $sectionKey }}][{{ $qKey }}]" value="{{ $val }}"
                           {{ $isSelected ? 'checked' : '' }} class="sr-only rating-input">
                    <span class="text-sm sm:text-base font-bold leading-tight">{{ $val }}</span>
                    <span class="pill-label text-[11px] sm:text-xs leading-tight mt-0.5 {{ $isSelected ? 'text-white/95 font-medium' : 'text-[#7A6E6A] font-normal' }}">
                        {{ $labels[$val] ?? $val }}
                    </span>
                </label>
            @endfor
        </div>
    </div>
</div>
