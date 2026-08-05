
<?php
    $isActive = fn (string ...$routeNames) => collect($routeNames)->contains(fn ($r) => request()->routeIs($r));
?>

<div class="sidebar" id="appSidebar">

    <div class="logo">
        <h3>PAIRfect Paws</h3>
    </div>

    <div class="sidebar-nav">

        <div class="menu-title">ADOPTION</div>

        <div class="menu-section">

            <a href="<?php echo e(route('animal.index')); ?>"
               class="menu-item <?php echo e($isActive('animal.index', 'animal.show', 'home') ? 'active' : ''); ?>">
                <i class="fa-solid fa-paw fa-lg"></i> Browse Pets
            </a>

            <a href="<?php echo e(route('recommendation.intake')); ?>"
               class="menu-item <?php echo e($isActive('recommendation.intake', 'recommendation.start', 'recommendation.results') ? 'active' : ''); ?>">
                <i class="fa-solid fa-heart fa-lg"></i> Pet Recommendation
            </a>

            <a href="<?php echo e(route('application.index')); ?>"
               class="menu-item <?php echo e($isActive('application.index', 'application.apply', 'application.submit') ? 'active' : ''); ?>">
                <i class="fa-solid fa-file fa-lg"></i> My Application
            </a>

        </div>

        <div class="menu-title">POST-ADOPTION</div>

        <div class="menu-section">

            <a href="<?php echo e(route('monitoring.index')); ?>"
               class="menu-item <?php echo e($isActive('monitoring.index', 'monitoring.report.show') ? 'active' : ''); ?>">
                <i class="fa-solid fa-circle-check fa-lg"></i> My Check-ins
            </a>

            <a href="<?php echo e(route('flagged.submitReport')); ?>"
               class="menu-item <?php echo e($isActive('flagged.submitReport', 'flagged.previewReport', 'flagged.confirmSubmit') ? 'active' : ''); ?>">
                <i class="fa-solid fa-pen fa-lg"></i> Submit Report
            </a>

            <a href="<?php echo e(route('flagged.overdueNotice')); ?>"
               class="menu-item <?php echo e($isActive('flagged.overdueNotice') ? 'active' : ''); ?>">
                <i class="fa-solid fa-circle-exclamation fa-lg"></i> Overdue Notice
            </a>

            <a href="<?php echo e(route('flagged.flaggedNotice')); ?>"
               class="menu-item <?php echo e($isActive('flagged.flaggedNotice') ? 'active' : ''); ?>">
                <i class="fa-solid fa-triangle-exclamation fa-lg"></i> Flagged Notice
            </a>

        </div>

    </div>

    <?php echo $__env->make('partials.profile-card', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/partials/sidebar.blade.php ENDPATH**/ ?>