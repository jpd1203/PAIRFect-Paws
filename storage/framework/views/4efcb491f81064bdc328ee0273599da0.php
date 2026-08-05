<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['class' => 'h-16 w-16']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['class' => 'h-16 w-16']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php echo e($attributes->merge(['class' => $class.' rounded-full bg-gray-800 flex items-center justify-center overflow-hidden shrink-0'])); ?>>
    <svg viewBox="0 0 100 100" class="h-[70%] w-[70%]" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M50 20c-4 0-7 3-9 7-3-2-7-2-9 1-2 3-1 7 2 9-2 3-2 7 1 9 3 3 7 3 10 1 2 2 4 3 5 3s3-1 5-3c3 2 7 2 10-1 3-2 3-6 1-9 3-2 4-6 2-9-2-3-6-3-9-1-2-4-5-7-9-7z" fill="#fff"/>
        <circle cx="42" cy="42" r="2.2" fill="#2b2b2b"/>
        <circle cx="58" cy="42" r="2.2" fill="#2b2b2b"/>
        <path d="M46 48c1.5 1.5 6.5 1.5 8 0" stroke="#2b2b2b" stroke-width="1.5" stroke-linecap="round"/>
        <path d="M30 62c8-6 32-6 40 0 4 3 4 12-2 15-9 5-27 5-36 0-6-3-6-12-2-15z" fill="#a6242c"/>
    </svg>
</div>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/components/logo-mark.blade.php ENDPATH**/ ?>