

<?php $__env->startSection('title', 'Submit Post-Adoption Report - PAIRfect Paws'); ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Submit Post-Adoption Report</h2>
            <p>Complete your scheduled welfare check-in<?php echo e($pet ? " for {$pet->name}" : ''); ?>.</p>
        </div>
    </div>

    <div class="content-area">

        <div class="contact-card">
            <p>
                Pending check-in: <strong><?php echo e($checkIn?->milestone_report_label); ?></strong> for <strong><?php echo e($pet?->name); ?></strong>. Please complete and submit below.
            </p>
        </div>

        <form id="submitReportForm" action="<?php echo e(route('flagged.previewReport')); ?>" method="POST" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="check_in_id" value="<?php echo e($checkIn?->id); ?>">
            <input type="hidden" name="pet_id" value="<?php echo e($pet?->id); ?>">
            <input type="hidden" name="milestone" value="<?php echo e($checkIn?->milestone); ?>">

            <div class="form-section !mt-4">

                <h3 class="!mb-3">Check-in Details</h3>

                <div class="form-grid !gap-y-3">

                    <div class="form-group">
                        <label>Adopted Pet</label>
                        <input type="text" value="<?php echo e($pet?->name); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Milestone</label>
                        <input type="text" value="<?php echo e($checkIn?->milestone_display); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Adopter Name</label>
                        <input type="text" value="<?php echo e($adopter?->full_name); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Report Date</label>
                        <input type="text" value="<?php echo e(now()->format('F j, Y')); ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- PET CONDITION -->
            <div class="form-section !mt-5">

                <h3 class="!mb-3">Pet's Current Condition</h3>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="healthStatus">Overall Health Status</label>
                        <select id="healthStatus" name="health_status" required>
                            <option value="">Select Health Status</option>
                            <?php $__currentLoopData = \App\Support\ReportOptions::HEALTH_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="eatingHabits">Eating &amp; Drinking Habits</label>
                        <select id="eatingHabits" name="eating_and_drinking" required>
                            <option value="">Select Habits</option>
                            <?php $__currentLoopData = \App\Support\ReportOptions::EATING_HABITS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="behavior">Behavior at Home</label>
                        <select id="behavior" name="behavior" required>
                            <option value="">Select Behavior</option>
                            <?php $__currentLoopData = \App\Support\ReportOptions::BEHAVIORS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="livingConditions">Living Conditions</label>
                        <select id="livingConditions" name="living_conditions" required>
                            <option value="">Select Living Condition</option>
                            <?php $__currentLoopData = \App\Support\ReportOptions::LIVING_CONDITIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Veterinary visit</label>
                        <div class="radio-group">
                            <label><input type="radio" name="vet_visit" value="1" required> Yes</label>
                            <label><input type="radio" name="vet_visit" value="0"> No</label>
                        </div>
                    </div>
                </div>

                <div class="form-section !mt-4">

                    <h3 class="!mb-2">Concerns &amp; Notes</h3>

                    <div class="form-group full-width">
                        <label for="concerns">Any concerns to raise?</label>
                        <textarea id="concerns" name="concerns" rows="3"></textarea>
                    </div>
                </div>

                <!-- DOCUMENT -->
                <div class="form-section !mt-4">

                    <div class="form-group">
                        <label>
                            Upload a photo of your pet
                        </label>

                        <input type="file" name="photo" accept="image/*">
                        <small class="text-[#999] text-[.75rem] mt-0.5">We love seeing how they're doing!</small>
                    </div>

                </div>

                <div class="submit-container !mt-6">
                    <button type="submit" class="btn btn-primary">
                        Submit Report
                    </button>
                </div>
            </div>

        </form>

    </div>

    <!-- Confirm Report Modal -->
    <div class="modal-overlay" id="confirmReportModal">
        <div class="pet-modal report-modal" id="confirmReportModalContent">
            <!-- Filled dynamically via fetch() after preview -->
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/submit-report.js')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/flagged-cases/submit-report.blade.php ENDPATH**/ ?>