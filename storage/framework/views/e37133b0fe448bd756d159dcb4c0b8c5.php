

<?php $__env->startSection('title', 'Flagged Report Notice - PAIRfect Paws'); ?>

<?php $reportLabel = str_replace(' Check-in', '', $report->milestone_report_label); ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Flagged Report Notice</h2>
            <p>One or more of your submitted welfare reports have been flagged for shelter review.</p>
        </div>
    </div>

    <div class="content-area">

        <!-- FLAGGED ALERT -->
        <div class="alert-card">

            <h3>Your <?php echo e($reportLabel); ?> Report Has Been Flagged</h3>

            <p>
                Your welfare report for <strong><?php echo e($report->pet?->name); ?></strong>,
                submitted on <strong><?php echo e($report->report_date->format('F j, Y')); ?></strong>,
                has been flagged by the system due to a
                <strong><?php echo e($report->flag_reason ?? 'Welfare Concern'); ?></strong>.
            </p>

            <p>
                Two automated reminder notifications have already been sent
                to your registered email. If you continue to not respond,
                the shelter will be required to flag your case for intervention
                and may contact you directly.
            </p>

        </div>

        <!-- Report Preview Card -->
        <div class="report-preview-card">

            <h3>Post-Adoption Report</h3>

            <small>
                Pet: <?php echo e($report->pet?->name); ?> &middot; <?php echo e($report->milestone_report_label); ?>

            </small>

            <div class="report-table">

                <div class="report-row">
                    <span>Health Status</span>
                    <span><?php echo e($report->health_status); ?></span>
                </div>

                <div class="report-row">
                    <span>Eating &amp; Drinking</span>
                    <span><?php echo e($report->eating_and_drinking); ?></span>
                </div>

                <div class="report-row">
                    <span>Behavior</span>
                    <span><?php echo e($report->behavior); ?></span>
                </div>

                <div class="report-row">
                    <span>Vet Visit</span>
                    <span><?php echo e($report->vet_visit_display); ?></span>
                </div>

                <div class="report-row">
                    <span>Living Conditions</span>
                    <span><?php echo e($report->living_conditions); ?></span>
                </div>

            </div>

            <?php if(!empty(trim((string) $report->concerns))): ?>
                <div class="notes-box">
                    <?php echo e($report->concerns); ?>

                </div>
            <?php endif; ?>

        </div>

        <!-- What This Means -->
        <div class="info-card">

            <h3>What This Means for You</h3>

            <ul class="icon-list">

                <li>
                    <i class="fa-solid fa-magnifying-glass"></i>
                    The shelter team is reviewing your submitted welfare report
                    to assess <?php echo e($report->pet?->name); ?>'s condition.
                </li>

                <li>
                    <i class="fa-solid fa-phone"></i>
                    A volunteer or administrator may contact you by email
                    or phone for a follow-up conversation.
                </li>

                <li>
                    <i class="fa-solid fa-house"></i>
                    Depending on the review outcome, the shelter may request
                    an in-person visit or additional updates.
                </li>

                <li>
                    <i class="fa-solid fa-square-check"></i>
                    Once the shelter closes the review, the flag will be cleared
                    and monitoring will continue normally.
                </li>

            </ul>

        </div>

        <!-- CONTACT -->
        <div class="contact-card">

            <p>
                If <?php echo e($report->pet?->name); ?>'s condition has improved since your last report,
                or if you have additional information to share,
                please contact the shelter directly at
                <strong>redcubspetpatrol@gmail.com</strong>
                or
                <strong>+63 918 985 2149</strong>.
                You may also submit your next scheduled check-in report
                when it becomes due.
            </p>

        </div>

    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/flagged-cases/flagged-notice.blade.php ENDPATH**/ ?>