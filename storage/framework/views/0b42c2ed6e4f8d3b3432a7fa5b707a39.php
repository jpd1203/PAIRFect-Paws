<?php if (isset($component)) { $__componentOriginal58c831a7c3cbf004f2e66a23aed50e5b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal58c831a7c3cbf004f2e66a23aed50e5b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public-layout','data' => ['title' => config('app.name').' - Compassion Make Us Human']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(config('app.name').' - Compassion Make Us Human')]); ?>

    
    <section class="relative overflow-hidden bg-cream-100 py-14 sm:py-20">
        <!-- Background Image All Over Hero Section -->
        <div class="absolute inset-0 z-0 pointer-events-none overflow-hidden">
            <img src="<?php echo e(asset('images/hero-collage.jpg')); ?>" alt="Hero background"
                 class="h-full w-full object-cover blur-[3px] opacity-85">
        </div>

        <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl text-left">
                <p class="text-sm font-[900] uppercase tracking-widest text-maroon-500">Compassion in Action</p>
                <h1 class="mt-3 text-4xl font-[900] leading-tight text-gray-900 sm:text-5xl tracking-tight">
                    Welcome to<br>
                    <span class="text-maroon-500 font-[900]">Red Cubs Pet Patrol</span>
                </h1>

                <div class="mt-6 inline-flex items-center gap-2 rounded-full border-2 border-maroon-500 bg-white/90 backdrop-blur-sm px-4 py-2 text-sm font-black text-maroon-600 shadow-sm">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3.5A1.5 1.5 0 013.5 2h1.148a1.5 1.5 0 011.465 1.175l.716 3.223a1.5 1.5 0 01-.437 1.485L4.784 9.485a11.03 11.03 0 005.731 5.731l1.602-1.608a1.5 1.5 0 011.485-.437l3.223.716A1.5 1.5 0 0118 15.352V16.5a1.5 1.5 0 01-1.5 1.5H15c-8.284 0-15-6.716-15-15v-.5z"/></svg>
                    +63 918 985 2149
                </div>

                <h2 class="mt-8 text-2xl font-[900] text-maroon-500 sm:text-3xl tracking-tight">
                    Together, We Make Every Pet Safe &amp; Loved
                </h2>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-gray-700">
                    We rescue, protect, and care for pets in need. Together, we make every pet safe
                    and loved &mdash; find your perfect match today.
                </p>
            </div>
        </div>
    </section>

    
    <section class="bg-cream-100">
        <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="order-2 flex justify-center lg:order-1">
                <img src="<?php echo e(asset('images/cats-love.png')); ?>" alt="Cats cared for by Red Cubs Pet Patrol"
                     class="max-h-96 w-auto drop-shadow-xl">
            </div>

            <div class="order-1 lg:order-2">
                <h2 class="section-title">
                    Every Pet Deserves<br>
                    <span class="section-title-accent">Love &amp; Protection</span>
                </h2>
                <p class="mt-5 leading-relaxed text-gray-700">
                    Thousands of stray and neglected pets need our help every day. At Red Cubs Pet
                    Patrol, we believe compassion makes us human and with your support, we can
                    rescue, protect, and provide care to those who can't speak for themselves.
                </p>
                <p class="mt-4 leading-relaxed text-gray-700">
                    Your donation, big or small, gives food, shelter, and medical care to pets who
                    need a hero. Together, we can make every paw feel safe and loved.
                </p>

                <a href="<?php echo e(route('login')); ?>" class="btn-primary mt-8">
                    Adopt a Pet
                </a>
            </div>
        </div>
    </section>

    
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="section-title">
                    Every Pet Deserves<br>
                    <span class="section-title-accent">Care, Safety, and a Loving Home</span>
                </h2>
            </div>

            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php if (isset($component)) { $__componentOriginale804957ecdb153e8c822de5ed47a4ace = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale804957ecdb153e8c822de5ed47a4ace = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.service-card','data' => ['image' => 'images/service-rescue.jpg','title' => 'Rescue']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('service-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['image' => 'images/service-rescue.jpg','title' => 'Rescue']); ?>
                    We respond to pets in need&mdash;helping lost, stray, and abandoned animals find
                    safety and care within our community.
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale804957ecdb153e8c822de5ed47a4ace)): ?>
<?php $attributes = $__attributesOriginale804957ecdb153e8c822de5ed47a4ace; ?>
<?php unset($__attributesOriginale804957ecdb153e8c822de5ed47a4ace); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale804957ecdb153e8c822de5ed47a4ace)): ?>
<?php $component = $__componentOriginale804957ecdb153e8c822de5ed47a4ace; ?>
<?php unset($__componentOriginale804957ecdb153e8c822de5ed47a4ace); ?>
<?php endif; ?>

                <?php if (isset($component)) { $__componentOriginale804957ecdb153e8c822de5ed47a4ace = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale804957ecdb153e8c822de5ed47a4ace = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.service-card','data' => ['image' => 'images/service-adoption.jpg','title' => 'Adoption']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('service-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['image' => 'images/service-adoption.jpg','title' => 'Adoption']); ?>
                    Every pet deserves a loving home. We connect rescued cats and dogs with families
                    ready to give them a second chance.
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale804957ecdb153e8c822de5ed47a4ace)): ?>
<?php $attributes = $__attributesOriginale804957ecdb153e8c822de5ed47a4ace; ?>
<?php unset($__attributesOriginale804957ecdb153e8c822de5ed47a4ace); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale804957ecdb153e8c822de5ed47a4ace)): ?>
<?php $component = $__componentOriginale804957ecdb153e8c822de5ed47a4ace; ?>
<?php unset($__componentOriginale804957ecdb153e8c822de5ed47a4ace); ?>
<?php endif; ?>

                <?php if (isset($component)) { $__componentOriginale804957ecdb153e8c822de5ed47a4ace = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale804957ecdb153e8c822de5ed47a4ace = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.service-card','data' => ['image' => 'images/service-kapon.jpg','title' => 'Kapon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('service-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['image' => 'images/service-kapon.jpg','title' => 'Kapon']); ?>
                    We support responsible pet ownership through spay and neuter programs that keep
                    our community's pets healthy and safe.
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale804957ecdb153e8c822de5ed47a4ace)): ?>
<?php $attributes = $__attributesOriginale804957ecdb153e8c822de5ed47a4ace; ?>
<?php unset($__attributesOriginale804957ecdb153e8c822de5ed47a4ace); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale804957ecdb153e8c822de5ed47a4ace)): ?>
<?php $component = $__componentOriginale804957ecdb153e8c822de5ed47a4ace; ?>
<?php unset($__componentOriginale804957ecdb153e8c822de5ed47a4ace); ?>
<?php endif; ?>
            </div>
        </div>
    </section>

    
    <section class="bg-cream-100 py-14 overflow-hidden">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 items-center gap-8 text-center">

                <!-- Left: Cat Peek -->
                <div class="lg:col-span-3 flex flex-col items-center lg:items-start relative">
                    <p class="mb-2 text-xs font-bold text-gray-700 bg-white px-3 py-1 rounded-full border border-gray-200 shadow-sm inline-block">
                        800 donors have contributed!
                    </p>
                    <img src="<?php echo e(asset('images/cat-peek.png')); ?>" alt="Cat peek" class="max-h-44 w-auto object-contain">
                </div>

                <!-- Center: Donations Counter -->
                <div class="lg:col-span-6 flex flex-col items-center">
                    <h2 class="section-title text-2xl sm:text-3xl font-extrabold text-gray-900">
                        Our Pets' Community Journey
                    </h2>
                    <p class="mt-2 text-xs sm:text-sm font-semibold text-maroon-500 max-w-md">
                        Every peso helps provide medical care, food and love. Track our progress and make a difference!
                    </p>
                    <a href="<?php echo e(route('community-impact')); ?>" class="mt-6 group block cursor-pointer no-underline transition hover:scale-105">
                        <span class="text-5xl sm:text-6xl font-black tracking-tight text-gray-900 group-hover:text-maroon-500 transition">
                            <?php echo e(number_format($impactTotal ?: 600)); ?>

                        </span>
                        <p class="mt-1 text-xs sm:text-sm font-extrabold uppercase tracking-wider text-gray-700 group-hover:text-maroon-600 transition">
                            TOTAL COMMUNITY DONATIONS
                        </p>
                        <p class="text-[11px] text-gray-500 font-medium mt-0.5">Funds Record Since Launch</p>
                    </a>
                </div>

                <!-- Right: Dog Face Accent -->
                <div class="lg:col-span-3 flex justify-center lg:justify-end">
                    <img src="<?php echo e(asset('images/dog-face-accent.png')); ?>" alt="Dog accent" class="max-h-52 w-auto object-contain">
                </div>

            </div>
        </div>
    </section>

    
    <section class="bg-white">
        <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="flex justify-center">
                <img src="<?php echo e(asset('images/dogs-group.png')); ?>" alt="Dogs cared for by Red Cubs Pet Patrol"
                     class="max-h-80 w-auto drop-shadow-xl">
            </div>

            <div>
                <h2 class="section-title">
                    Where Every Visit<br>
                    Brings <span class="section-title-accent">Hope</span>
                </h2>

                <div class="mt-8 space-y-6">
                    <div>
                        <p class="text-lg font-extrabold text-maroon-500">Red Cubs Cat Shelter</p>
                        <p class="text-sm font-semibold text-gray-700">7th ave., Beverly Hills, Antipolo</p>
                    </div>
                    <div>
                        <p class="text-lg font-extrabold text-maroon-500">Red Cubs Dog Shelter</p>
                        <p class="text-sm font-semibold text-gray-700">Banha Subd., San Jose, Antipolo</p>
                    </div>
                </div>
            </div>
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
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/landing.blade.php ENDPATH**/ ?>