<?php $__env->startSection('title', 'Assessment Record - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Assessment Record</h2>
        <p>A pet may be assessed a maximum of 3 times.</p>
    </div>

    <div class="my-3">
        <input type="text" data-search-input data-search-scope="assessmentTableBody"
               class="search-input w-full" placeholder="Search">
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr>
                        <th>Pet</th><th>Assessed By</th><th>Date Last Assessed</th>
                        <th>Status</th><th>Summary</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assessmentTableBody">
                    <?php $__empty_1 = true; $__currentLoopData = $pets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pet): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr data-search-row data-search-text="<?php echo e($pet->name); ?> <?php echo e($pet->species); ?>">
                            <td class="!text-left font-semibold"><?php echo e($pet->name); ?> <span class="text-[#999] font-normal">(<?php echo e($pet->species); ?>)</span></td>
                            <td><?php echo e($pet->last_assessed_by ?? '—'); ?></td>
                            <td><?php echo e($pet->last_assessed_at?->format('M j, Y') ?? '—'); ?></td>
                            <td>
                                <span class="badge <?php echo e($pet->assessment_status === 'complete' ? 'badge-completed' : 'badge-pending'); ?>">
                                    <?php echo e(ucfirst($pet->assessment_status)); ?>

                                </span>
                                <div class="text-[#999] text-[.75rem] mt-1"><?php echo e($pet->assessment_count); ?>/3</div>
                            </td>
                            <td>
                                <?php if($pet->assessment_status === 'complete'): ?>
                                    <button class="btn btn-secondary btn-sm" onclick="openAssessmentSummary(<?php echo e($pet->id); ?>)">Summary</button>
                                <?php else: ?>
                                    <span class="text-[#bbb]">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($pet->assessment_count < 3): ?>
                                    <a href="<?php echo e(route('admin.assessments.create', $pet)); ?>" class="btn btn-primary btn-sm">Assess</a>
                                <?php else: ?>
                                    <span class="text-[#bbb]">Complete</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="text-[#888] py-6">No pets on record yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assessment Summary Modal -->
    <div class="custom-modal-backdrop" id="assessmentSummaryModal">
        <div class="custom-modal" id="assessmentSummaryContent">
            <!-- filled dynamically via fetch() -->
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        async function openAssessmentSummary(petId) {
            const content = document.getElementById('assessmentSummaryContent');
            try {
                const res = await fetch(`/admin/animals/${petId}/assessment-summary`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Failed to load summary');
                content.innerHTML = await res.text();
                openModal('assessmentSummaryModal');
            } catch (err) {
                window.PAIRfectAdmin?.showToast('Could not load the assessment summary.', 'error');
            }
        }
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/assessment/record.blade.php ENDPATH**/ ?>