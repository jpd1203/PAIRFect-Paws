<div class="custom-modal-backdrop" id="viewAnimalModal">
    <div class="custom-modal custom-modal-wide">
        <div class="custom-modal-header">
            <h3 id="viewAnimalTitle">Animal Profile</h3>
            <p>Animal's profile information</p>
        </div>
        <form id="viewAnimalForm" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="version" id="vVersion">
            <div class="custom-modal-body">
                <div class="grid grid-cols-3 gap-4 max-[768px]:grid-cols-1">

                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input id="vName" name="name" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Species</label>
                        <div class="select-wrapper">
                            <select id="vSpecies" name="species" class="form-select">
                                @foreach ($options::SPECIES as $s)
                                    <option>{{ $s }}</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                         </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Breed</label>
                        <input id="vBreed" name="breed" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Age</label>

                        <div class="flex gap-2">
                            <input id="vAgeYears" name="age_years" type="number" class="form-control flex-1" placeholder="Years">
                            <input id="vAgeMonths" name="age_months" type="number" class="form-control flex-1" placeholder="Months">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sex</label>
                        <div class="select-wrapper">
                            <select id="vSex" name="sex" class="form-select">
                                <option>Male</option><option>Female</option>
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Intake Date</label>
                        <input id="vIntake" name="intake_date" type="date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Health Status</label>
                        <div class="select-wrapper">
                            <select id="vHealth" name="health_status" class="form-select">
                                @foreach ($options::HEALTH_STATUSES as $h)
                                    <option>{{ $h }}</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adoption Status</label>
                        <div class="select-wrapper">
                            <select id="vStatus" name="status" class="form-select">
                                @foreach ($options::ADOPTION_STATUSES as $s)
                                    <option>{{ $s }}</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Physical Size</label>
                        <div class="select-wrapper">
                            <select id="vSize" name="physical_size" class="form-select">
                                @foreach ($options::SIZES as $s)
                                    <option>{{ $s }}</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Vaccination Status</label>
                        <div class="select-wrapper">
                            <select id="vVacc" name="vaccination_record_status" class="form-select">
                                <option>Complete</option>
                                <option>Incomplete</option>
                            </select>
                            <i class="fa-solid fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Medical Needs Level</label>
                        <div class="select-wrapper">
                            <select id="vMedical" name="medical_needs" class="form-select">
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

                    <div class="form-group">
                        <label class="form-label">Replace Photo (optional)</label>
                        <img id="vPhotoPreview" src="" style="height:80px;border-radius:6px;margin-bottom:0.5rem;display:none">
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>

                    <div class="form-group col-span-3">
                        <label class="form-label">Notes</label>
                        <textarea id="vNotes" name="behavioral_notes" class="form-control" rows="1"></textarea>
                    </div>

                    <div class="form-group col-span-3">
                        <label class="form-label">Pet Assessment</label>
                        <div class="flex items-center justify-between gap-3 border border-[#ddd] rounded-lg px-4 py-3 bg-neutral-light">
                            <span class="text-[.88rem]">
                                <span id="vAssessBadge" class="badge badge-pending">Pending</span>
                                <span id="vAssessCount" class="text-[#777] ml-2">0/3 assessments completed</span>
                            </span>
                            <a id="vAssessBtn" href="#" class="btn btn-secondary btn-sm">Edit Pet Assessment</a>
                        </div>
                    </div>

                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewAnimalModal')">Close</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>
