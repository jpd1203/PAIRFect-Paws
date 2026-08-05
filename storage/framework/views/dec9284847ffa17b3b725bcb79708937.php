<?php
    $isActive = fn (string ...$routeNames) => collect($routeNames)->contains(fn ($r) => request()->routeIs($r));
    $staff = auth()->user();
?>

<div class="sidebar" id="appSidebar">

    <div class="logo">
        <h3>PAIRfect Paws</h3>
    </div>

    <div class="sidebar-nav">

        <div class="menu-section">
            <a href="<?php echo e(route('admin.dashboard')); ?>" class="menu-item <?php echo e($isActive('admin.dashboard') ? 'active' : ''); ?>">
                <i class="fa-solid fa-table-columns fa-lg"></i> Dashboard
            </a>
        </div>

        <div class="menu-title">ADOPTION</div>

        <div class="menu-section">

            <a href="<?php echo e(route('admin.animals.index')); ?>" class="menu-item <?php echo e($isActive('admin.animals.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-paw fa-lg"></i> Animal Records
            </a>

            <a href="<?php echo e(route('admin.assessments.record')); ?>" class="menu-item <?php echo e($isActive('admin.assessments.record') ? 'active' : ''); ?>">
                <i class="fa-solid fa-pen-to-square fa-lg"></i> Assessment Record
            </a>

            <a href="<?php echo e(route('admin.assessments.record')); ?>" class="menu-item <?php echo e($isActive('admin.assessments.create', 'admin.assessments.store') ? 'active' : ''); ?>">
                <i class="fa-solid fa-heart fa-lg"></i> Pet Assessment
            </a>

            <a href="<?php echo e(route('admin.applications.index')); ?>" class="menu-item <?php echo e($isActive('admin.applications.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-file fa-lg"></i> Applications
            </a>

            <a href="<?php echo e(route('admin.compatibility.index')); ?>" class="menu-item <?php echo e($isActive('admin.compatibility.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-percent fa-lg"></i> Compatibility
            </a>

            <a href="<?php echo e(route('admin.adopter-profiles.index')); ?>" class="menu-item <?php echo e($isActive('admin.adopter-profiles.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-circle-user fa-lg"></i> Adoption Profile
            </a>

        </div>

        <div class="menu-title">POST-ADOPTIONS</div>

        <div class="menu-section">

            <a href="<?php echo e(route('admin.monitoring.index')); ?>" class="menu-item <?php echo e($isActive('admin.monitoring.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-magnifying-glass fa-lg"></i> Monitoring
            </a>

            <a href="<?php echo e(route('admin.flagged-cases.index')); ?>" class="menu-item <?php echo e($isActive('admin.flagged-cases.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-triangle-exclamation fa-lg"></i> Flagged Cases
            </a>

        </div>

        <div class="menu-title">SYSTEM</div>

        <div class="menu-section">

            <a href="<?php echo e(route('admin.volunteers.index')); ?>" class="menu-item <?php echo e($isActive('admin.volunteers.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-users fa-lg"></i> Volunteers
            </a>

            <a href="<?php echo e(route('admin.audit-logs.index')); ?>" class="menu-item <?php echo e($isActive('admin.audit-logs.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-clipboard-list fa-lg"></i> Audit Logs
            </a>

            <a href="<?php echo e(route('admin.funds.index')); ?>" class="menu-item <?php echo e($isActive('admin.funds.index') ? 'active' : ''); ?>">
                <i class="fa-solid fa-sack-dollar fa-lg"></i> Manage Funds
            </a>

        </div>

    </div>

    <div class="user-card">
        <div class="avatar"><?php echo e($staff?->avatar_initial ?? '?'); ?></div>
        <div class="user-info flex-1">
            <strong><?php echo e($staff?->full_name ?? 'Staff'); ?></strong>
            <small><?php echo e($staff?->email); ?></small>
        </div>
        <form action="<?php echo e(route('admin.logout')); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <button type="submit" class="logout-btn" title="Log out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/partials/sidebar.blade.php ENDPATH**/ ?>