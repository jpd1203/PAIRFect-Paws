

<?php $__env->startSection('title', 'Overdue Check-in Notice - PAIRfect Paws'); ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Overdue Check-in Notice</h2>
            <p>Your post-adoption welfare report is past its due date.</p>
        </div>
    </div>

    <div class="content-area">

        <!-- OVERDUE ALERT -->
        <div class="alert-card">

            <h3>Your <?php echo e($checkIn->milestone_short); ?> Check-In is Overdue</h3>

            <p>
                Your welfare check-in for <strong><?php echo e($checkIn->pet?->name); ?></strong> was due on
                <strong><?php echo e($checkIn->due_date->format('F j, Y')); ?></strong>. You have not submitted your
                report yet.
            </p>

            <p>
                Two automated reminder notifications have already been sent
                to your registered email. If you continue to not respond,
                the shelter will be required to flag your case for intervention
                and may contact you directly.
            </p>

        </div>

        <!-- WHAT TO DO -->
        <div class="info-card">

            <h3>What You Need to Do</h3>

            <ul class="action-list">

                <li>
                    <span class="dot red"></span>
                    Submit your <?php echo e($checkIn->milestone_short); ?> Welfare Report for <?php echo e($checkIn->pet?->name); ?> as soon as possible.
                </li>

                <li>
                    <span class="dot yellow"></span>
                    Include accurate details about <?php echo e($checkIn->pet?->name); ?>'s health, behavior,
                    living conditions, and any concerns.
                </li>

                <li>
                    <span class="dot green"></span>
                    Once submitted, your check-in status will be updated and
                    the shelter will review your report.
                </li>

            </ul>

            <a class="btn btn-submitOverdue" href="<?php echo e(route('flagged.submitReport')); ?>">
                Submit Overdue Report Now
            </a>

        </div>

        <!-- CONSEQUENCES -->
        <div class="info-card">

            <h3>What Happens If You Don't Submit</h3>

            <ul class="warning-list">

                <li>
                    <i class="fa-solid fa-flag"></i>
                    Your case will be automatically flagged as a
                    Missed Submission and escalated to the shelter administrator.
                </li>

                <li>
                    <i class="fa-solid fa-phone"></i>
                    A shelter volunteer or administrator may contact
                    you by phone or email to follow up on <?php echo e($checkIn->pet?->name); ?>'s welfare.
                </li>

                <li>
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Repeated missed check-ins may result in an
                    in-person home visit to verify the animal's condition.
                </li>

            </ul>

        </div>

        <!-- CONTACT -->
        <div class="contact-card">

            <p>
                If you are experiencing difficulty submitting your report
                or have concerns about <?php echo e($checkIn->pet?->name); ?>'s health, please contact
                the shelter directly at
                <strong>redcubspetpatrol@gmail.com</strong>
                or call
                <strong>+63 918 985 2149</strong>.
            </p>

        </div>

    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/flagged-cases/overdue-notice.blade.php ENDPATH**/ ?>