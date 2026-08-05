<div class="custom-modal-backdrop" id="scheduleInterviewModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h2>Schedule Interview</h2>
            <small id="scheduleSubheading">Applicant: · Pet: · Submitted:</small>
        </div>
        <form action="<?php echo e(route('admin.applications.schedule')); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="application_id" id="scheduleAppId">
            <div class="custom-modal-body">
                <div class="grid grid-cols-1 gap-4 max-[768px]:grid-cols-1">
                    <div class="form-group">
                        <label class="form-label">Interview Date</label>
                        <input type="date" name="interview_date" class="form-control" min="<?php echo e(now()->format('Y-m-d')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Interview Time</label>
                        <input type="time" name="interview_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conducted By</label>
                        <select name="conducted_by" class="form-select" required>
                            <option value="">Select Interviewer</option>
                            <?php $__currentLoopData = $volunteers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($v->full_name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeModal('scheduleInterviewModal')">Cancel</button>
                <button type="submit" class="btn btn-sucess">Confirm</button>
            </div>
        </form>
    </div>
</div>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/application/_schedule-modal.blade.php ENDPATH**/ ?>