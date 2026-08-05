

<?php $__env->startSection('title', "Apply to Adopt {$pet->name} - PAIRfect Paws"); ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Apply to Adopt <?php echo e($pet->name); ?></h2>
            <p>Complete all fields to submit your application</p>
        </div>
    </div>

    <div class="content-area">

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

        <form action="<?php echo e(route('application.submit')); ?>" method="POST" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="pet_id" value="<?php echo e($pet->id); ?>">

            <!-- PERSONAL INFORMATION -->
            <div class="form-section !mt-4">

                <h3 class="!mb-3">Personal Information</h3>

                <div class="form-grid !gap-y-3">

                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input id="first_name" name="first_name" type="text" value="<?php echo e(old('first_name')); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input id="last_name" name="last_name" type="text" value="<?php echo e(old('last_name')); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input id="email" name="email" type="email" value="<?php echo e(old('email', auth()->user()->email)); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number *</label>
                        <input id="phone_number" name="phone_number" type="text" value="<?php echo e(old('phone_number')); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">Address *</label>
                        <input id="address" name="address" type="text" value="<?php echo e(old('address')); ?>" required>
                    </div>

                </div>

            </div>

            <!-- ADOPTER PROFILE -->
            <div class="form-section !mt-6">

                <h3 class="!mb-3">Adopter Profile</h3>

                <div class="form-grid !gap-y-3">

                    <div class="form-group">
                        <label for="housing_type">Housing Type *</label>
                        <select id="housing_type" name="housing_type" required>
                            <option value="">Select Housing Type</option>
                            <?php $__currentLoopData = \App\Support\ApplicationOptions::HOUSING_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('housing_type') === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="other_pets">Other Pets? *</label>
                        <select id="other_pets" name="other_pets" required>
                            <option value="">Select Option</option>
                            <?php $__currentLoopData = \App\Support\ApplicationOptions::OTHER_PETS_OPTIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('other_pets') === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="household_size">Household Size *</label>
                        <select id="household_size" name="household_size" required>
                            <option value="">Select Household Size</option>
                            <?php $__currentLoopData = \App\Support\ApplicationOptions::HOUSEHOLD_SIZES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('household_size') === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="monthly_income_range">Monthly Income Range *</label>
                        <select id="monthly_income_range" name="monthly_income_range" required>
                            <option value="">Select Income Range</option>
                            <?php $__currentLoopData = \App\Support\ApplicationOptions::INCOME_RANGES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('monthly_income_range') === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prior_pet_experience">Prior Pet Experience? *</label>
                        <select id="prior_pet_experience" name="prior_pet_experience" required>
                            <option value="">Select Experience</option>
                            <?php $__currentLoopData = \App\Support\ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($option); ?>" <?php if(old('prior_pet_experience') === $option): echo 'selected'; endif; ?>><?php echo e($option); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                </div>

            </div>

            <!-- DOCUMENT -->
            <div class="form-section">

                <div class="form-group">
                    <label>
                        Upload Valid ID / Proof of Residence *
                    </label>

                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small class="text-[.72rem] text-[#999]">PDF, JPG, or PNG — max 5MB.</small>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="agreement" name="agreed_to_animal_welfare_act" value="1" required>

                    <label for="agreement">
                        I agree to comply with Republic Act No. 8485
                        (Animal Welfare Act) and provide proper care
                        for the adopted pet.
                    </label>
                </div>

            </div>

            <div class="submit-container">
                <button type="submit" class="btn btn-primary">
                    Submit Application
                </button>
            </div>

        </form>

    </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/application/apply.blade.php ENDPATH**/ ?>