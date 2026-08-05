<?php $__env->startSection('title', 'Applications - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Applications</h2>
        <p>Review adoption applications and manage the interview pipeline.</p>
    </div>

    <div class="my-3">
        <input type="text" data-search-input data-search-scope="applicationTableBody"
               class="search-input w-full" placeholder="Search by applicant or pet name…">
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="applicationTableBody">
        <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
        <button class="filter-btn badge-pending" data-filter-btn="pending">Pending</button>
        <button class="filter-btn badge-scheduled" data-filter-btn="scheduled">Scheduled</button>
        <button class="filter-btn badge-underreview" data-filter-btn="underreview">Under Review</button>
        <button class="filter-btn badge-approved" data-filter-btn="approved">Approved</button>
        <button class="filter-btn badge-rejected" data-filter-btn="rejected">Rejected</button>
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr><th>Applicant</th><th>Pet</th><th>Submitted</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="applicationTableBody">
                    <?php $__empty_1 = true; $__currentLoopData = $applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr data-search-row data-search-text="<?php echo e($app->first_name); ?> <?php echo e($app->last_name); ?> <?php echo e($app->pet?->name); ?>"
                            data-filter-row data-status="<?php echo e($app->status_slug); ?>">
                            <td class="!text-left font-semibold"><?php echo e($app->first_name); ?> <?php echo e($app->last_name); ?></td>
                            <td><?php echo e($app->pet?->name); ?></td>
                            <td><?php echo e($app->created_at->format('M j, Y')); ?></td>
                            <td><span class="badge <?php echo e($app->status_badge_class); ?>"><?php echo e($app->status_display); ?></span></td>
                            <td>
                                <?php if($app->status_slug === 'scheduled'): ?>
                                    <button class="btn btn-yellow btn-sm" onclick="openAddNoteModal(<?php echo e($app->id); ?>)">Add Notes</button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" onclick="openReviewModal(<?php echo e($app->id); ?>)">View</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="5" class="text-[#888] py-6">No applications yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php echo $__env->make('admin.application._review-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('admin.application._schedule-modal', ['volunteers' => $volunteers], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('admin.application._note-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('admin.application._history-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('admin.application._compatibility-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <script id="applicationData" type="application/json">
        <?php echo $applications->map(function ($app) {
            return [
                'id' => $app->id,
                'status' => $app->status_slug,
                'full_name' => "{$app->first_name} {$app->last_name}",
                'pet' => $app->pet?->name,
                'pet_details' => $app->pet ? "{$app->pet->species}, {$app->pet->breed}, {$app->pet->age_display}" : '',
                'submitted' => $app->created_at->format('M j'),
                'submitted_full' => $app->created_at->format('F j, Y'),
                'contact' => $app->phone_number,
                'email' => $app->email,
                'address' => $app->address,
                'physical_activity_level' => $app->physical_activity_level,
                'time_availability' => $app->time_availability,
                'prior_pet_experience' => $app->prior_pet_experience,
                'housing_type' => $app->housing_type,
                'household_composition' => $app->household_composition,
                'monthly_income_range' => $app->monthly_income_range,
                'document_url' => route('admin.applications.document', $app),
                'has_compatibility' => (bool) $app->compatibility_result,
                'compatibility' => $app->compatibility_result,
                'has_history' => (bool) $app->priorHistory,
                'interview_notes' => $app->interview_notes,
                'interview_date' => $app->interview_date_display,
                'interview_time' => $app->interview_time_display,
                'conducted_by' => $app->conducted_by,
                'decision_remarks' => $app->decision_remarks,
                'schedule_action' => route('admin.applications.schedule'),
                'notes_action' => route('admin.applications.notes', $app),
                'decide_action' => route('admin.applications.decide', $app),
                'history_url' => route('admin.applications.history', $app),
            ];
        })->toJson(); ?>

    </script>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/admin/application.js')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/application/index.blade.php ENDPATH**/ ?>