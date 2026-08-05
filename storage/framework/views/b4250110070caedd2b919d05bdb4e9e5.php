

<?php $__env->startSection('title', 'My Application - PAIRfect Paws'); ?>

<?php
    use App\Models\AdoptionApplication;
    $statusValue = $application?->status ?? -1;
    $scheduledValue = AdoptionApplication::STATUS_SCHEDULED;
    $underReviewValue = AdoptionApplication::STATUS_UNDER_REVIEW;
?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>My Application Status</h2>
            <p>Track the progress of your adoption application</p>
        </div>
    </div>

    <div class="content-area">

        <?php if(!$application): ?>
            <div class="empty-state">
                <i class="fa-solid fa-file"></i>
                <h3>No application yet</h3>
                <p>Browse available pets and tap "Adopt Me!" to get started.</p>
            </div>
        <?php else: ?>
            <div class="application-card" id="applicationCard"
                 data-application-id="<?php echo e($application->id); ?>"
                 data-last-updated="<?php echo e($application->last_updated->toIso8601String()); ?>">

                <div class="card-header">
                    <h2>Application #<?php echo e(str_pad($application->id, 4, '0', STR_PAD_LEFT)); ?></h2>

                    <span class="badge <?php echo e($application->status_badge_class); ?>" id="statusBadge">
                        <?php echo e($application->status_display); ?>

                    </span>
                </div>

                <div class="application-details">

                    <div class="detail-row">
                        <span class="label">Pet Requested</span>
                        <span class="value">
                            <?php echo e($application->pet ? "{$application->pet->name} ({$application->pet->species_display}, {$application->pet->breed}, {$application->pet->age_display})" : '—'); ?>

                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="label">Submitted on</span>
                        <span class="value"><?php echo e($application->submitted_on->format('F j, Y')); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="label">Last Updated</span>
                        <span class="value" id="lastUpdatedValue"><?php echo e($application->last_updated->format('F j, Y')); ?></span>
                    </div>

                </div>

                <div class="progress-section">

                    <h3>Application Progress</h3>

                    <div class="progress-flow" id="progressFlow">

                        <span class="step completed">Submitted</span>

                        <span class="arrow">&rarr;</span>

                        <span class="step <?php echo e($statusValue == $scheduledValue ? 'active' : ($statusValue > $scheduledValue ? 'completed' : '')); ?>">
                            Interview Scheduled
                        </span>

                        <span class="arrow">&rarr;</span>

                        <span class="step <?php echo e($statusValue == $underReviewValue ? 'active' : ($statusValue > $underReviewValue ? 'completed' : '')); ?>">
                            Under Review
                        </span>

                        <span class="arrow">&rarr;</span>

                        <span class="step <?php echo e(in_array($application->status, [AdoptionApplication::STATUS_APPROVED, AdoptionApplication::STATUS_REJECTED]) ? 'active' : ''); ?>">
                            Decision
                        </span>

                    </div>

                </div>

                <?php if($application->interview_notes || $application->decision_remarks): ?>
                    <div class="note-section" id="noteSection">
                        <strong>Note:</strong>
                        <?php echo e($application->decision_remarks ?: $application->interview_notes); ?>

                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/my-application.js')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/application/index.blade.php ENDPATH**/ ?>