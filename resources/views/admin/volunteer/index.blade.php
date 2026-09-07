@extends('admin.layouts.app')

@section('title', 'Volunteers - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Volunteers</h2>
        <p>Manage staff and volunteer accounts.</p>
    </div>

    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                showToast(@json(session('success')), 'success');
            });
        </script>
    @endif

    <div class="flex flex-wrap gap-3 items-center my-5">
        <input type="text" data-search-input data-search-scope="volunteerTableBody" class="search-input flex-1 min-w-[220px]" placeholder="Search by name or email…">
        <button class="btn btn-primary" onclick="openModal('addVolunteerModal')"><i class="fa-solid fa-users"></i> Add Volunteer</button>
    </div>

    <div class="records-container">
        <div class="table-responsive custom-scrollbar">
            <table class="w-full">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Email Verification</th><th>Actions</th></tr>
                </thead>
                <tbody id="volunteerTableBody">
                    @forelse ($volunteers as $v)
                        <tr data-search-row data-search-text="{{ $v->full_name }} {{ $v->email }}">
                            <td class="!text-left font-semibold">{{ $v->full_name }}</td>
                            <td>{{ $v->email }}</td>
                            <td><span class="badge {{ $v->role === \App\Enums\Role::Administrator ? 'badge-approved' : 'badge-scheduled' }}">{{ $v->role->value }}</span></td>
                            <td><span class="badge {{ $v->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $v->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td><span class="badge {{ $v->hasVerifiedEmail() ? 'badge-approved' : 'badge-pending' }}">{{ $v->hasVerifiedEmail() ? 'Verified' : 'Pending' }}</span></td>
                            <td>
                                @if ($v->role === \App\Enums\Role::Volunteer)
                                    <button
                                        class="btn btn-secondary btn-sm"
                                        onclick="openEditVolunteerModal({
                                            id: {{ $v->id }},
                                            first_name: @js($v->first_name),
                                            last_name: @js($v->last_name),
                                            is_active: {{ $v->is_active ? 'true' : 'false' }},
                                            update_url: @js(route('admin.volunteers.update', $v))
                                        })"><i class="fa-solid fa-pen"></i>
                                        Edit
                                    </button>
                                @else
                                    <span class="text-xs text-[#888]">Administrator</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-[#888] py-6">No volunteers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Volunteer -->
    <div class="custom-modal-backdrop" id="addVolunteerModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Add Volunteer</h2></div>
            <form action="{{ route('admin.volunteers.store') }}" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label ml-1" for="volunteer_first_name">First Name*</label>
                            <input id="volunteer_first_name" name="first_name" value="{{ old('first_name') }}" class="form-control" required>
                            @error('first_name') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label ml-1" for="volunteer_last_name">Last Name*</label>
                            <input id="volunteer_last_name" name="last_name" value="{{ old('last_name') }}" class="form-control" required>
                            @error('last_name') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group md:col-span-2">
                            <label class="form-label ml-1" for="volunteer_email">Email*</label>
                            <input id="volunteer_email" name="email" value="{{ old('email') }}" type="email" class="form-control" required>
                            <p class="text-xs text-gray-500 mt-1">Volunteers receive a verification email. Administrator accounts are verified automatically.</p>
                            @error('email') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label ml-1" for="volunteer_role">Role*</label>
                            <div class="select-wrapper">
                                <select id="volunteer_role" name="role" class="form-select" required>
                                    <option value="Volunteer" @selected(old('role', 'Volunteer') === 'Volunteer')>Volunteer</option>
                                    <option value="Administrator" @selected(old('role') === 'Administrator')>Administrator</option>
                                </select>
                                @error('role') <div class="field-error">{{ $message }}</div> @enderror
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label ml-1" for="volunteer_branch_id">Branch*</label>
                            <div class="select-wrapper">
                                <select id="volunteer_branch_id" name="branch_id" class="form-select">
                                    <option value="">None / System-wide</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_id') <div class="field-error">{{ $message }}</div> @enderror
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label ml-1" for="volunteer_password">Temporary Password*</label>
                            <input id="volunteer_password" name="password" type="password" class="form-control" minlength="8" required>
                            @error('password') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label ml-1" for="volunteer_password_confirmation">Confirm Temporary Password*</label>
                            <input id="volunteer_password_confirmation" name="password_confirmation" type="password" class="form-control" minlength="8" required>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addVolunteerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-users"></i>Add</button>
                </div>
            </form>
        </div>
    </div>
                
    <!-- Edit Volunteer -->
    <div class="custom-modal-backdrop" id="editVolunteerModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Edit Volunteer</h2></div>
            <form id="editVolunteerForm" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_volunteer_editing" value="1">
                <input type="hidden" id="evUserId" name="edit_user_id">
                <div class="custom-modal-body">
                    <div class="grid grid-cols-2 gap-4 max-[768px]:grid-cols-1">
                        <div class="form-group">
                            <label class="form-label" for="evFirstName">First Name</label>
                            <input id="evFirstName" name="first_name" class="form-control" required maxlength="255">
                            @if (old('_volunteer_editing')) @error('first_name') <div class="field-error">{{ $message }}</div> @enderror @endif
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="evLastName">Last Name</label>
                            <input id="evLastName" name="last_name" class="form-control" required maxlength="255">
                            @if (old('_volunteer_editing')) @error('last_name') <div class="field-error">{{ $message }}</div> @enderror @endif
                        </div>
                        <div class="form-group col-span-2 max-[768px]:col-span-1">
                            <p class="rounded-lg border border-[#ddd] bg-[#fafafa] px-3 py-2 text-sm text-[#666]">
                                Role: <strong>Volunteer</strong>. Administrator accounts are managed separately.
                            </p>
                        </div>
                        <div class="form-group col-span-2 max-[768px]:col-span-1">
                            <label class="form-label">Status</label>
                            <div class="status-options">
                                <input type="radio" name="is_active" id="statusActivate" value="1" required>
                                <label for="statusActivate" class="status-box activate-box"><i class="fa-solid fa-circle-check mr-1x.com"></i>Active</label>
                                <input type="radio" name="is_active" id="statusDeactivate" value="0">
                                <label for="statusDeactivate" class="status-box deactivate-box"><i class="fa-solid fa-circle-xmark mr-1"></i>Inactive</label>
                            </div>
                            @if (old('_volunteer_editing')) @error('is_active') <div class="field-error">{{ $message }}</div> @enderror @endif
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editVolunteerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        @if ($errors->any() && !old('_volunteer_editing'))
            document.addEventListener('DOMContentLoaded', () => openModal('addVolunteerModal'));
        @endif

        function openEditVolunteerModal(v) {
            document.getElementById('editVolunteerForm').action = v.update_url;
            document.getElementById('evUserId').value = v.id;
            document.getElementById('evFirstName').value = v.first_name;
            document.getElementById('evLastName').value = v.last_name;
            document.getElementById(v.is_active ? 'statusActivate' : 'statusDeactivate').checked = true;
            openModal('editVolunteerModal');
        }

        @if ($errors->any() && old('_volunteer_editing') && old('edit_user_id'))
            document.addEventListener('DOMContentLoaded', () => openEditVolunteerModal({
                id: @js(old('edit_user_id')),
                first_name: @js(old('first_name')),
                last_name: @js(old('last_name')),
                is_active: @js((string) old('is_active') === '1'),
                update_url: @js(route('admin.volunteers.update', ['user' => old('edit_user_id')])),
            }));
        @endif
    </script>
@endpush
