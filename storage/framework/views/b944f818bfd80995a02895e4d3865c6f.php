<?php $__env->startSection('title', 'Manage Funds - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="flex justify-between items-start flex-wrap gap-3">
        <div class="heading-text">
            <h2>Manage Funds</h2>
            <p>Track all donation and fund records.</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 items-center my-5">
        <input type="text" data-search-input data-search-scope="" class="search-input flex-1 min-w-[220px]" placeholder="Search by user or action…">
        <button class="btn btn-primary" onclick="openModal('recordFundsModal')"><i class="fa-solid fa-wallet"></i>Record Funds</button>
    </div>

    <div class="stats-grid !grid-cols-2 my-5 max-[576px]:!grid-cols-1">
        <div class="stat-card">
            <h6>Total Donations</h6>
            <h1 class="text-status-success-text">₱<?php echo e(number_format($totalDonations, 2)); ?></h1>
        </div>
        <div class="stat-card">
            <h6>Total Shelter Spent</h6>
            <h1 class="text-status-danger-text">₱<?php echo e(number_format($totalSpent, 2)); ?></h1>
        </div>
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full funds-table">
                <thead>
                    <tr><th>Date</th><th>Activity</th><th>Donation Added</th><th>Shelter Spent</th></tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($r->recorded_date->format('m/d/Y')); ?></td>
                            <td><?php echo e($r->activity); ?></td>
                            <td class="<?php echo e($r->donation_added ? 'amount-positive' : ''); ?>"><?php echo e($r->donation_added ? number_format($r->donation_added, 2) : '-'); ?></td>
                            <td class="<?php echo e($r->shelter_spent ? 'amount-negative' : ''); ?>"><?php echo e($r->shelter_spent ? '-'.number_format($r->shelter_spent, 2) : '-'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="4" class="text-[#888] py-6 text-center">No fund records yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Record Funds Modal -->
    <div class="custom-modal-backdrop" id="recordFundsModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Record Funds</h2></div>
            <form action="<?php echo e(route('admin.funds.store')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <div class="custom-modal-body">
                    <div class="grid grid-cols-2 gap-4 max-[768px]:grid-cols-1">
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="recorded_date" class="form-control" value="<?php echo e(now()->format('Y-m-d')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Entry Type</label>
                            <select name="entry_type" class="form-select" required>
                                <option value="donation">Donation Added</option>
                                <option value="expense">Shelter Spent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Activity</label>
                            <input name="activity" class="form-control" placeholder="e.g. Veterinary Supplies" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (₱)</label>
                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('recordFundsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/funds/index.blade.php ENDPATH**/ ?>