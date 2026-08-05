<div class="match-card" data-pet-card="<?php echo e($pet->id); ?>">

    <img src="<?php echo e($pet->image_url); ?>" alt="<?php echo e($pet->name); ?>">

    <div class="flex-1 min-w-0">

        <div class="flex items-center justify-between gap-3">
            <div>
                <strong class="font-primary text-base"><?php echo e($pet->name); ?></strong>
                <span class="text-[.78rem] text-[#888] ml-1"><?php echo e($pet->species); ?> &middot; <?php echo e($pet->age_group); ?> &middot; <?php echo e($pet->sex); ?></span>
            </div>
        </div>

        <div class="flex flex-col gap-1.5 mt-2.5" data-rows>
            <?php $__currentLoopData = $result['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="flex items-center gap-2.5">
                    <span class="compat-row-label"><?php echo e($row['label']); ?></span>
                    <div class="compat-bar-track">
                        <div class="compat-bar-fill" style="width:<?php echo e($row['percent']); ?>%; background:<?php echo e($row['percent'] >= 60 ? '#295F51' : ($row['percent'] >= 35 ? '#614E34' : '#773E47')); ?>;"></div>
                    </div>
                    <span class="text-[.7rem] text-[#666] w-8 text-right"><?php echo e($row['percent']); ?>%</span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

    </div>

    <div class="flex flex-col items-end justify-between shrink-0">
        <div class="text-right">
            <div class="compat-score" data-overall-score><?php echo e($result['overall']); ?>/100</div>
            <div class="compat-score-sub" data-match-label><?php echo e($matcher->matchLabel($result['overall'])); ?></div>
        </div>

        <div class="flex flex-col gap-1.5 mt-3">
            <button type="button" class="btn btn-secondary" onclick="openPetModal(<?php echo e($pet->id); ?>)">View</button>
            <a class="btn btn-adoptMe text-center" href="<?php echo e(route('application.apply', $pet)); ?>">Adopt</a>
        </div>
    </div>

</div>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/recommendation/_match-card.blade.php ENDPATH**/ ?>