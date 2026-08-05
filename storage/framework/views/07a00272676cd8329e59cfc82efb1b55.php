<?php
    $energyLabels = [
        1 => 'Very Low Energy',
        2 => 'Low Energy',
        3 => 'Moderate Energy',
        4 => 'High Energy',
        5 => 'Very High Energy',
    ];
    $independenceLabels = [
        1 => 'Very Dependent',
        2 => 'Somewhat Dependent',
        3 => 'Mostly Independent',
        4 => 'Independent',
        5 => 'Very Independent',
    ];
    $trainabilityLabels = [
        1 => 'Slow Learner',
        2 => 'Moderate Trainability',
        3 => 'Average Trainability',
        4 => 'Highly Trainable',
        5 => 'Exceptionally Trainable',
    ];
    $temperamentLabels = [
        1 => 'Cautious / Reactive',
        2 => 'Reserved',
        3 => 'Generally calm',
        4 => 'Gentle & Friendly',
        5 => 'Very Calm & Adaptable',
    ];

    $energyVal = is_numeric($pet->energy_level) ? ($energyLabels[(int)$pet->energy_level] ?? $pet->energy_level) : ($pet->energy_level ?: 'Moderate Energy');
    $independenceVal = is_numeric($pet->independence_level) ? ($independenceLabels[(int)$pet->independence_level] ?? $pet->independence_level) : ($pet->independence_level ?: 'Mostly Independent');
    $trainabilityVal = is_numeric($pet->trainability) ? ($trainabilityLabels[(int)$pet->trainability] ?? $pet->trainability) : ($pet->trainability ?: 'Highly Trainable');
    $temperamentVal = is_numeric($pet->temperament) ? ($temperamentLabels[(int)$pet->temperament] ?? $pet->temperament) : ($pet->temperament ?: 'Generally calm');
?>

<!-- Modal Header & Center Image -->
<div class="relative flex flex-col items-center mb-4">
    <div class="w-full text-left mb-2">
        <h2 class="text-3xl font-bold font-primary text-text-dark m-0"><?php echo e($pet->name); ?></h2>
        <p class="text-base text-[#777] m-0">Pet Profile</p>
    </div>
    <div class="modal-image -mt-10 mb-4">
        <img src="<?php echo e($pet->image_url); ?>" alt="<?php echo e($pet->name); ?>" class="w-[180px] h-[180px] object-cover rounded-2xl border border-[#444] shadow-sm">
    </div>
</div>

<!-- Landscape 2-Column Profile Table -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1">

    <!-- Left Column -->
    <div>
        <div class="profile-row">
            <span>Species</span>
            <span><?php echo e($pet->species_display); ?></span>
        </div>
        <div class="profile-row">
            <span>Breed</span>
            <span><?php echo e($pet->breed); ?></span>
        </div>
        <div class="profile-row">
            <span>Age</span>
            <span><?php echo e($pet->age_years ? $pet->age_years . ' yrs' : $pet->age_group); ?></span>
        </div>
        <div class="profile-row">
            <span>Sex</span>
            <span><?php echo e($pet->sex_display); ?></span>
        </div>
        <div class="profile-row">
            <span>Intake Date</span>
            <span><?php echo e($pet->intake_date ? $pet->intake_date->format('m/d/Y') : 'mm/dd/yyyy'); ?></span>
        </div>
        <div class="profile-row">
            <span>Health Status</span>
            <span><?php echo e($pet->health_status); ?></span>
        </div>
        <div class="profile-row">
            <span>Vaccination Records</span>
            <span><?php echo e($pet->vaccination_records ?: 'Anti-Rabies, 5in1'); ?></span>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <div class="profile-row">
            <span>Status</span>
            <span><?php echo e($pet->status); ?></span>
        </div>
        <div class="profile-row">
            <span>Energy Level</span>
            <span><?php echo e($energyVal); ?></span>
        </div>
        <div class="profile-row">
            <span>Independence Level</span>
            <span><?php echo e($independenceVal); ?></span>
        </div>
        <div class="profile-row">
            <span>Trainability</span>
            <span><?php echo e($trainabilityVal); ?></span>
        </div>
        <div class="profile-row">
            <span>Physical Size</span>
            <span><?php echo e($pet->physical_size ?: 'Medium'); ?></span>
        </div>
        <div class="profile-row">
            <span>Temperament</span>
            <span><?php echo e($temperamentVal); ?></span>
        </div>
    </div>

</div>

<!-- Modal Action Buttons -->
<div class="modal-actions mt-6 flex justify-end gap-3">
    <button type="button" class="btn btn-secondary rounded-xl px-6 py-2" onclick="closePetModal()">
        Close
    </button>
    <a class="btn btn-apply rounded-xl px-6 py-2" href="<?php echo e(route('application.apply', $pet)); ?>">
        Adopt Me!
    </a>
</div>
<?php /**PATH C:\Users\Mary Lois Denosta\.gemini\antigravity-ide\scratch\pairfect-paws-ui\resources\views/animal/_pet-modal-content.blade.php ENDPATH**/ ?>