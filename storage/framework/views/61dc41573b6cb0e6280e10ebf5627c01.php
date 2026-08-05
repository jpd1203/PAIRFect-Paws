<?php $__env->startSection('title', 'Audit Logs - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="flex justify-between items-start flex-wrap gap-3">
        <div class="heading-text">
            <h2>Audit Logs</h2>
            <p>A record of every meaningful staff action across the system.</p>
        </div>
        <a href="<?php echo e(route('admin.audit-logs.export')); ?>" class="btn btn-secondary"><i class="fa-solid fa-download"></i> Export CSV</a>
    </div>

    <div class="my-3">
        <input type="text" data-search-input data-search-scope="auditTableBody" class="search-input w-full" placeholder="Search by user or action…">
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr><th>Date / Time</th><th>User</th><th>Role</th><th>Action</th></tr>
                </thead>
                <tbody id="auditTableBody">
                    <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr data-search-row data-search-text="<?php echo e($log->user_name); ?> <?php echo e($log->action); ?>">
                            <td><?php echo e($log->timestamp->format('M j, Y g:i A')); ?></td>
                            <td class="font-semibold"><?php echo e($log->user_name); ?></td>
                            <td><span class="badge <?php echo e($log->role === 'Admin' ? 'badge-approved' : ($log->role === 'Volunteer' ? 'badge-scheduled' : 'badge-pending')); ?>"><?php echo e($log->role); ?></span></td>
                            <td class="!text-left"><?php echo e($log->action); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="4" class="text-[#888] py-6">No activity recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4"><?php echo e($logs->links()); ?></div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/audit-logs/index.blade.php ENDPATH**/ ?>