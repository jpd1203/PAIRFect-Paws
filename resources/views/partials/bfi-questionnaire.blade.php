<section class="info-card shadow-card mt-5">
    <h3>Personality Questionnaire</h3>
    <p class="mb-4">How accurately does each statement describe you? Answer all 20 statements. Your answers help tailor recommendations.</p>
    <div class="form-grid">
        @foreach (config('matching.bfi.prompts') as $key => $prompt)
            <div class="form-group flex flex-col justify-between">
                <label for="bfi_{{ $key }}">{{ $loop->iteration }}. {{ $prompt }}</label>
                <select id="bfi_{{ $key }}" name="bfi_responses[{{ $key }}]" required>
                    <option value="">Select a response</option>
                    @foreach ([1 => 'Very Inaccurate', 2 => 'Moderately Inaccurate', 3 => 'Neither Accurate nor Inaccurate', 4 => 'Moderately Accurate', 5 => 'Very Accurate'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('bfi_responses.'.$key, $profile?->bfi_responses[$key] ?? '') === (string) $value)>{{ $value }} — {{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>
</section>
