<div class="modal-header">
    <div>
        <h2>Confirm Report</h2>
        <p><?php echo e(str_replace(' Check-in', ' Report', $report->milestone_report_label)); ?> &mdash; <?php echo e($report->pet?->name ?? ''); ?></p>
    </div>
</div>

<div class="report-banner">
    Your report will be submitted to the shelter for review. Thank you!
</div>

<div class="profile-table">

    <div class="profile-row">
        <span>Health Status</span>
        <span><?php echo e($report->health_status); ?></span>
    </div>

    <div class="profile-row">
        <span>Eating &amp; Drinking</span>
        <span><?php echo e($report->eating_and_drinking); ?></span>
    </div>

    <div class="profile-row">
        <span>Behavior</span>
        <span><?php echo e($report->behavior); ?></span>
    </div>

    <div class="profile-row">
        <span>Vet Visit</span>
        <span><?php echo e($report->vet_visit_display); ?></span>
    </div>

    <div class="profile-row">
        <span>Living Conditions</span>
        <span><?php echo e($report->living_conditions); ?></span>
    </div>

</div>

<div class="modal-actions">

    <button type="button" class="btn btn-secondary" onclick="closeConfirmReportModal()">
        Close
    </button>

    <form action="<?php echo e(route('flagged.confirmSubmit')); ?>" method="POST" enctype="multipart/form-data" id="confirmReportForm" class="inline-block">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="check_in_id" value="<?php echo e($report->check_in_id); ?>">
        <input type="hidden" name="pet_id" value="<?php echo e($report->pet_id); ?>">
        <input type="hidden" name="milestone" value="<?php echo e($report->milestone); ?>">
        <input type="hidden" name="health_status" value="<?php echo e($report->health_status); ?>">
        <input type="hidden" name="eating_and_drinking" value="<?php echo e($report->eating_and_drinking); ?>">
        <input type="hidden" name="behavior" value="<?php echo e($report->behavior); ?>">
        <input type="hidden" name="living_conditions" value="<?php echo e($report->living_conditions); ?>">
        <input type="hidden" name="vet_visit" value="<?php echo e($report->vet_visit ? 1 : 0); ?>">
        <input type="hidden" name="concerns" value="<?php echo e($report->concerns); ?>">
        <button type="submit" class="btn btn-primary">
            Submit
        </button>
    </form>

</div>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/flagged-cases/_confirm-report-modal-content.blade.php ENDPATH**/ ?>