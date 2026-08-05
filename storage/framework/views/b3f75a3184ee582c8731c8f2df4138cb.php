<?php $__env->startSection('title', "{$animal->species} Pet Assessment - PAIRfect Paws Admin"); ?>

<?php $__env->startSection('content'); ?>
<div class="form-content">

    <div class="assessment-header">
        <div class="heading-text">
            <h2>Pet Assessment</h2>
            <p>Assessment of <?php echo e($animal->species); ?> Behavioral Characteristics — <?php echo e($animal->name); ?> (Assessment #<?php echo e($assessmentNumber); ?> of 3)</p>
        </div>
    </div>

    <div class="rating-guide">
        <strong>0</strong> = Never &nbsp; <strong>1</strong> = Seldom &nbsp; <strong>2</strong> = Sometimes &nbsp; <strong>3</strong> = Usually &nbsp; <strong>4</strong> = Always
    </div>

    <form action="<?php echo e(route('admin.assessments.store', $animal)); ?>" method="POST" id="assessmentForm" class="flex-1 flex flex-col min-h-0">
        <?php echo csrf_field(); ?>
        <div class="assessment-scroll">
            <div class="assessment-body">
                <div class="assessment-container !p-0 max-[991px]:!p-0">

                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $catKey => $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="assessment-card">
                            <h4><?php echo e($cat['label']); ?></h4>

                            <?php if($cat['type'] === 'flat'): ?>
                                <div class="question-grid">
                                    <?php $__currentLoopData = $cat['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $text): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="question">
                                            <label><?php echo e($i + 1); ?>. <?php echo e($text); ?></label>
                                            <div class="slider-row">
                                                <span>0</span>
                                                <input type="range" min="0" max="4" step="1" value="0" class="rating-slider"
                                                    name="<?php echo e($catKey); ?>_<?php echo e($i); ?>" oninput="this.nextElementSibling.nextElementSibling.textContent=this.value">
                                                <span>4</span>
                                                <div class="value">0</div>
                                            </div>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            <?php else: ?>
                                <?php $colLabels = array_keys($cat['columns']); $colLists = array_values($cat['columns']); ?>
                                <div class="question-grid">
                                    <label class="temp-categ"><?php echo e($colLabels[0]); ?></label>
                                    <label class="temp-categ"><?php echo e($colLabels[1]); ?></label>
                                </div>
                                <div class="question-grid">
                                    <?php $maxRows = max(count($colLists[0]), count($colLists[1])); ?>
                                    <?php for($row = 0; $row < $maxRows; $row++): ?>
                                        <?php for($col = 0; $col < 2; $col++): ?>
                                            <?php if(isset($colLists[$col][$row])): ?>
                                                <div class="question">
                                                    <label><?php echo e($row + 1); ?>. <?php echo e($colLists[$col][$row]); ?></label>
                                                    <div class="slider-row">
                                                        <span>0</span>
                                                        <input type="range" min="0" max="4" step="1" value="0" class="rating-slider"
                                                            name="<?php echo e($catKey); ?>_<?php echo e($col); ?>_<?php echo e($row); ?>" oninput="this.nextElementSibling.nextElementSibling.textContent=this.value">
                                                        <span>4</span>
                                                        <div class="value">0</div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="question"></div>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                </div>
            </div>
        </div>

        <div class="button-group sticky bottom-0 pt-2">
            <a href="<?php echo e(route('admin.assessments.record')); ?>" class="btn btn-secondary">Back</a>
            <button type="submit" class="btn btn-primary">Save Assessment</button>
        </div>

    </form>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/assessment/form.blade.php ENDPATH**/ ?>