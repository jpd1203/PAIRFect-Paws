<?php $user = auth()->user(); ?>

<div class="user-card" id="profileCardToggle">

    <div class="avatar">
        <?php echo e($user?->avatar_initial ?? '?'); ?>

    </div>

    <div>
        <strong><?php echo e($user?->full_name ?? 'Guest'); ?></strong>
        <br>
        <?php echo e($user?->email ?? ''); ?>

    </div>

    <i class="fa-solid fa-chevron-up profile-caret" id="profileCaret"></i>

    <div class="profile-dropdown" id="profileDropdown">

        <div class="profile-dropdown-header">
            Signed in as<br>
            <strong><?php echo e($user?->email ?? ''); ?></strong>
        </div>

        <a href="<?php echo e(route('animal.index')); ?>" class="profile-dropdown-item">
            <i class="fa-solid fa-house"></i> Home
        </a>

        <a href="<?php echo e(route('account.settings')); ?>" class="profile-dropdown-item">
            <i class="fa-solid fa-gear"></i> Settings
        </a>

        <form action="<?php echo e(route('logout')); ?>" method="POST" class="m-0">
            <?php echo csrf_field(); ?>
            <button type="submit" class="profile-dropdown-item profile-dropdown-item-danger logout-button">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
        </form>

    </div>

</div>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/partials/profile-card.blade.php ENDPATH**/ ?>