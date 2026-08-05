<?php if (isset($component)) { $__componentOriginal58c831a7c3cbf004f2e66a23aed50e5b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal58c831a7c3cbf004f2e66a23aed50e5b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public-layout','data' => ['title' => 'Donate - '.config('app.name')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Donate - '.config('app.name'))]); ?>

    <section class="bg-white">
        <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">
            <h1 class="text-5xl font-extrabold tracking-tight text-gray-900">DONATE</h1>

            <h2 class="mt-8 text-xl font-extrabold text-gray-900">Why Your Donation Matters</h2>
            <p class="mt-3 leading-relaxed text-gray-700">
                Red Cubs Pet Patrol exists because animals can't ask for help on their own.
            </p>
            <p class="mt-3 leading-relaxed text-gray-700">
                Every peso donated goes toward stepping in when pets and strays are left hungry,
                hurt, or forgotten. We use your support to provide medical care, emergency response,
                and hands-on help for animals who would otherwise be ignored.
            </p>
            <p class="mt-3 leading-relaxed text-gray-700">
                Beyond rescue, your donation helps us push for better treatment of animals through
                awareness, education, and community action. Small acts of kindness add up, and
                together, they create real change for animals who need it most.
            </p>
            <p class="mt-3 font-semibold leading-relaxed text-gray-800">
                Your support doesn't just fund our work. It gives animals a second chance.
            </p>

            <hr class="my-10 border-gray-200">

            <h2 class="text-center text-2xl font-extrabold text-gray-900">Official Donation Channels</h2>

            <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <?php $__currentLoopData = $channels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if (isset($component)) { $__componentOriginal21101e182d75e114520d2586d668a172 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal21101e182d75e114520d2586d668a172 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.donation-channel-card','data' => ['name' => $channel['name'],'accountName' => $channel['account_name'],'accountNumber' => $channel['account_number'],'qr' => $channel['qr'],'accent' => $channel['accent']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('donation-channel-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($channel['name']),'account-name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($channel['account_name']),'account-number' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($channel['account_number']),'qr' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($channel['qr']),'accent' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($channel['accent'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal21101e182d75e114520d2586d668a172)): ?>
<?php $attributes = $__attributesOriginal21101e182d75e114520d2586d668a172; ?>
<?php unset($__attributesOriginal21101e182d75e114520d2586d668a172); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal21101e182d75e114520d2586d668a172)): ?>
<?php $component = $__componentOriginal21101e182d75e114520d2586d668a172; ?>
<?php unset($__componentOriginal21101e182d75e114520d2586d668a172); ?>
<?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <p class="mt-8 text-center text-xs text-gray-500">
                For bank transfer receipts or donation acknowledgements, please contact us at
                +63 918 985 2149.
            </p>
        </div>
    </section>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal58c831a7c3cbf004f2e66a23aed50e5b)): ?>
<?php $attributes = $__attributesOriginal58c831a7c3cbf004f2e66a23aed50e5b; ?>
<?php unset($__attributesOriginal58c831a7c3cbf004f2e66a23aed50e5b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal58c831a7c3cbf004f2e66a23aed50e5b)): ?>
<?php $component = $__componentOriginal58c831a7c3cbf004f2e66a23aed50e5b; ?>
<?php unset($__componentOriginal58c831a7c3cbf004f2e66a23aed50e5b); ?>
<?php endif; ?>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/donate.blade.php ENDPATH**/ ?>