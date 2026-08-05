<?php $__env->startSection('title', 'Compatibility - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Compatibility</h2>
        <p>Pet Recommendation match results for applicants who used the feature.</p>
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="compatList">
        <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
        <button class="filter-btn badge-approved" data-filter-btn="high">High Match</button>
        <button class="filter-btn badge-scheduled" data-filter-btn="good">Good Match</button>
        <button class="filter-btn badge-pending" data-filter-btn="fair">Fair Match</button>
        <button class="filter-btn badge-rejected" data-filter-btn="low">Low Match</button>
    </div>

    <div id="compatList" class="flex flex-col gap-0 mt-4">
        <?php $__empty_1 = true; $__currentLoopData = $applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $overall = $app->compatibility_result['overall'] ?? 0;

                $tier = $overall >= 80 ? 'high'
                        : ($overall >= 60 ? 'good'
                        : ($overall >= 40 ? 'fair'
                        : 'low'));

                $tierLabel = ucfirst($tier) . ' Match';

                $ringColor = match ($tier) {
                    'high' => '#0F7B5A',
                    'good' => '#2D8CFF',
                    'fair' => '#E6A700',
                    default => '#D9534F',
                };
            ?>
            <div class="pet-card-compat" data-filter-row data-status="<?php echo e(strtolower(explode(' ', $tier)[0])); ?>">

    <div class="compat-card-top">

        <img src="<?php echo e($app->pet?->image_url); ?>"
             alt="<?php echo e($app->pet?->name); ?>"
             class="pet-photo-compat">

        <div class="compat-info">

            <div class="pet-title">
                <h3><?php echo e($app->pet?->name); ?></h3>

                <span>
                    <?php echo e($app->pet?->species); ?>

                    •
                    <?php echo e($app->pet?->age_group); ?>

                    •
                    <?php echo e($app->pet?->sex); ?>

                </span>
            </div>

            <p class="matched-user">
                Matched with
                <strong><?php echo e($app->first_name); ?> <?php echo e($app->last_name); ?></strong>
            </p>

            <p class="applied-date">
                <i class="fa-regular fa-calendar"></i>
                Applied <?php echo e($app->created_at->format('M d, Y')); ?>

            </p>

        </div>

        <div class="compat-score">

            <div class="score-circle"
                 style="--ring: <?php echo e($ringColor); ?>">
                <div class="score-value">
                    <strong><?php echo e($overall); ?></strong>
                    <small>/100</small>
                </div>
            </div>

            <span class="score-badge">
                <?php echo e($tier); ?>

            </span>

        </div>

    </div>

    <div class="compat-card-footer">

        <button
            class="btn btn-primary"
            onclick='openBreakdown(<?php echo json_encode($app->compatibility_result, 15, 512) ?>, <?php echo json_encode("{$app->pet?->name} · {$app->first_name} {$app->last_name}", 15, 512) ?>)'>
            View Breakdown
        </button>

    </div>

</div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                <h3>No Compatibility Result</h3>
                <p>No applicants have used Pet Recommendation yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="custom-modal-backdrop" id="breakdownModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2>Compatibility Breakdown</h2>
                <small id="breakdownSubheading"></small>
            </div>
            <div class="custom-modal-body">
                <div id="breakdownRows" class="flex flex-col gap-3"></div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('breakdownModal')">Close</button>
            </div>
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        function openBreakdown(result, subheading) {
            document.getElementById('breakdownSubheading').textContent = subheading;
            const host = document.getElementById('breakdownRows');
            host.innerHTML = '';
            (result.rows || []).forEach((row) => {
                const color = row.percent >= 60 ? '#295F51' : row.percent >= 35 ? '#614E34' : '#773E47';
                host.insertAdjacentHTML('beforeend', `
                    <div class="compat-row-grid">
                        <span class="compat-label">${row.label}</span>
                        <div class="compat-bar-bg"><div class="compat-bar-fill-anim" style="width:${row.percent}%;background:${color}"></div></div>
                        <span class="compat-pct">${row.percent}%</span>
                    </div>
                `);
            });
            openModal('breakdownModal');
        }
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/compatibility/index.blade.php ENDPATH**/ ?>