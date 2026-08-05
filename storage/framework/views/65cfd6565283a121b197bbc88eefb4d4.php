

<?php $__env->startSection('title', 'My Check-ins - PAIRfect Paws'); ?>

<?php use App\Models\CheckIn; ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>My Check-ins</h2>
            <p>Your post-adoption reporting schedule<?php echo e($pet ? " for {$pet->name}" : ''); ?>.</p>
        </div>
    </div>

    <div class="content-area">

        <?php if(!$hasActiveAdoption): ?>
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                <h3>No check-ins scheduled yet</h3>
                <p>Once your adoption is finalized, your 3-day, 3-week, and 3-month check-ins will appear here.</p>
            </div>
        <?php else: ?>
            <div class="info-card schedule-card">

                <h2>Check-in Schedule &mdash; <?php echo e($pet?->name); ?></h2>

                <?php $__currentLoopData = $schedule; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $checkIn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="schedule-item">

                        <div class="schedule-info">
                            <span class="status-dot <?php echo e($checkIn->status_dot_class); ?>"></span>
                            <strong><?php echo e($checkIn->milestone_display); ?></strong> &middot; Due: <?php echo e($checkIn->due_date->format('F j, Y')); ?>

                        </div>

                        <div class="schedule-actions">

                            <span class="badge <?php echo e($checkIn->status_badge_class); ?>">
                                <?php echo e($checkIn->status_display); ?>

                            </span>

                            <?php if(in_array($checkIn->status, [CheckIn::STATUS_PENDING, CheckIn::STATUS_OVERDUE])): ?>
                                <a class="btn btn-primary" href="<?php echo e(route('flagged.submitReport')); ?>">
                                    Submit Now
                                </a>
                            <?php endif; ?>

                        </div>

                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            </div>

            <!-- SUBMITTED REPORTS -->
            <div class="info-card reports-card">

                <h2>Submitted Reports</h2>

                <?php if($submittedReports->isEmpty()): ?>
                    <p class="text-[#888] text-center py-3">No reports submitted yet.</p>
                <?php else: ?>
                    <table class="reports-table">

                        <thead>
                            <tr>
                                <th>Milestone</th>
                                <th>Submitted</th>
                                <th>Health Status</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php $__currentLoopData = $submittedReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><?php echo e(str_replace(' Check-in', '', $report->milestone_report_label)); ?></td>
                                    <td><?php echo e($report->report_date->format('F j, Y')); ?></td>
                                    <td>
                                        <span class="badge <?php echo e($report->health_badge_class); ?>">
                                            <?php echo e($report->health_status); ?>

                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-secondary" onclick="openReportViewModal(<?php echo e($report->id); ?>)">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        </tbody>

                    </table>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>

    <!-- Report View Modal -->
    <div class="modal-overlay" id="reportViewModal">
        <div class="pet-modal report-modal" id="reportViewModalContent">
            <!-- Filled dynamically via fetch() -->
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/my-check-ins.js')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/monitoring/index.blade.php ENDPATH**/ ?>