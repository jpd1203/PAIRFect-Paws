<?php $__env->startSection('title', 'Monitoring - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Post-Adoption Monitoring</h2>
        <p>Track all welfare check-ins for adopted animals</p>
    </div>

    <div class="my-5">
        <input type="text" data-search-input data-search-scope="monitoringTableBody" class="search-input w-full" placeholder="Search by adopter or pet name…">
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="monitoringTableBody">
        <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
        <button class="filter-btn badge-upcoming" data-filter-btn="upcoming">Upcoming</button>
        <button class="filter-btn badge-pending" data-filter-btn="pending">Pending</button>
        <button class="filter-btn badge-completed" data-filter-btn="completed">Completed</button>
        <button class="filter-btn badge-overdue" data-filter-btn="overdue">Overdue</button>
        <button class="filter-btn badge-flagged" data-filter-btn="flagged">Flagged</button>
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr><th>Adopter</th><th>Pet</th><th>Milestone</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="monitoringTableBody">
                    <?php $__empty_1 = true; $__currentLoopData = $checkIns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr data-search-row data-search-text="<?php echo e($c->user?->full_name); ?> <?php echo e($c->pet?->name); ?>"
                            data-filter-row data-status="<?php echo e($c->status_slug); ?>">
                            <td class="font-semibold"><?php echo e($c->user?->full_name); ?></td>
                            <td><?php echo e($c->pet?->name); ?></td>
                            <td><?php echo e($c->milestone_display); ?></td>
                            <td><?php echo e($c->due_date->format('M j, Y')); ?></td>
                            <td><span class="badge <?php echo e($c->status_badge_class); ?>"><?php echo e($c->status_display); ?></span></td>
                            <td>
                                <?php if(in_array($c->status_slug, ['pending', 'overdue'])): ?>
                                    <form action="<?php echo e(route('admin.monitoring.reminder', $c)); ?>" method="POST" class="inline-block">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn btn-secondary btn-sm">Remind</button>
                                    </form>
                                    <form action="<?php echo e(route('admin.monitoring.flag', $c)); ?>" method="POST" class="inline-block"
                                          onsubmit="return confirm('Flag this case for follow-up?')">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn btn-danger btn-sm">Flag</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-[#bbb]">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="text-[#888] py-6">No monitoring cases yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/monitoring/index.blade.php ENDPATH**/ ?>