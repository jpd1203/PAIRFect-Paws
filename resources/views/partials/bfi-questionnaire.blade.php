<section class="info-card shadow-card mt-5">
    <h3>Personality Questionnaire</h3>
    <p class="mb-4">How accurately does each statement describe you? Answer all 20 statements. Your answers help tailor recommendations.</p>
    <div class="form-grid">
        @foreach (config('matching.bfi.prompts') as $key => $prompt)
            <div class="form-group flex flex-col justify-between">
                <label for="bfi_{{ $key }}">{{ $loop->iteration }}. {{ $prompt }}</label>
                <select id="bfi_{{ $key }}" name="bfi_responses[{{ $key }}]" required @error('bfi_responses.'.$key) aria-invalid="true" aria-describedby="bfi_{{ $key }}_error" @enderror>
                    <option value="">Select a response</option>
                    @foreach ([1 => 'Very Inaccurate', 2 => 'Moderately Inaccurate', 3 => 'Neither Accurate nor Inaccurate', 4 => 'Moderately Accurate', 5 => 'Very Accurate'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('bfi_responses.'.$key, $profile?->bfi_responses[$key] ?? '') === (string) $value)>{{ $value }} — {{ $label }}</option>
                    @endforeach
                </select>
                @error('bfi_responses.'.$key)
                    <p id="bfi_{{ $key }}_error" class="mt-1 text-sm text-status-danger-text">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>
</section>
