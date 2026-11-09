@foreach ([
    'has_existing_pets' => 'Do you currently have pets at home?*',
    'has_children' => 'Do children live in or regularly stay in your home?*'
] as $field => $label)

    @php
        $selectedAnswer = old($field, $profile?->{$field});
        $selectedAnswer = is_bool($selectedAnswer) ? (int) $selectedAnswer : $selectedAnswer;
    @endphp

    <div class="flex flex-col">
        <label for="{{ $field }}" class="mb-1.5 text-[13px] font-semibold text-ink">{{ $label }}</label>

        <div class="select-wrapper">
            <select id="{{ $field }}" name="{{ $field }}" required class="h-[42px] border border-[#777] rounded-md px-3 text-sm bg-white focus:border-maroon-600 focus:ring-4 focus:ring-maroon-600/10 focus:outline-none w-full">
                <option value="">Select an answer</option>
                @foreach ([1 => 'Yes', 0 => 'No'] as $value => $text)
                    <option value="{{ $value }}"
                        @selected($selectedAnswer !== null && (string) $selectedAnswer === (string) $value)>
                        {{ $text }}
                    </option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down select-arrow"></i>
        </div>
    </div>

@endforeach
