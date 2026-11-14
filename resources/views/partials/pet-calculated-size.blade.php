@php
    $requiresSizeReview = $sizePet?->exists && $sizePet->size_classification_status === 'legacy_unclassified';
    $sizeFieldValue = $sizePet?->hasClassifiedPhysicalSize()
        ? $sizePet->physical_size
        : ($requiresSizeReview ? 'Needs size review' : ($sizePet ? 'Not calculated' : 'Calculated on save'));
@endphp
<label class="form-label" for="{{ $sizeInputId }}">Current physical size (calculated from measured weight)</label>
<input id="{{ $sizeInputId }}" type="text" class="form-control" readonly value="{{ $sizeFieldValue }}" aria-describedby="{{ $sizeInputId }}Help">
<div id="{{ $sizeInputId }}Help">
    @include('partials.pet-size-guidance')
</div>
@if($requiresSizeReview)
    <label class="flex items-start gap-2 mt-2 text-sm text-gray-700" for="{{ $sizeInputId }}Review">
        <input id="{{ $sizeInputId }}Review" type="checkbox" name="review_current_size" value="1" @checked(old('review_current_size'))>
        <span>Verify recorded weight and calculate current size when saving.</span>
    </label>
    <p class="mt-1 text-xs text-gray-500">The original manual size remains in history.</p>
    @error('review_current_size')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
@endif
