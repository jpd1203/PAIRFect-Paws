<?php if($pets->isEmpty()): ?>
    <div class="empty-state">
        <i class="fa-solid fa-paw"></i>
        <h3>No pets match these filters</h3>
        <p>Try a different species or age range.</p>
    </div>
<?php else: ?>
    <div class="pet-grid">

        <?php $__currentLoopData = $pets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pet): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="pet-card">
                <img src="<?php echo e($pet->image_url); ?>" alt="<?php echo e($pet->name); ?>">
                <h3><?php echo e($pet->name); ?></h3>
                <p><?php echo e($pet->card_subtitle); ?></p>
                <div class="card-actions">
                    <button type="button" class="btn btn-adoptMe" data-pet-id="<?php echo e($pet->id); ?>" onclick="openPetModal(<?php echo e($pet->id); ?>)">
                        Adopt Me!
                    </button>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    </div>
<?php endif; ?>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/animal/_pet-grid.blade.php ENDPATH**/ ?>