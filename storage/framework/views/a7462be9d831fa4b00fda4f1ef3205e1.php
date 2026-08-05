<?php $__env->startSection('title', 'Dashboard - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Dashboard</h2>
        <p>Overview of shelter activity and pending work.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <i class="fa-solid fa-paw stat-icon text-primary"></i>
            <h6>Total Pets</h6>
            <h1><?php echo e($totalPets); ?></h1>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-circle-check stat-icon text-status-success-text"></i>
            <h6>Available for Adoption</h6>
            <h1><?php echo e($availablePets); ?></h1>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-file stat-icon text-status-processing-text"></i>
            <h6>Pending Applications</h6>
            <h1><?php echo e($pendingApplications); ?></h1>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-5 items-start">

        <div>

            <div class="dashboard-box">
                <h3>Application Pipeline</h3>
                <div class="pipeline-container">
                    <div class="pipeline-item">
                        <span class="pipeline scheduled" style="width: <?php echo e(max(40, $pipeline['scheduled'] * 12)); ?>px"></span>
                        Scheduled (<?php echo e($pipeline['scheduled']); ?>)
                    </div>
                    <div class="pipeline-item">
                        <span class="pipeline underreview" style="width: <?php echo e(max(40, $pipeline['underreview'] * 12)); ?>px"></span>
                        Under Review (<?php echo e($pipeline['underreview']); ?>)
                    </div>
                    <div class="pipeline-item">
                        <span class="pipeline approved" style="width: <?php echo e(max(40, $pipeline['approved'] * 12)); ?>px"></span>
                        Approved (<?php echo e($pipeline['approved']); ?>)
                    </div>
                    <div class="pipeline-item">
                        <span class="pipeline rejected" style="width: <?php echo e(max(40, $pipeline['rejected'] * 12)); ?>px"></span>
                        Rejected (<?php echo e($pipeline['rejected']); ?>)
                    </div>
                </div>
            </div>

            <div class="dashboard-box">
                <h3>Recent Applications</h3>
                <div class="records-container records-container-full">
                    <table class="w-full dashboard-table">
                        <thead>
                            <tr><th>Applicant</th><th>Pet</th><th>Status</th><th>Submitted</th></tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $recentApplications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td><?php echo e($app->first_name); ?> <?php echo e($app->last_name); ?></td>
                                    <td><?php echo e($app->pet?->name); ?></td>
                                    <td><span class="badge <?php echo e($app->status_badge_class); ?>"><?php echo e($app->status_display); ?></span></td>
                                    <td><?php echo e($app->created_at->format('M j, Y')); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr><td colspan="4" class="text-[#888] py-6">No applications yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div>

            <div class="dashboard-box">
                <h3>Needs Attention</h3>
                <div class="alert-list">

                    <?php $__currentLoopData = $overdueCheckIns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $checkIn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="alert-item alert-overdue">
                            <i class="fa-solid fa-circle-exclamation alert-icon text-status-danger-text"></i>
                            <span>
                                <strong><?php echo e($checkIn->pet?->name); ?></strong>
                                — <?php echo e($checkIn->milestone_display); ?> overdue since
                                <?php echo e($checkIn->due_date->format('M j')); ?>

                            </span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <?php $__currentLoopData = $unresolvedFlags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="alert-item alert-flag">
                            <i class="fa-solid fa-flag alert-icon text-status-flagged-text"></i>
                            <span>
                                <strong><?php echo e($flag->checkIn?->pet?->name); ?></strong>
                                — unresolved flagged case
                            </span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <?php if($overdueCheckIns->isEmpty() && $unresolvedFlags->isEmpty()): ?>
                        <p class="text-[#888] text-sm py-2">
                            Nothing needs attention right now.
                        </p>
                    <?php endif; ?>

                </div>
            </div>

            <div class="dashboard-box">
                <h3>Recent Activity</h3>
                <div class="flex flex-col gap-2.5">
                    <?php $__empty_1 = true; $__currentLoopData = $recentActivity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="text-[.85rem] border-b border-[#f0ece5] pb-2 last:border-b-0">
                            <strong><?php echo e($log->user_name); ?></strong> <?php echo e($log->action); ?>

                            <div class="text-[#999] text-[.75rem] mt-0.5"><?php echo e($log->timestamp->diffForHumans()); ?></div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <p class="text-[#888] text-sm py-2">No activity recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/dashboard/index.blade.php ENDPATH**/ ?>