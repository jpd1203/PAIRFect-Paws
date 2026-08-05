

<?php $__env->startSection('title', 'Pet Recommendation - PAIRfect Paws'); ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Pet Recommendation</h2>
            <p>Find your most compatible pet based on your lifestyle.</p>
        </div>
    </div>

    <div class="content-area flex flex-col flex-1 min-h-0 overflow-hidden">

        <div class="reco-banner mb-4 shrink-0">
            Fill in your preferred Pet Characteristics. Compatibility scores update live. All pairings are reviewed and decided manually
            by shelter staff — scores are a guide only.
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-6 items-stretch flex-1 min-h-0 pb-2">

            <!-- LEFT: Pet Characteristics sliders -->
            <div class="reco-panel flex flex-col h-[565px] max-h-[calc(100vh-185px)] overflow-hidden" id="recoPanel">
                <h3 class="font-primary text-xl mb-4 shrink-0">Pet Characteristics</h3>

                <?php
                    $sliderDefs = [
                        'energy' => [
                            'label' => 'Energy Level',
                            'desc' => [
                                1 => 'Very low energy; prefers resting',
                                2 => 'Low energy; enjoys light activity',
                                3 => 'Moderate energy; needs daily exercise',
                                4 => 'High energy; needs regular exercise',
                                5 => 'Very high energy; needs intense daily activity',
                            ],
                        ],
                        'trainability' => [
                            'label' => 'Trainability',
                            'desc' => [
                                1 => 'Learns very slowly',
                                2 => 'Needs frequent repetition',
                                3 => 'Learns with regular practice',
                                4 => 'Learns quickly with guidance',
                                5 => 'Learns new commands very quickly',
                            ],
                        ],
                        'medical' => [
                            'label' => 'Medical Needs',
                            'desc' => [
                                1 => 'Routine care only',
                                2 => 'Minor medical care needed',
                                3 => 'Regular medication or checkups',
                                4 => 'Frequent veterinary care needed',
                                5 => 'Intensive ongoing medical care',
                            ],
                        ],
                        'independence' => [
                            'label' => 'Independence Level',
                            'desc' => [
                                1 => 'Needs constant companionship',
                                2 => 'Prefers company most of the time',
                                3 => 'Can be alone for short periods',
                                4 => 'Comfortable being alone',
                                5 => 'Very independent',
                            ],
                        ],
                        'temperament' => [
                            'label' => 'Temperament',
                            'desc' => [
                                1 => 'May be aggressive or reactive',
                                2 => 'Shy or cautious',
                                3 => 'Generally friendly',
                                4 => 'Gentle and adaptable',
                                5 => 'Very calm and highly adaptable',
                            ],
                        ],
                    ];
                ?>

                <div class="flex flex-col justify-between flex-1 py-1">
                    <?php $__currentLoopData = $sliderDefs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $def): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="mb-2 last:mb-0" data-slider-group="<?php echo e($key); ?>">
                            <div class="reco-slider-label">
                                <span><?php echo e($def['label']); ?></span>
                                <span class="text-primary font-bold" data-slider-value><?php echo e($sliders[$key]); ?></span>
                            </div>
                            <input
                                type="range"
                                min="1"
                                max="5"
                                step="1"
                                value="<?php echo e($sliders[$key]); ?>"
                                class="reco-range"
                                data-slider-input="<?php echo e($key); ?>"
                            >
                            <p class="reco-slider-hint" data-slider-desc><?php echo e($def['desc'][$sliders[$key]]); ?></p>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                <script id="sliderDescriptions" type="application/json">
                    <?php echo json_encode(array_map(fn ($d) => $d['desc'], $sliderDefs)); ?>

                </script>
            </div>

            <!-- RIGHT: Compatibility results -->
            <div class="reco-panel flex flex-col h-[565px] max-h-[calc(100vh-185px)] overflow-hidden">
                <h3 class="font-primary text-xl mb-4 shrink-0">Compatibility results</h3>

                <div id="matchResults" class="flex flex-col gap-4 flex-1 overflow-y-auto pr-2">
                    <?php $__currentLoopData = $matches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php echo $__env->make('recommendation._match-card', ['pet' => $match['pet'], 'result' => $match['result'], 'matcher' => $matcher], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

        </div>

    </div>

    <!-- Modal Overlay (shared "View" pet profile modal) -->
    <div class="modal-overlay" id="petModal">
        <div class="pet-modal" id="petModalContent">
            <!-- Filled in dynamically via fetch() -->
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        window.RECO_RECOMPUTE_URL = <?php echo json_encode(route('recommendation.recompute'), 15, 512) ?>;
    </script>
    <script src="<?php echo e(asset('js/recommendation.js')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/recommendation/results.blade.php ENDPATH**/ ?>