<div class="custom-modal-backdrop" id="addAnimalModal">
    <div class="custom-modal custom-modal-wide">
        <form action="{{ route('admin.animals.store') }}" method="POST" enctype="multipart/form-data" id="addAnimalForm">
            @csrf
            
            <!-- Header -->
            <div class="custom-modal-header">
                <div>
                    <h3>Add New Animal</h3>
                    <small>Fill in the details for the new shelter animal.</small>
                </div>
            </div>

            <!-- Modal Body with Interactive Tabs -->
            <div class="custom-modal-body">
                
                <!-- Interactive Step Navigation Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-2 mb-4 border-b border-gray-100 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <button type="button" class="modal-tab-pill active" id="addTabBtn-1" onclick="switchAnimalModalTab('add', 1)">
                        <i class="fa-solid fa-paw"></i>
                        <span>1. Basic Info</span>
                    </button>
                    <button type="button" class="modal-tab-pill" id="addTabBtn-2" onclick="switchAnimalModalTab('add', 2)">
                        <i class="fa-solid fa-heart-pulse"></i>
                        <span>2. Health & Status</span>
                    </button>
                    <button type="button" class="modal-tab-pill" id="addTabBtn-3" onclick="switchAnimalModalTab('add', 3)">
                        <i class="fa-solid fa-camera"></i>
                        <span>3. Story & Media</span>
                    </button>
                </div>

                <!-- STEP 1: Basic Information -->
                <div class="space-y-4" id="addTabPane-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5 sm:gap-4">
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
                            <input name="breed" class="form-control" placeholder="e.g. Mixed / Aspin" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Age*</label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input type="number" name="age_years" min="0" max="30" class="form-control pr-8" placeholder="0" required>
                                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium pointer-events-none">yrs</span>
                                </div>
                                <div class="relative flex-1">
                                    <input type="number" name="age_months" min="0" max="11" class="form-control pr-8" placeholder="0" required>
                                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium pointer-events-none">mos</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sex*</label>
                            <div class="select-wrapper">
                                <select name="sex" class="form-select" required>
                                    <option value="">Select Sex</option>
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
                                    <option value="">Select Size</option>
                                    @foreach ($options::SIZES as $s)
                                        <option>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group sm:col-span-2 md:col-span-1">
                            <label class="form-label">Intake Date*</label>
                            <input
                                name="intake_date"
                                type="date"
                                class="form-control"
                                value="{{ \App\Support\ManilaTime::now()->format('Y-m-d') }}"
                                max="{{ \App\Support\ManilaTime::now()->format('Y-m-d') }}"
                                required>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="button" class="btn btn-secondary w-full sm:w-auto" onclick="switchAnimalModalTab('add', 2)">
                            <span>Next: Health & Status</span>
                            <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Health & Status -->
                <div class="space-y-4 hidden" id="addTabPane-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5 sm:gap-4">
                        <div class="form-group">
                            <label class="form-label">Health Status*</label>
                            <div class="select-wrapper">
                                <select name="health_status" class="form-select" required>
                                    <option value="">Select Health Status</option>
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
                                    <option value="">Select Adoption Status</option>
                                    @foreach ($options::ADOPTION_STATUSES as $s)
                                        <option {{ $s === 'Assessing' ? 'selected' : '' }}>
                                             {{ $s }}
                                        </option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group sm:col-span-2 md:col-span-3">
                            <label class="form-label">Medical Needs Level</label>
                            <div class="select-wrapper">
                                <select name="medical_needs" class="form-select">
                                    <option value="">Not recorded</option>
                                    <option value="1">1 - Routine care only</option>
                                    <option value="2">2 - Minor medical care</option>
                                    <option value="3">3 - Regular medication/checkups</option>
                                    <option value="4">4 - Frequent veterinary care</option>
                                    <option value="5">5 - Intensive ongoing care</option>
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row justify-between gap-2.5 pt-2">
                        <button type="button" class="btn btn-secondary w-full sm:w-auto" onclick="switchAnimalModalTab('add', 1)">
                            <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                            <span>Back to Basic Info</span>
                        </button>
                        <button type="button" class="btn btn-secondary w-full sm:w-auto" onclick="switchAnimalModalTab('add', 3)">
                            <span>Next: Story & Media</span>
                            <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Media & Story -->
                <div class="space-y-4 hidden" id="addTabPane-3">
                    
                    <!-- Interactive Photo Upload Zone -->
                    <div class="form-group">
                        <label class="form-label">Pet Photo*</label>
                        <div class="photo-upload-zone" id="addPhotoDropzone" onclick="document.getElementById('addPhotoInput').click()">
                            <input
                                type="file"
                                id="addPhotoInput"
                                name="photo"
                                class="hidden"
                                accept="image/*"
                                onchange="handlePhotoPreview(this, 'addPhotoPreview', 'addPhotoFilename')"
                                required>
                            
                            <!-- Initial Upload UI -->
                            <div id="addPhotoPlaceholder" class="flex flex-col items-center py-2">
                                <div class="w-12 h-12 rounded-full bg-maroon-50 text-maroon-600 flex items-center justify-center text-xl mb-2">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <span class="text-sm font-semibold text-gray-800">Tap to upload or take a photo</span>
                                <span class="text-xs text-gray-400 mt-0.5">PNG, JPG, WEBP up to 5MB</span>
                            </div>

                            <!-- Live Preview Area -->
                            <div id="addPhotoPreviewContainer" class="hidden flex-col items-center gap-2 w-full">
                                <img id="addPhotoPreview" src="" alt="Photo preview" class="w-32 h-32 object-cover rounded-xl shadow-sm border border-gray-200">
                                <span id="addPhotoFilename" class="text-xs text-gray-600 font-medium truncate max-w-xs"></span>
                                <span class="text-xs text-maroon-600 font-semibold hover:underline">Tap to change photo</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pet Story / Description</label>
                        <textarea
                            name="description"
                            class="form-control resize-y min-h-[85px]"
                            rows="3"
                            placeholder="Write the pet's background story, personality, rescue journey, or favorite activities..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Behavioral / Medical Notes</label>
                        <textarea
                            name="behavioral_notes"
                            class="form-control resize-y min-h-[65px]"
                            rows="2"
                            placeholder="Optional behavioral or health observations..."></textarea>
                    </div>

                    <div class="flex justify-start pt-1">
                        <button type="button" class="btn btn-secondary w-full sm:w-auto" onclick="switchAnimalModalTab('add', 2)">
                            <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                            <span>Back to Health & Status</span>
                        </button>
                    </div>
                </div>

            </div>
            
            <!-- Sticky Modal Footer -->
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAnimalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add Animal</span>
                </button>
            </div>
        </form>
    </div>
</div>
