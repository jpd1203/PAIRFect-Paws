<?php $__env->startSection('title', 'Adoption Profile - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Adoption Profile</h2>
        <p>Adopter lifestyle profiles collected from applications.</p>
    </div>

    <div class="my-5">
        <input type="text" data-search-input data-search-scope="profilesList" class="search-input w-full" placeholder="Search by applicant name…">
    </div>

    <div class="profiles-container" id="profilesList">
        <?php $__empty_1 = true; $__currentLoopData = $applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="profile-card" data-search-row data-search-text="<?php echo e($app->first_name); ?> <?php echo e($app->last_name); ?>">

                <div class="profile-header">
                    <div>
                        <h3><?php echo e($app->first_name); ?> <?php echo e($app->last_name); ?></h3>
                        <p>Applied for <?php echo e($app->pet?->name); ?> &middot; <?php echo e($app->created_at->format('F j, Y')); ?></p>
                    </div>
                    <span class="badge <?php echo e($app->status_badge_class); ?>"><?php echo e($app->status_display); ?></span>
                </div>

                <div class="profile-details">
                    <div class="detail-column">
                        <p><strong>Physical Activity:</strong> <?php echo e($app->physical_activity_level); ?></p>
                        <p><strong>Time Availability:</strong> <?php echo e($app->time_availability); ?></p>
                        <p><strong>Prior Experience:</strong> <?php echo e($app->prior_pet_experience); ?></p>
                    </div>
                    <div class="detail-column">
                        <p><strong>Housing Type:</strong> <?php echo e($app->housing_type); ?></p>
                        <p><strong>Household:</strong> <?php echo e($app->household_composition); ?></p>
                        <p><strong>Monthly Income:</strong> <?php echo e($app->monthly_income_range); ?></p>
                    </div>
                </div>

                <div class="profile-actions">
                    <?php if($app->priorHistory): ?>
                        <button type="button" class="btn btn-secondary btn-sm"
                                onclick="openProfileHistory('<?php echo e(route('admin.applications.history', $app)); ?>')">
                            <i class="fa-solid fa-clock-rotate-left"></i> Adoption Record History
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo e(route('admin.adopter-profiles.document', $app)); ?>" target="_blank" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-file"></i> Document Upload
                    </a>
                </div>

            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                <h3>No adopter profiles yet.</h3>
                <p>No adopter profiles have been created yet. Profiles will appear here once applications are submitted.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="custom-modal-backdrop" id="profileHistoryModal">
        <div class="custom-modal" id="profileHistoryContent"></div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        async function openProfileHistory(url) {
            const content = document.getElementById('profileHistoryContent');
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error('failed');
                content.innerHTML = (await res.text()).replaceAll('adoptionHistoryModal', 'profileHistoryModal');
                openModal('profileHistoryModal');
            } catch (err) {
                window.PAIRfectAdmin?.showToast('Could not load adoption history.', 'error');
            }
        }
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/adopter-profile/index.blade.php ENDPATH**/ ?>