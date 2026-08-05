<?php if(!$prior): ?>
    <div class="custom-modal-header">
        <h2>Adoption History</h2>
    </div>
    <div class="custom-modal-body">
        <p class="text-[#888]">No prior adoption history found for this applicant.</p>
    </div>
    <div class="custom-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('adoptionHistoryModal')">Close</button>
    </div>
<?php else: ?>
    <?php
        $total = $prior->checkIns->count();
        $submitted = $prior->checkIns->filter(fn ($c) => $c->report)->count();
        $isComplete = $total > 0 && $submitted === $total;
    ?>

    <div class="custom-modal-header flex items-start justify-between">
        <div>
            <h2>Adoption History</h2>
            <small>Applicant: <?php echo e($prior->first_name); ?> <?php echo e($prior->last_name); ?> &middot; Pet: <?php echo e($prior->pet?->name); ?></small>
        </div>
        <span class="badge <?php echo e($isComplete ? 'badge-completed' : 'badge-pending'); ?>"><?php echo e($isComplete ? 'Complete' : 'In Progress'); ?></span>
    </div>

    <div class="custom-modal-body">

        <div class="grid grid-cols-3 gap-3 mb-5 max-[576px]:grid-cols-1">
            <div class="border border-[#ddd] rounded-lg p-3">
                <div class="text-[.72rem] text-[#888] mb-1">Adopted on</div>
                <div class="font-semibold"><?php echo e($prior->updated_at->format('F j, Y')); ?></div>
            </div>
            <div class="border border-[#ddd] rounded-lg p-3">
                <div class="text-[.72rem] text-[#888] mb-1">Monitoring</div>
                <div class="font-semibold <?php echo e($isComplete ? 'text-status-success-text' : 'text-status-processing-text'); ?>"><?php echo e($isComplete ? 'Completed' : 'Ongoing'); ?></div>
            </div>
            <div class="border border-[#ddd] rounded-lg p-3">
                <div class="text-[.72rem] text-[#888] mb-1">Reports filed</div>
                <div class="font-semibold"><?php echo e($submitted); ?>/<?php echo e($total); ?></div>
            </div>
        </div>

        <h6 class="font-bold text-[.8rem] uppercase tracking-wide text-[#888] mb-2.5">Post-adoption Monitoring — 3-3-3 check-ins</h6>
        <div class="flex items-center gap-2 flex-wrap mb-5 text-[.85rem]">
            <?php $__currentLoopData = $prior->checkIns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span class="badge <?php echo e($c->report ? 'badge-completed' : ($c->status === \App\Models\CheckIn::STATUS_OVERDUE ? 'badge-overdue' : 'badge-pending')); ?>">
                    <?php echo e($c->milestone_short); ?> (<?php echo e($c->due_date->format('M j')); ?>)
                </span>
                <?php if(!$loop->last): ?> <i class="fa-solid fa-arrow-right text-[#bbb]"></i> <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <h6 class="font-bold text-[.8rem] uppercase tracking-wide text-[#888] mb-2.5">Report summaries</h6>
        <div class="flex flex-col gap-2.5">
            <?php $__currentLoopData = $prior->checkIns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="border border-[#ddd] rounded-lg p-3 flex items-center gap-3">
                    <span class="badge <?php echo e($c->report ? 'badge-completed' : 'badge-upcoming'); ?>"><?php echo e($c->milestone_short); ?></span>
                    <span class="text-[.85rem]"><?php echo e($c->due_date->format('M j, Y')); ?></span>
                    <span class="badge <?php echo e($c->report ? 'badge-completed' : ($c->status === \App\Models\CheckIn::STATUS_OVERDUE ? 'badge-overdue' : 'badge-upcoming')); ?> ml-auto">
                        <?php echo e($c->report ? 'Completed' : ($c->status === \App\Models\CheckIn::STATUS_OVERDUE ? 'Overdue' : 'Upcoming')); ?>

                    </span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

    </div>

    <div class="custom-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('adoptionHistoryModal')">Close</button>
    </div>
<?php endif; ?>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/application/_history-modal-content.blade.php ENDPATH**/ ?>