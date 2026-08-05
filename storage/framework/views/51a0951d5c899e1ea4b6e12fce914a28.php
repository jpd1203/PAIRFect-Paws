<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'accountName', 'accountNumber', 'qr', 'accent']));

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

foreach (array_filter((['name', 'accountName', 'accountNumber', 'qr', 'accent']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="rounded-xl border-2 <?php echo e($accent); ?> p-5 text-center">
    <p class="text-sm font-extrabold uppercase tracking-wide text-gray-800"><?php echo e($name); ?></p>

    <div class="mx-auto mt-3 flex aspect-square w-full max-w-[180px] items-center justify-center rounded-lg bg-white p-3 shadow-sm">
        <?php if($qr && file_exists(public_path($qr))): ?>
            <img src="<?php echo e(asset($qr)); ?>" alt="<?php echo e($name); ?> QR code" class="h-full w-full object-contain">
        <?php else: ?>
            <svg viewBox="0 0 100 100" class="h-full w-full text-gray-300" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="4" y="4" width="92" height="92" rx="6" stroke="currentColor" stroke-width="3"/>
                <rect x="14" y="14" width="20" height="20" fill="currentColor"/>
                <rect x="66" y="14" width="20" height="20" fill="currentColor"/>
                <rect x="14" y="66" width="20" height="20" fill="currentColor"/>
                <rect x="44" y="44" width="12" height="12" fill="currentColor"/>
                <rect x="60" y="44" width="8" height="8" fill="currentColor"/>
                <rect x="44" y="60" width="8" height="8" fill="currentColor"/>
                <rect x="60" y="60" width="12" height="12" fill="currentColor"/>
            </svg>
        <?php endif; ?>
    </div>

    <p class="mt-3 text-xs font-bold text-gray-800"><?php echo e($accountName); ?></p>
    <p class="text-[11px] text-gray-500"><?php echo e($accountNumber); ?></p>
</div>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/components/donation-channel-card.blade.php ENDPATH**/ ?>