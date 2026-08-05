<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['image', 'title']));

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

foreach (array_filter((['image', 'title']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="group overflow-hidden rounded-xl border-b-4 border-maroon-500 bg-white shadow-card transition hover:-translate-y-1">
    <div class="aspect-[4/3] w-full overflow-hidden">
        <img src="<?php echo e(asset($image)); ?>" alt="<?php echo e($title); ?>" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
    </div>
    <div class="p-5">
        <h3 class="text-lg font-black text-gray-900"><?php echo e($title); ?></h3>
        <p class="mt-2 text-sm leading-relaxed font-semibold text-gray-800"><?php echo e($slot); ?></p>
    </div>
</div>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/components/service-card.blade.php ENDPATH**/ ?>