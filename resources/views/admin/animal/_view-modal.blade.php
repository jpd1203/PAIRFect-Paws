<div class="custom-modal-backdrop" id="viewAnimalModal">
    <div class="custom-modal custom-modal-wide">
        <form id="viewAnimalForm" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="version" id="vVersion">

            <!-- Header -->
            <div class="custom-modal-header">
                <div class="min-w-0 pr-2">
                    <h3 id="viewAnimalTitle" class="truncate max-w-[200px] sm:max-w-md font-bold text-gray-900">Animal Profile</h3>
                    <small class="text-xs text-gray-500">View and update animal details.</small>
                </div>
            </div>

            <!-- Modal Body with Interactive Tabs -->
            <div class="custom-modal-body">

                <!-- Interactive Step Navigation Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-2 mb-4 border-b border-gray-100 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <button type="button" class="modal-tab-pill active" id="viewTabBtn-1" onclick="switchAnimalModalTab('view', 1)">
                        <i class="fa-solid fa-paw"></i>
                        <span>1. Basic Info</span>
                    </button>
                    <button type="button" class="modal-tab-pill" id="viewTabBtn-2" onclick="switchAnimalModalTab('view', 2)">
                        <i class="fa-solid fa-heart-pulse"></i>
                        <span>2. Health & Status</span>
                    </button>
                    <button type="button" class="modal-tab-pill" id="viewTabBtn-3" onclick="switchAnimalModalTab('view', 3)">
                        <i class="fa-solid fa-camera"></i>
                        <span>3. Story & Media</span>
                    </button>
                </div>

                <!-- STEP 1: Basic Information -->
                <div class="space-y-4" id="viewTabPane-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5 sm:gap-4">
                        <div class="form-group">
                            <label class="form-label" for="vName">Name*</label>
                            <input id="vName" name="name" class="form-control" placeholder="Animal name" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vSpecies">Species*</label>
                            <div class="select-wrapper">
                                <select id="vSpecies" name="species" class="form-select" required>
                                    @foreach ($options::SPECIES as $s)
                                        <option>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vBreed">Breed</label>
                            <input id="vBreed" name="breed" class="form-control" placeholder="e.g. Domestic Short Hair">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Age</label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input id="vAgeYears" name="age_years" type="number" min="0" max="30" class="form-control pr-8" placeholder="0">
                                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium pointer-events-none">yrs</span>
                                </div>
                                <div class="relative flex-1">
                                    <input id="vAgeMonths" name="age_months" type="number" min="0" max="11" class="form-control pr-8" placeholder="0">
                                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium pointer-events-none">mos</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vSex">Sex</label>
                            <div class="select-wrapper">
                                <select id="vSex" name="sex" class="form-select">
                                    <option>Male</option>
                                    <option>Female</option>
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group sm:col-span-2 md:col-span-1">
                            <label class="form-label" for="vIntake">Intake Date</label>
                            <input id="vIntake" name="intake_date" type="date" class="form-control">
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="button" class="btn btn-secondary w-full sm:w-auto" onclick="switchAnimalModalTab('view', 2)">
                            <span>Next: Health & Status</span>
                            <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Health & Status -->
                <div class="space-y-4 hidden" id="viewTabPane-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5 sm:gap-4">
                        <div class="form-group">
                            <label class="form-label" for="vHealth">Health Status*</label>
                            <div class="select-wrapper">
                                <select id="vHealth" name="health_status" class="form-select" required>
                                    @foreach ($options::HEALTH_STATUSES as $h)
                                        <option>{{ $h }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vVacc">Vaccination Status</label>
                            <div class="select-wrapper">
                                <select id="vVacc" name="vaccination_record_status" class="form-select">
                                    <option>Complete</option>
                                    <option>Incomplete</option>
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vStatus">Adoption Status*</label>
                            <div class="select-wrapper">
                                <select id="vStatus" name="status" class="form-select" required>
                                    @foreach ($options::ADOPTION_STATUSES as $s)
                                        <option>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group sm:col-span-2 md:col-span-3">
                            <label class="form-label" for="vMedical">Medical Needs Level</label>
                            <div class="select-wrapper">
                                <select id="vMedical" name="medical_needs" class="form-select">
                                    <option value="">Not yet veterinary-assessed</option>
                                    <option value="1">1 - Routine care only</option>
                                    <option value="2">2 - Minor medical care</option>
                                    <option value="3">3 - Regular medication/checkups</option>
                                    <option value="4">4 - Frequent veterinary care</option>
                                    <option value="5">5 - Intensive ongoing care</option>
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Use the veterinary health evaluation. Required before matching.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vSize">Physical Size</label>
                            <div class="select-wrapper">
                                <select id="vSize" name="physical_size" class="form-select">
                                    <option value="">Not yet veterinary-assessed</option>
                                    @foreach (array_keys(config('matching.size_levels')) as $size)
                                        <option value="{{ $size }}">{{ $size }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Based on veterinary assessment. Required before matching.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vLifeStage">Life Stage</label>
                            <div class="select-wrapper">
                                <select id="vLifeStage" name="life_stage" class="form-select">
                                    <option value="">Not yet verified</option>
                                    <option value="young">Young</option>
                                    <option value="adult">Adult</option>
                                    <option value="senior">Senior</option>
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vAggression">Documented Aggression History</label>
                            <div class="select-wrapper">
                                <select id="vAggression" name="has_aggression_history" class="form-select">
                                    <option value="">Not yet assessed / Unknown</option>
                                    <option value="0">No documented aggression history</option>
                                    <option value="1">Yes, documented aggression history</option>
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Select No only after checking the animal's documented history.</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="vVocalization">High Vocalization</label>
                            <div class="select-wrapper">
                                <select id="vVocalization" name="high_vocalization" class="form-select">
                                    <option value="">Not yet assessed / Unknown</option>
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row justify-between gap-2.5 pt-2">
                        <button type="button" class="btn btn-secondary w-full sm:w-auto" onclick="switchAnimalModalTab('view', 1)">
                            <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                            <span>Back to Basic Info</span>
                        </button>
                        <button type="button" class="btn btn-secondary w-full sm:w-auto" onclick="switchAnimalModalTab('view', 3)">
                            <span>Next: Story & Media</span>
                            <i class="fa-solid fa-arrow-right ml-1.5 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Media, Story & Assessment -->
                <div class="space-y-4 hidden" id="viewTabPane-3">
                    
                    <!-- Interactive Photo Upload Zone -->
                    <div class="form-group">
                        <label class="form-label">Pet Photo</label>
                        <div class="photo-upload-zone" id="viewPhotoDropzone" onclick="document.getElementById('viewPhotoInput').click()">
                            <input
                                type="file"
                                id="viewPhotoInput"
                                name="photo"
                                class="hidden"
                                accept="image/*"
                                onchange="handlePhotoPreview(this, 'vPhotoPreview', 'viewPhotoFilename')">
                            
                            <!-- Placeholder when no photo is attached -->
                            <div id="viewPhotoPlaceholder" class="flex flex-col items-center py-2">
                                <div class="w-12 h-12 rounded-full bg-maroon-50 text-maroon-600 flex items-center justify-center text-xl mb-2">
                                    <i class="fa-solid fa-camera"></i>
                                </div>
                                <span class="text-sm font-semibold text-gray-800">Tap to upload or take a photo</span>
                                <span class="text-xs text-gray-400 mt-0.5">PNG, JPG, WEBP up to 5MB</span>
                            </div>

                            <!-- Live / Current Preview Area -->
                            <div id="viewPhotoPreviewContainer" class="hidden flex flex-col items-center gap-2 w-full">
                                <img id="vPhotoPreview" src="" alt="Animal photo" class="w-32 h-32 object-cover rounded-xl shadow-sm border border-gray-200">
                                <span id="viewPhotoFilename" class="text-xs text-gray-600 font-medium truncate max-w-xs"></span>
                                <span class="text-xs text-maroon-600 font-semibold hover:underline">Tap to change photo</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="vDescription">Story / Description</label>
                        <textarea
                            id="vDescription"
                            name="description"
                            class="form-control resize-y min-h-[85px]"
                            rows="3"
                            placeholder="Tell the pet's story, background, and personality..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="vNotes">Behavioral / Medical Notes</label>
                        <textarea
                            id="vNotes"
                            name="behavioral_notes"
                            class="form-control resize-y min-h-[65px]"
                            rows="2"
                            placeholder="Observations, behavioral notes, or temperament details..."></textarea>
                    </div>

                    <!-- Pet Assessment Summary Box -->
                    <div class="form-group">
                        <label class="form-label">Pet Assessment</label>
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border border-gray-200 rounded-xl p-3.5 bg-neutral-light">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span id="vAssessBadge" class="badge badge-pending">Pending</span>
                                <span id="vAssessCount" class="text-xs sm:text-sm text-gray-600">0/3 assessments completed</span>
                            </div>
                            <a id="vAssessBtn" href="#" class="btn btn-secondary btn-sm w-full sm:w-auto text-center">
                                <i class="fa-solid fa-clipboard-check mr-1"></i>
                                <span>Edit Assessment</span>
                            </a>
                        </div>
                    </div>

                    <div class="flex justify-start pt-1">
                        <button type="button" class="btn btn-secondary w-full sm:w-auto" onclick="switchAnimalModalTab('view', 2)">
                            <i class="fa-solid fa-arrow-left mr-1.5 text-xs"></i>
                            <span>Back to Health & Status</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- Sticky Modal Footer -->
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewAnimalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>
