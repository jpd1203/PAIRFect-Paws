<footer class="border-t border-gray-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="flex items-center gap-3">
                    <?php if (isset($component)) { $__componentOriginal4383542ad8210ed40ff2e701a8552b53 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4383542ad8210ed40ff2e701a8552b53 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.logo-mark','data' => ['class' => 'h-12 w-12']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('logo-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-12 w-12']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4383542ad8210ed40ff2e701a8552b53)): ?>
<?php $attributes = $__attributesOriginal4383542ad8210ed40ff2e701a8552b53; ?>
<?php unset($__attributesOriginal4383542ad8210ed40ff2e701a8552b53); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4383542ad8210ed40ff2e701a8552b53)): ?>
<?php $component = $__componentOriginal4383542ad8210ed40ff2e701a8552b53; ?>
<?php unset($__componentOriginal4383542ad8210ed40ff2e701a8552b53); ?>
<?php endif; ?>
                    <span class="text-sm font-bold text-gray-900">Red Cubs Pet Patrol</span>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold uppercase tracking-wide text-maroon-500">About</h3>
                <ul class="mt-4 space-y-2 text-sm text-gray-600">
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-maroon-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9.69 18.933a.75.75 0 00.62 0c.058-.026 3.4-1.72 5.632-3.985C17.71 13.19 19 11.185 19 8.75 19 4.858 15.882 2 12 2S5 4.858 5 8.75c0 2.435 1.29 4.44 2.958 6.198 2.231 2.264 5.574 3.96 5.632 3.986zM10 11a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" clip-rule="evenodd"/></svg>
                        7th Ave, Beverly Hills, Antipolo
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-maroon-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                        Mon&ndash;Sat: 10AM&ndash;3PM
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-maroon-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3.5A1.5 1.5 0 013.5 2h1.148a1.5 1.5 0 011.465 1.175l.716 3.223a1.5 1.5 0 01-.437 1.485L4.784 9.485a11.03 11.03 0 005.731 5.731l1.602-1.608a1.5 1.5 0 011.485-.437l3.223.716A1.5 1.5 0 0118 15.352V16.5a1.5 1.5 0 01-1.5 1.5H15c-8.284 0-15-6.716-15-15v-.5z"/></svg>
                        +63 918 985 2149
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-bold uppercase tracking-wide text-maroon-500">Services</h3>
                <ul class="mt-4 space-y-2 text-sm text-gray-600">
                    <li>Pet Rescue</li>
                    <li>Pet Adoption</li>
                    <li>Pet Sheltering</li>
                    <li>Pet Care</li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-bold uppercase tracking-wide text-maroon-500">Newsletter</h3>
                <p class="mt-3 text-xs leading-relaxed text-gray-600">
                    Your pet's health and well-being are our top priority. Stay Tuned With Our Updates.
                </p>
                <form action="#" method="POST" class="mt-3 flex flex-col gap-2" onsubmit="event.preventDefault();">
                    <input type="email" placeholder="Enter your email" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-800 focus:border-maroon-500 focus:outline-none">
                    <button type="submit" class="rounded-full bg-maroon-500 px-4 py-1.5 text-xs font-bold text-white hover:bg-maroon-600 transition">
                        Subscribe
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-10 border-t border-gray-200 pt-6 text-center text-xs text-gray-500">
            Copyright &copy; <?php echo e(now()->year); ?> PAIRfect Paws &middot; Red Cubs Pet Patrol. All Rights Reserved.
        </div>
    </div>
</footer>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/components/footer.blade.php ENDPATH**/ ?>