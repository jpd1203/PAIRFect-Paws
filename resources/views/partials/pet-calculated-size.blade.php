<label class="form-label" for="{{ $sizeInputId }}">Physical size (calculated from adult weight)</label>
<input id="{{ $sizeInputId }}" type="text" class="form-control" readonly value="{{ $sizePet?->size_display ?? 'Unavailable until species, age and weight are saved' }}" aria-describedby="{{ $sizeInputId }}Help">
<div id="{{ $sizeInputId }}Help">
    @include('partials.pet-size-guidance')
</div>
