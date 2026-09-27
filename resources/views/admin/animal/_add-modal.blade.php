<div class="custom-modal-backdrop" id="addAnimalModal">
    <div class="custom-modal custom-modal-wide">
        <form action="{{ route('admin.animals.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
        <div class="custom-modal-header">
            <h3>Add New Animal</h3>
            <small>Fill in the details for the new shelter animal.</small>
        </div>
            <div class="custom-modal-body">
                <div class="grid grid-cols-3 gap-4 max-[768px]:grid-cols-1">

                    <!-- Basic Information -->
                    <!-- <div class="col-span-3">
                        <h4 class="text-lg font-semibold text-text-dark">
                            Basic Information
                        </h4>
                    </div> -->

                    <div class="form-group">
                        <label class="form-label">Name*</label>
                        <input name="name" class="form-control" placeholder="e.g. Mochi" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Species*</label>
                        <div class="select-wrapper">
                            <select name="species" class="form-select" required>
                                <option value="">Select Species</option>
                                @foreach ($options::SPECIES as $s)
                                    <option>{{ $s }}</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                         </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Breed*</label>
                        <input name="breed" class="form-control" placeholder="e.g. Mixed" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Age*</label>

                        <div class="flex gap-2">
                            <input type="number" name="age_years" min="0" max="30" class="form-control flex-1" placeholder="Years" required>
                            <input type="number" name="age_months" min="0" max="11" class="form-control flex-1" placeholder="Months" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sex*</label>
                        <div class="select-wrapper">
                            <select name="sex" class="form-select" required>
                                <option value="">Select</option>
                                <option>Male</option>
                                <option>Female</option>
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Physical Size*</label>
                        <div class="select-wrapper">
                            <select name="physical_size" class="form-select" required>
                                <option value="">Select</option>
                                @foreach ($options::SIZES as $s)
                                    <option>{{ $s }}</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Intake Date*</label>
                        <input
                            name="intake_date"
                            type="date"
                            class="form-control"
                            value="{{ \App\Support\ManilaTime::now()->format('Y-m-d') }}"
                            max="{{ \App\Support\ManilaTime::now()->format('Y-m-d') }}"
                            required>
                    </div>

                    <!-- Health Status -->
                    <!-- <div class="col-span-3 mt-2">
                        <h4 class="text-lg font-semibold text-text-dark">
                            Health Status
                        </h4>
                    </div> -->

                    <div class="form-group">
                        <label class="form-label">Health Status*</label>
                        <div class="select-wrapper">
                            <select name="health_status" class="form-select" required>
                                <option value="">Select</option>
                                @foreach ($options::HEALTH_STATUSES as $h)
                                    <option>{{ $h }}</option>
                                @endforeach
                            </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Vaccination Status</label>
                        <div class="select-wrapper">
                            <select name="vaccination_record_status" class="form-select">
                                <option>Complete</option>
                                <option>Incomplete</option>
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adoption Status*</label>
                        <div class="select-wrapper">
                            <select name="status" class="form-select" required>
                                <option value="">Select</option>
                                @foreach ($options::ADOPTION_STATUSES as $s)
                                    <option {{ $s === 'Assessing' ? 'selected' : '' }}>
                                        {{ $s }}
                                    </option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pet Photo*</label>
                        <input
                            type="file"
                            name="photo"
                            class="form-control"
                            accept="image/*"
                            required>
                    </div>

                    <div class="form-group col-span-3">
                        <label class="form-label">Pet Story / Description</label>
                        <textarea
                            name="description"
                            class="form-control"
                            rows="3"
                            placeholder="Write the pet's background story, personality, or rescue journey..."></textarea>
                    </div>

                    <div class="form-group col-span-3">
                        <label class="form-label">Behavioral Notes</label>
                        <textarea
                            name="notes"
                            class="form-control"
                            rows="2"
                            placeholder="Optional medical/behavioral notes..."></textarea>
                    </div>

                </div>
            </div>
            
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAnimalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i>Add Animal</button>
            </div>
        </form>
    </div>
</div>
