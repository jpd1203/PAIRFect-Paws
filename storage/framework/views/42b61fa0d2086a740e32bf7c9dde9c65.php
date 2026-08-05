<?php $__env->startSection('title', 'Pet Recommendation - PAIRfect Paws'); ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Pet Recommendation</h2>
            <p>Tell us about your lifestyle so we can find your most compatible pet.</p>
        </div>
    </div>

    <div class="content-area">

        <div class="reco-banner mb-6">
            This information helps tailor your compatibility results. All pairings are still reviewed
            and decided manually by shelter staff — this is a guide, not a final decision.
        </div>

        <?php if($errors->any()): ?>
            <div class="mb-5 rounded-lg border border-status-danger-text bg-status-danger-bg px-4 py-3 text-status-danger-text text-sm">
                <strong>Please fix the following:</strong>
                <ul class="list-disc ml-5 mt-1">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?php echo e(route('recommendation.start')); ?>" method="POST" novalidate>
            <?php echo csrf_field(); ?>

            <div class="form-section mt-0">

                <h3>Your Adopter Profile</h3>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="physical_activity_level">Physical Activity Level *</label>
                        <select id="physical_activity_level" name="physical_activity_level" required>
                            <option value="">Select Activity Level</option>
                            <?php $__currentLoopData = $options::PHYSICAL_ACTIVITY_LEVELS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('physical_activity_level', $profile?->physical_activity_level) === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="time_availability">Time Availability *</label>
                        <select id="time_availability" name="time_availability" required>
                            <option value="">Select Time Availability</option>
                            <?php $__currentLoopData = $options::TIME_AVAILABILITY_OPTIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('time_availability', $profile?->time_availability) === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prior_pet_experience">Prior Pet Experience *</label>
                        <select id="prior_pet_experience" name="prior_pet_experience" required>
                            <option value="">Select Experience</option>
                            <?php $__currentLoopData = $options::PRIOR_EXPERIENCE_OPTIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('prior_pet_experience', $profile?->prior_pet_experience) === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="housing_type">Housing Type *</label>
                        <select id="housing_type" name="housing_type" required>
                            <option value="">Select Housing Type</option>
                            <?php $__currentLoopData = $options::HOUSING_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('housing_type', $profile?->housing_type) === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="household_composition">Household Composition *</label>
                        <select id="household_composition" name="household_composition" required>
                            <option value="">Select Household Composition</option>
                            <?php $__currentLoopData = $options::HOUSEHOLD_COMPOSITIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('household_composition', $profile?->household_composition) === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="monthly_income_range">Monthly Income Range *</label>
                        <select id="monthly_income_range" name="monthly_income_range" required>
                            <option value="">Select Income Range</option>
                            <?php $__currentLoopData = $options::INCOME_RANGES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('monthly_income_range', $profile?->monthly_income_range) === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                </div>

            </div>

            <div class="submit-container">
                <button type="submit" class="btn btn-primary">
                    Start Matching
                </button>
            </div>

        </form>

    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/recommendation/intake.blade.php ENDPATH**/ ?>