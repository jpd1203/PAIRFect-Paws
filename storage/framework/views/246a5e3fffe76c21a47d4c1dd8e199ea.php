

<?php $__env->startSection('title', 'Overdue Check-in Notice - PAIRfect Paws'); ?>

<?php $__env->startSection('content'); ?>
    <div class="sticky-header">
        <div class="heading-text">
            <h2>Overdue Check-in Notice</h2>
            <p>Your post-adoption welfare report is past its due date.</p>
        </div>
    </div>

    <div class="content-area">
        <div class="empty-state">
            <i class="fa-solid fa-circle-check"></i>
            <h3>Nothing overdue</h3>
            <p>You're up to date on all your scheduled check-ins. Nice work!</p>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/flagged-cases/no-overdue-notice.blade.php ENDPATH**/ ?>