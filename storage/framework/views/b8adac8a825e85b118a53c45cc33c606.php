<div class="modal-header">
    <div>
        <h2>Post-Adoption Report</h2>
        <p>
            Applicant: You &middot; Pet: <?php echo e($report->pet?->name ?? '—'); ?> &middot; <?php echo e($report->milestone_report_label); ?>

        </p>
    </div>
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

<?php if(!empty(trim((string) $report->concerns))): ?>
    <span class="notes-label">Additional Notes</span>
    <div class="notes-box">
        <?php echo e($report->concerns); ?>

    </div>
<?php endif; ?>

<div class="modal-actions">
    <button type="button" class="btn btn-secondary" onclick="closeReportViewModal()">
        Close
    </button>
</div>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/monitoring/_report-view-modal-content.blade.php ENDPATH**/ ?>