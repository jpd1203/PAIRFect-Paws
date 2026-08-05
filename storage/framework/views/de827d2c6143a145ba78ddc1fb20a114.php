

<?php $__env->startSection('title', 'Settings - PAIRfect Paws'); ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Account Settings</h2>
            <p>Manage your profile and password</p>
        </div>
    </div>

    <div class="content-area">

        <div class="settings-wrap">

            <form action="<?php echo e(route('account.profile.update')); ?>" method="POST" class="settings-card">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PATCH'); ?>

                <h3>Profile Information</h3>

                <div class="settings-grid">

                    <div class="settings-field">
                        <label for="full_name">Full Name</label>
                        <input id="full_name" name="full_name" type="text" value="<?php echo e(old('full_name', $user->full_name)); ?>">
                    </div>

                    <div class="settings-field">
                        <label>Email <span class="field-hint">(contact support to change)</span></label>
                        <input type="email" value="<?php echo e($user->email); ?>" disabled>
                    </div>

                    <div class="settings-field">
                        <label for="phone_number">Phone Number</label>
                        <input id="phone_number" name="phone_number" type="text" value="<?php echo e(old('phone_number', $user->phone_number)); ?>">
                    </div>

                    <div class="settings-field full-width">
                        <label for="address">Address</label>
                        <input id="address" name="address" type="text" value="<?php echo e(old('address', $user->address)); ?>">
                    </div>

                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

            <form action="<?php echo e(route('account.password.update')); ?>" method="POST" class="settings-card">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PATCH'); ?>

                <h3>Change Password</h3>

                <div class="settings-grid">

                    <div class="settings-field">
                        <label for="current_password">Current Password</label>
                        <input id="current_password" name="current_password" type="password" required>
                    </div>

                    <div class="settings-field"></div>

                    <div class="settings-field">
                        <label for="password">New Password</label>
                        <input id="password" name="password" type="password" required minlength="10">
                    </div>

                    <div class="settings-field">
                        <label for="password_confirmation">Confirm New Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="10">
                    </div>

                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>

        </div>

    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/account/settings.blade.php ENDPATH**/ ?>