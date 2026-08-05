<div class="custom-modal-backdrop" id="addAnimalModal">
    <div class="custom-modal custom-modal-wide">
        <form action="<?php echo e(route('admin.animals.store')); ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
        <div class="custom-modal-header">
            <h3>Add New Animal</h3>
            <small>Fill in the details for the new shelter animal.</small>
        </div>
            <div class="custom-modal-body">
                <div class="grid grid-cols-3 gap-4 max-[768px]:grid-cols-1">

                    <!-- Basic Information -->
                    <!-- <div class="col-span-3">
                        <h4 class="text-lg font-semibold text-text-dark">
                            Basic Information
                        </h4>
                    </div> -->

                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input name="name" class="form-control" placeholder="e.g. Mochi" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Species *</label>
                        <select name="species" class="form-select" required>
                            <option value="">Select Species</option>
                            <?php $__currentLoopData = $options::SPECIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($s); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Breed *</label>
                        <input name="breed" class="form-control" placeholder="e.g. Mixed" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Age *</label>

                        <div class="flex gap-2">
                            <input type="number" name="age_years" min="0" max="30" class="form-control flex-1" placeholder="Years">
                            <input type="number" name="age_months" min="0" max="11" class="form-control flex-1" placeholder="Months">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sex *</label>
                        <select name="sex" class="form-select" required>
                            <option value="">Select</option>
                            <option>Male</option>
                            <option>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Physical Size *</label>
                        <select name="physical_size" class="form-select" required>
                            <option value="">Select</option>
                            <?php $__currentLoopData = $options::SIZES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($s); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Intake Date *</label>
                        <input
                            name="intake_date"
                            type="date"
                            class="form-control"
                            value="<?php echo e(now()->format('Y-m-d')); ?>"
                            required>
                    </div>

                    <!-- Health Status -->
                    <!-- <div class="col-span-3 mt-2">
                        <h4 class="text-lg font-semibold text-text-dark">
                            Health Status
                        </h4>
                    </div> -->

                    <div class="form-group">
                        <label class="form-label">Health Status *</label>
                        <select name="health_status" class="form-select" required>
                            <option value="">Select</option>
                            <?php $__currentLoopData = $options::HEALTH_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($h); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Vaccination Status</label>
                        <select name="vaccination_record_status" class="form-select">
                            <option>Complete</option>
                            <option>Incomplete</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adoption Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="">Select</option>
                            <?php $__currentLoopData = $options::ADOPTION_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option <?php echo e($s === 'Assessing' ? 'selected' : ''); ?>>
                                    <?php echo e($s); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pet Photo</label>
                        <input
                            type="file"
                            name="photo"
                            class="form-control"
                            accept="image/*">
                    </div>

                    <div class="form-group col-span-3">
                        <label class="form-label">Notes</label>
                        <textarea
                            name="notes"
                            class="form-control"
                            rows="2"
                            placeholder="Optional notes..."></textarea>
                    </div>

                </div>
            </div>
            
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAnimalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Animal</button>
            </div>
        </form>
    </div>
</div>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/animal/_add-modal.blade.php ENDPATH**/ ?>