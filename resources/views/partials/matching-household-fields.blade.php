<div class="form-grid">
    @foreach ([
        'has_existing_pets' => 'Do you currently have pets at home?',
        'has_children' => 'Do children live in or regularly stay in your home?'
    ] as $field => $label)

        @php
            $selectedAnswer = old($field, $profile?->{$field});
            $selectedAnswer = is_bool($selectedAnswer) ? (int) $selectedAnswer : $selectedAnswer;
        @endphp

        <div class="form-group pt-2">
            <label for="{{ $field }}">{{ $label }}*</label>

            <div class="select-wrapper">
                <select id="{{ $field }}" name="{{ $field }}" required>
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
</div>