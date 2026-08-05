<?php $__env->startSection('title', 'Volunteers - PAIRfect Paws Admin'); ?>

<?php $__env->startSection('content'); ?>

    <div class="heading-text">
        <h2>Volunteers</h2>
        <p>Manage staff and volunteer accounts.</p>
    </div>

    <div class="flex flex-wrap gap-3 items-center my-3">
        <input type="text" data-search-input data-search-scope="volunteerTableBody" class="search-input flex-1 min-w-[220px]" placeholder="Search by name or email…">
        <button class="btn btn-primary" onclick="openModal('addVolunteerModal')"><i class="fa-solid fa-plus"></i> Add Volunteer</button>
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="volunteerTableBody">
                    <?php $__empty_1 = true; $__currentLoopData = $volunteers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr data-search-row data-search-text="<?php echo e($v->full_name); ?> <?php echo e($v->email); ?>">
                            <td class="!text-left font-semibold"><?php echo e($v->full_name); ?></td>
                            <td><?php echo e($v->email); ?></td>
                            <td><?php echo e($v->phone_number ?? '—'); ?></td>
                            <td><span class="badge <?php echo e($v->role === 'Admin' ? 'badge-approved' : 'badge-scheduled'); ?>"><?php echo e($v->role); ?></span></td>
                            <td><span class="badge <?php echo e($v->is_active ? 'badge-active' : 'badge-inactive'); ?>"><?php echo e($v->is_active ? 'Active' : 'Inactive'); ?></span></td>
                            <td>
                                <button
                                    class="btn btn-secondary btn-sm"
                                    onclick="openEditVolunteerModal({
                                        id: <?php echo e($v->id); ?>,
                                        full_name: <?php echo \Illuminate\Support\Js::from($v->full_name)->toHtml() ?>,
                                        phone_number: <?php echo \Illuminate\Support\Js::from($v->phone_number)->toHtml() ?>,
                                        role: <?php echo \Illuminate\Support\Js::from($v->role)->toHtml() ?>,
                                        is_active: <?php echo e($v->is_active ? 'true' : 'false'); ?>,
                                        update_url: <?php echo \Illuminate\Support\Js::from(route('admin.volunteers.update', $v))->toHtml() ?>
                                    })">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="text-[#888] py-6">No volunteers yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Volunteer -->
    <div class="custom-modal-backdrop" id="addVolunteerModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Add Volunteer</h2></div>
            <form action="<?php echo e(route('admin.volunteers.store')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <div class="custom-modal-body">
                    <div class="form-group"><label class="form-label">Full Name</label><input name="full_name" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Phone Number</label><input name="phone_number" class="form-control"></div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option>Volunteer</option>
                            <option>Admin</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Temporary Password</label><input name="password" type="password" class="form-control" minlength="10" required></div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addVolunteerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Volunteer -->
    <div class="custom-modal-backdrop" id="editVolunteerModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Edit Volunteer</h2></div>
            <form id="editVolunteerForm" method="POST">
                <?php echo csrf_field(); ?>
                <?php echo method_field('POST'); ?>
                <div class="custom-modal-body">
                    <div class="form-group"><label class="form-label">Full Name</label><input id="evName" name="full_name" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Phone Number</label><input id="evPhone" name="phone_number" class="form-control"></div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select id="evRole" name="role" class="form-select" required>
                            <option>Volunteer</option>
                            <option>Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div class="status-options">
                            <input type="radio" name="is_active" id="statusActivate" value="1">
                            <label for="statusActivate" class="status-box activate-box">Active</label>
                            <input type="radio" name="is_active" id="statusDeactivate" value="0">
                            <label for="statusDeactivate" class="status-box deactivate-box">Inactive</label>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editVolunteerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        function openEditVolunteerModal(v) {
            document.getElementById('editVolunteerForm').action = v.update_url;
            document.getElementById('evName').value = v.full_name;
            document.getElementById('evPhone').value = v.phone_number ?? '';
            document.getElementById('evRole').value = v.role;
            document.getElementById(v.is_active ? 'statusActivate' : 'statusDeactivate').checked = true;
            openModal('editVolunteerModal');
        }
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/volunteer/index.blade.php ENDPATH**/ ?>