@php
    $measurementPet = $measurementPet ?? null;
    $requireWeight = $requireWeight ?? false;
    $showSize = $showSize ?? false;
@endphp
<fieldset class="border-0 p-0 min-w-0 mt-4">
    <legend class="font-semibold text-sm mb-2">Physical measurements</legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="form-group">
            <label class="form-label" for="{{ $idPrefix }}WeightKg">Weight (kg){{ $requireWeight ? ' *' : '' }}</label>
            <input class="form-control" id="{{ $idPrefix }}WeightKg" type="number" name="weight_kg" inputmode="decimal" step="0.001" min="0.001" max="9999999.999" value="{{ old('weight_kg', $measurementPet?->weight_kg) }}" aria-describedby="{{ $idPrefix }}WeightHelp" @required($requireWeight)>
            <p id="{{ $idPrefix }}WeightHelp" class="mt-1 text-xs text-gray-500">Record the measured weight in kilograms, up to 3 decimal places. No pounds conversion. {{ $requireWeight ? 'Required for a new pet.' : 'Blank means not recorded; historical unknown weight may remain blank.' }}</p>
            @error('weight_kg')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label class="form-label" for="{{ $idPrefix }}HeightCm">Height (cm, optional)</label>
            <input class="form-control" id="{{ $idPrefix }}HeightCm" type="number" name="height_cm" inputmode="decimal" step="0.001" min="0.001" max="9999999.999" value="{{ old('height_cm', $measurementPet?->height_cm) }}" aria-describedby="{{ $idPrefix }}HeightHelp">
            <p id="{{ $idPrefix }}HeightHelp" class="mt-1 text-xs text-gray-500">Optional centimeters; leave blank if unknown. Proposed technique: standing shoulder height (shelter approval pending). Do not infer height from weight or breed.</p>
            @error('height_cm')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        @if($showSize)
            <div class="form-group sm:col-span-2">
                <label class="form-label" for="{{ $idPrefix }}PhysicalSize">Physical size (staff-entered veterinary assessment)</label>
                <select class="form-select" id="{{ $idPrefix }}PhysicalSize" name="physical_size">
                    <option value="">Not yet veterinary-assessed</option>
                    @foreach(array_keys(config('matching.size_levels')) as $size)
                        <option value="{{ $size }}" @selected(old('physical_size', $measurementPet?->physical_size) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                @include('partials.pet-size-guidance')
                @error('physical_size')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
        @endif
    </div>
</fieldset>
