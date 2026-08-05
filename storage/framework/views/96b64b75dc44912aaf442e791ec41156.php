<?php $__env->startSection('title', 'Animal Records - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Animal Records</h2>
        <p>Manage all shelter animal profiles</p>
    </div>

    <div class="flex flex-wrap gap-3 items-center my-5">
        <input type="text" data-search-input data-search-scope="animalTableBody"
               class="search-input flex-1 min-w-[220px]" placeholder="Search by name, species, breed…">
        <button class="btn btn-primary" onclick="openModal('addAnimalModal')">
            <i class="fa-solid fa-plus"></i> Add Animal
        </button>
    </div>

    <div class="filter-section">
        <select id="speciesFilter">
            <option value="all">All Species</option>
            <?php $__currentLoopData = $options::SPECIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s); ?>"><?php echo e($s); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        <select id="ageFilter">
            <option value="all">All Ages</option>
            <?php $__currentLoopData = $options::AGE_GROUPS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($a); ?>"><?php echo e($a); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        <select id="healthFilter">
            <option value="all">All Health</option>
            <?php $__currentLoopData = $options::HEALTH_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($h); ?>"><?php echo e($h); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        <select id="statusFilter">
            <option value="all">All Status</option>
            <?php $__currentLoopData = $options::ADOPTION_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s); ?>"><?php echo e($s); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div class="records-container mt-4">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr>
                        <th>Name</th><th>Species</th><th>Breed</th><th>Age</th>
                        <th>Sex</th><th>Health</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="animalTableBody">
                    <?php $__empty_1 = true; $__currentLoopData = $pets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pet): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr data-search-row
                            data-search-text="<?php echo e($pet->name); ?> <?php echo e($pet->species); ?> <?php echo e($pet->breed); ?>"
                            data-filter-row
                            data-filters="species:<?php echo e($pet->species); ?>|age:<?php echo e($pet->age_group); ?>|health:<?php echo e($pet->health_status); ?>|status:<?php echo e($pet->status); ?>">
                            <td class="font-semibold"><?php echo e($pet->name); ?></td>
                            <td><?php echo e($pet->species); ?></td>
                            <td><?php echo e($pet->breed); ?></td>
                            <td><?php echo e($pet->age_display); ?></td>
                            <td><?php echo e($pet->sex); ?></td>
                            <td><span class="badge badge-<?php echo e($pet->health_status_class); ?>"><?php echo e($pet->health_status); ?></span></td>
                            <td><span class="badge badge-<?php echo e($pet->adoption_status_class); ?>"><?php echo e($pet->status); ?></span></td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="openViewAnimalModal(<?php echo e($pet->id); ?>)">View</button>
                                <form action="<?php echo e(route('admin.animals.destroy', $pet)); ?>" method="POST" class="inline-block"
                                      onsubmit="return confirm('Delete <?php echo e($pet->name); ?>? This cannot be undone.')">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="8" class="text-[#888] py-6">No animals on record yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php echo $__env->make('admin.animal._add-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('admin.animal._view-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <script id="animalData" type="application/json">
        <?php echo $pets->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'species' => $p->species, 'breed' => $p->breed,
            'age_years' => $p->age_years, 'age_months' => $p->age_months, 'sex' => $p->sex,
            'intake' => optional($p->intake_date)->format('Y-m-d'), 'health' => $p->health_status,
            'status' => $p->status, 'vacc' => $p->vaccination_record_status, 'notes' => $p->notes,
            'physical_size' => $p->physical_size, 'assessment_status' => $p->assessment_status,
            'assessment_count' => $p->assessment_count,
            'assess_url' => route('admin.assessments.create', $p),
            'update_url' => route('admin.animals.update', $p),
        ])->toJson(); ?>

    </script>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        const rows = document.querySelectorAll('#animalTableBody [data-filter-row]');

        const filters = {
            species: document.getElementById('speciesFilter'),
            age: document.getElementById('ageFilter'),
            health: document.getElementById('healthFilter'),
            status: document.getElementById('statusFilter'),
        };

        Object.values(filters).forEach(select => {
            select.addEventListener('change', applyFilters);
        });

        function applyFilters() {
            rows.forEach(row => {
                const data = row.dataset.filters.split('|').reduce((acc, item) => {
                    const [key, value] = item.split(':');
                    acc[key] = value;
                    return acc;
                }, {});

                const visible =
                    (filters.species.value === 'all' || data.species === filters.species.value) &&
                    (filters.age.value === 'all' || data.age === filters.age.value) &&
                    (filters.health.value === 'all' || data.health === filters.health.value) &&
                    (filters.status.value === 'all' || data.status === filters.status.value);

                row.style.display = visible ? '' : 'none';
            });
        }
    </script>
    <script src="<?php echo e(asset('js/admin/animal.js')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/animal/index.blade.php ENDPATH**/ ?>