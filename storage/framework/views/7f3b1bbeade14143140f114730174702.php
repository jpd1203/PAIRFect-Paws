

<?php $__env->startSection('title', 'Browse Pets - PAIRfect Paws'); ?>

<?php $__env->startSection('content'); ?>

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Available Pets</h2>
            <p>Browse animals ready for adoption</p>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <select id="speciesFilter">
                <option value="All Species" <?php if($speciesFilter === 'All Species'): echo 'selected'; endif; ?>>All Species</option>
                <option value="Dog" <?php if($speciesFilter === 'Dog'): echo 'selected'; endif; ?>>Dog</option>
                <option value="Cat" <?php if($speciesFilter === 'Cat'): echo 'selected'; endif; ?>>Cat</option>
            </select>

            <select id="ageFilter">
                <option value="All Ages" <?php if($ageFilter === 'All Ages'): echo 'selected'; endif; ?>>All Ages</option>
                <option value="Baby" <?php if($ageFilter === 'Baby'): echo 'selected'; endif; ?>>Baby</option>
                <option value="Young" <?php if($ageFilter === 'Young'): echo 'selected'; endif; ?>>Young</option>
                <option value="Adult" <?php if($ageFilter === 'Adult'): echo 'selected'; endif; ?>>Adult</option>
                <option value="Senior" <?php if($ageFilter === 'Senior'): echo 'selected'; endif; ?>>Senior</option>
            </select>
        </div>
    </div>

    <div class="content-area">
        <div id="petGridContainer">
            <?php echo $__env->make('animal._pet-grid', ['pets' => $pets], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>

    <!-- Modal Overlay -->
    <div class="modal-overlay" id="petModal">
        <div class="pet-modal" id="petModalContent">
            <!-- Filled in dynamically via fetch() -->
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/browse-pets.js')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/animal/index.blade.php ENDPATH**/ ?>