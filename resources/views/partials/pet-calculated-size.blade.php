@php
    $sizeFieldValue = $sizePet?->hasClassifiedPhysicalSize()
        ? $sizePet->physical_size
        : ($sizePet ? 'Not calculated' : 'Calculated on save');
@endphp
<label class="form-label" for="{{ $sizeInputId }}">Current physical size (calculated from measured weight)</label>
<input id="{{ $sizeInputId }}" type="text" class="form-control" readonly value="{{ $sizeFieldValue }}" aria-describedby="{{ $sizeInputId }}Help">
<div id="{{ $sizeInputId }}Help">
    @include('partials.pet-size-guidance')
</div>
