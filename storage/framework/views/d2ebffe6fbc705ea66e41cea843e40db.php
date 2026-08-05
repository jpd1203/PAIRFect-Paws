<div class="custom-modal-backdrop" id="viewAnimalModal">
    <div class="custom-modal custom-modal-wide">
        <div class="custom-modal-header">
            <h3 id="viewAnimalTitle">Animal Profile</h3>
        </div>
        <form id="viewAnimalForm" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="custom-modal-body">
                <div class="grid grid-cols-3 gap-4 max-[768px]:grid-cols-1">

                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input id="vName" name="name" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Species</label>
                        <select id="vSpecies" name="species" class="form-select">
                            <?php $__currentLoopData = $options::SPECIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($s); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Breed</label>
                        <input id="vBreed" name="breed" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Age Group</label>
                        <select id="vAgeGroup" name="age_group" class="form-select">
                            <?php $__currentLoopData = $options::AGE_GROUPS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($a); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Age (years)</label>
                        <input id="vAgeYears" name="age_years" type="number" min="0" max="30" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sex</label>
                        <select id="vSex" name="sex" class="form-select">
                            <option>Male</option><option>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Intake Date</label>
                        <input id="vIntake" name="intake_date" type="date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Health Status</label>
                        <select id="vHealth" name="health_status" class="form-select">
                            <?php $__currentLoopData = $options::HEALTH_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($h); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adoption Status</label>
                        <select id="vStatus" name="status" class="form-select">
                            <?php $__currentLoopData = $options::ADOPTION_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($s); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Physical Size</label>
                        <select id="vSize" name="physical_size" class="form-select">
                            <?php $__currentLoopData = $options::SIZES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($s); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Vaccination Status</label>
                        <select id="vVacc" name="vaccination_record_status" class="form-select">
                            <option>Complete</option>
                            <option>Incomplete</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Replace Photo (optional)</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>

                    <div class="form-group col-span-3">
                        <label class="form-label">Notes</label>
                        <textarea id="vNotes" name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-group col-span-3">
                        <label class="form-label">Pet Assessment</label>
                        <div class="flex items-center justify-between gap-3 border border-[#ddd] rounded-lg px-4 py-3 bg-neutral-light">
                            <span class="text-[.88rem]">
                                <span id="vAssessBadge" class="badge badge-pending">Pending</span>
                                <span id="vAssessCount" class="text-[#777] ml-2">0/3 assessments completed</span>
                            </span>
                            <a id="vAssessBtn" href="#" class="btn btn-secondary btn-sm">Edit Pet Assessment</a>
                        </div>
                    </div>

                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewAnimalModal')">Close</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/animal/_view-modal.blade.php ENDPATH**/ ?>