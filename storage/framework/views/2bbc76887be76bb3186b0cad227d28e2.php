<?php $__env->startSection('title', 'Flagged Cases - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Flagged Cases</h2>
        <p>Post-adoption welfare cases that need staff follow-up.</p>
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="flagCasesList">
        <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
        <button class="filter-btn badge-pending" data-filter-btn="open">Open</button>
        <button class="filter-btn badge-flagged" data-filter-btn="intervention">Marked for Intervention</button>
        <button class="filter-btn badge-rejected" data-filter-btn="escalated">Escalated</button>
        <button class="filter-btn badge-approved" data-filter-btn="resolved">Resolved</button>
    </div>

    <div class="flag-cases" id="flagCasesList">
        <?php $__empty_1 = true; $__currentLoopData = $flaggedCases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $case): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $status = $case->resolved ? 'resolved' : ($case->is_escalated ? 'escalated' : ($case->marked_for_intervention ? 'intervention' : 'open'));
            ?>
            <div class="flag-card" data-filter-row data-status="<?php echo e($status); ?>">
                <div class="flex justify-between items-start gap-3 mb-2">
                    <div>
                        <strong><?php echo e($case->checkIn?->pet?->name); ?></strong>
                        <span class="text-[#7d4b52] text-[.85rem]"> — adopted by <?php echo e($case->checkIn?->user?->full_name); ?></span>
                    </div>
                    <span class="badge <?php echo e($case->resolved ? 'badge-approved' : ($case->is_escalated ? 'badge-rejected' : ($case->marked_for_intervention ? 'badge-flagged' : 'badge-pending'))); ?>">
                        <?php echo e($case->resolved ? 'Resolved' : ($case->is_escalated ? 'Escalated' : ($case->marked_for_intervention ? 'Intervention' : 'Open'))); ?>

                    </span>
                </div>

                <p class="flag-message"><?php echo e($case->description); ?></p>

                <?php if($case->intervention_type): ?>
                    <p class="flag-message"><strong>Intervention:</strong> <?php echo e($case->intervention_type); ?> — <?php echo e($case->intervention_notes); ?></p>
                <?php endif; ?>
                <?php if($case->escalation_reason): ?>
                    <p class="flag-message"><strong>Escalation reason:</strong> <?php echo e($case->escalation_reason); ?></p>
                <?php endif; ?>
                <?php if($case->resolved): ?>
                    <p class="flag-message"><strong>Resolved by <?php echo e($case->resolved_by); ?></strong> on <?php echo e($case->resolved_at?->format('M j, Y')); ?> — <?php echo e($case->resolution_notes); ?></p>
                <?php endif; ?>

                <?php if (! ($case->resolved)): ?>
                    <div class="flag-actions">
                        <button type="button" class="btn btn-yellow btn-sm" onclick="openInterventionModal(<?php echo e($case->id); ?>)">Mark for Intervention</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="openEscalateModal(<?php echo e($case->id); ?>)">Escalate</button>
                        <form action="<?php echo e(route('admin.flagged-cases.reminder', $case)); ?>" method="POST" class="inline-block">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-secondary btn-sm">Send Reminder</button>
                        </form>
                        <button type="button" class="btn btn-resolve btn-sm" onclick="openResolveModal(<?php echo e($case->id); ?>)">Resolve</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <!-- <div class="empty-state text-center py-16 text-[#888]">
                <i class="fa-solid fa-circle-check text-7xl mb-5 block text-[#c9c2b8]"></i>
                <p class="text-4 text-[#6b7280]">
                    No flagged cases right now.
                </p>
            </div> -->
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                <h3>No flagged cases right now.</h3>
                <p>Great news! There are currently no post-adoption cases that require attention.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mark for Intervention -->
    <div class="custom-modal-backdrop" id="interventionModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Mark for Intervention</h2></div>
            <form id="interventionForm" method="POST">
                <?php echo csrf_field(); ?>
                <div class="custom-modal-body">
                    <div class="form-group">
                        <label class="form-label">Intervention Type</label>
                        <select name="intervention_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <?php $__currentLoopData = $options::INTERVENTION_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option><?php echo e($t); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="intervention_notes" class="form-control remarks-textarea" rows="3"></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('interventionModal')">Cancel</button>
                    <button type="submit" class="btn btn-yellow">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Escalate -->
    <div class="custom-modal-backdrop" id="escalateModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Escalate Case</h2></div>
            <form id="escalateForm" method="POST">
                <?php echo csrf_field(); ?>
                <div class="custom-modal-body">
                    <div class="modal-note warning">This will flag the case as escalated to a supervisor for urgent review.</div>
                    <div class="form-group">
                        <label class="form-label">Escalation Reason</label>
                        <textarea name="escalation_reason" class="form-control remarks-textarea" rows="3" required></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('escalateModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Escalate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resolve -->
    <div class="custom-modal-backdrop" id="resolveModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Resolve Case</h2></div>
            <form id="resolveForm" method="POST">
                <?php echo csrf_field(); ?>
                <div class="custom-modal-body">
                    <div class="form-group">
                        <label class="form-label">Resolution Notes</label>
                        <textarea name="resolution_notes" class="form-control remarks-textarea" rows="3" placeholder="How was this resolved?"></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resolveModal')">Cancel</button>
                    <button type="submit" class="btn btn-resolve">Mark Resolved</button>
                </div>
            </form>
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        function openInterventionModal(id) {
            document.getElementById('interventionForm').action = `/admin/flagged-cases/${id}/intervention`;
            openModal('interventionModal');
        }
        function openEscalateModal(id) {
            document.getElementById('escalateForm').action = `/admin/flagged-cases/${id}/escalate`;
            openModal('escalateModal');
        }
        function openResolveModal(id) {
            document.getElementById('resolveForm').action = `/admin/flagged-cases/${id}/resolve`;
            openModal('resolveModal');
        }
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/flagged-cases/index.blade.php ENDPATH**/ ?>