@extends('layouts.app')

@section('title', "Apply to Adopt {$pet->name} - PAIRfect Paws")

@section('content')

    <div class="nonsticky-header custom-scrollbar">
        <div class="heading-text">
            <h2>Apply to Adopt {{ $pet->name }}</h2>
            <p>Complete all fields to submit your application</p>
        </div>

        <div class="content-area custom-scrollbar">

            <section class="form-section !mt-4" aria-label="Personality Assessment">
                <h3>Personality Assessment</h3>
                <p>✓ Personality questionnaire completed. Last updated: {{ $profile->bfi_completed_at?->format('F j, Y') }}.</p>
                <p>Your saved assessment and the pet's behavioral profile determine compatibility when you submit. Shelter staff make the final adoption decision.</p>
                <p>Matching household: {{ $profile->housing_type }}; {{ $profile->has_existing_pets ? 'existing pets' : 'no existing pets' }}; {{ $profile->has_children ? 'children at home' : 'no children at home' }}. Your saved income range informs care-capacity screening.</p>
                <a class="btn btn-secondary mt-3" href="{{ route('recommendation.intake', ['return_pet' => $pet->id]) }}">Review / Update Assessment</a>
            </section>
            @if (session('success'))<p class="mb-4 text-green-800">{{ session('success') }}</p>@endif
            @if (old('motivation_statement'))<p class="mb-4 text-amber-800">Your entered details were restored. Please reattach your supporting document before submitting.</p>@endif

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-status-danger-text bg-status-danger-bg px-4 py-3 text-status-danger-text text-sm">
                    <strong>Please fix the following:</strong>
                    <ul class="list-disc ml-5 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('application.submit') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf
                <input type="hidden" name="pet_id" value="{{ $pet->id }}">

                <!-- PERSONAL INFORMATION -->
                <div class="form-section !mt-4">

                    <h3 class="!mb-3">Personal Information</h3>

                    <div class="form-grid !gap-y-3">

                        <div class="form-group">
                            <label for="first_name">First Name*</label>
                            <input id="first_name" name="first_name" type="text" value="{{ old('first_name', auth()->user()->first_name) }}" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name*</label>
                            <input id="last_name" name="last_name" type="text" value="{{ old('last_name', auth()->user()->last_name) }}" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email*</label>
                            <input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required>
                        </div>

                        <div class="form-group">
                            <label for="phone_number">Phone Number*</label>
                            <input id="phone_number" name="phone_number" type="text" value="{{ old('phone_number') }}" required>
                        </div>

                        <x-philippine-address-fields
                            :address="auth()->user()->address_components"
                            id-prefix="application_address"
                        />

                    </div>

                </div>

                <!-- ADOPTER PROFILE -->
                <div class="form-section !mt-6">

                    <h3 class="!mb-3">Adopter Profile</h3>

                    <div class="form-grid !gap-y-3">

                        <div class="form-group">
                            <label for="physical_activity_level">Physical Activity Level*</label>
                            <div class="select-wrapper">
                                <select id="physical_activity_level" name="physical_activity_level" required>
                                    <option value="">Select Activity Level</option>
                                    @foreach (\App\Support\ApplicationOptions::PHYSICAL_ACTIVITY_LEVELS as $option)
                                        <option value="{{ $option }}" @selected(old('physical_activity_level', $profile?->physical_activity_level) === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="time_availability">Time Availability*</label>
                            <div class="select-wrapper">
                                <select id="time_availability" name="time_availability" required>
                                    <option value="">Select Time Availability</option>
                                    @foreach (\App\Support\ApplicationOptions::TIME_AVAILABILITY_OPTIONS as $option)
                                        <option value="{{ $option }}" @selected(old('time_availability', $profile?->time_availability) === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="prior_pet_experience">Prior Pet Experience*</label>
                            <div class="select-wrapper">
                                <select id="prior_pet_experience" name="prior_pet_experience" required>
                                    <option value="">Select Experience</option>
                                    @foreach (\App\Support\ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS as $option)
                                        <option value="{{ $option }}" @selected(old('prior_pet_experience', $profile?->prior_pet_experience) === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="household_composition">Household Composition*</label>
                            <div class="select-wrapper">
                                <select id="household_composition" name="household_composition" required>
                                    <option value="">Select Household Composition</option>
                                    @foreach (\App\Support\ApplicationOptions::HOUSEHOLD_COMPOSITIONS as $option)
                                        <option value="{{ $option }}" @selected(old('household_composition', $profile?->household_composition) === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down select-arrow"></i>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- DOCUMENT & STATEMENT -->
                <div class="form-section">

                    <div class="form-group full-width mb-4">
                        <label for="motivation_statement">Why do you want to adopt this pet? (Motivation Statement) *</label>
                        <textarea id="motivation_statement" name="motivation_statement" rows="4" required>{{ old('motivation_statement') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            Upload Government ID Showing Your Name and Residential Address*
                        </label>

                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                        <p class="mt-2 text-[.72rem] text-[#777]">For automatic verification, use a Philippine National ID or LTO driver's license showing your full name and current residential address. Passports, bills, and other proof-of-address documents are not eligible for automatic verification.</p>
                        <small class="text-[.72rem] text-[#777]">PDF, JPG, or PNG — max 10MB. OCR extracts text only; PAIRfect Paws does not use facial recognition or biometric matching.</small>
                        <p class="mt-2 text-[.72rem] text-[#777]">Your document and extracted text are stored privately and are accessible only to authorized shelter personnel.</p>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="agreement_terms" name="agreed_to_terms" value="1" required>
                        <label for="agreement_terms">
                            I have read, understood, and agree to the terms of the 
                            <a href="javascript:void(0)" onclick="openTermsModal()" style="color: var(--primary-color); text-decoration: underline;">Adoption and Data Processing Agreement</a>.
                        </label>
                    </div>

                </div>

                <div class="submit-container">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i>
                        Submit Application
                    </button>
                </div>

            </form>

        </div>
    </div>  


    <div class="modal-overlay" id="termsModal">
        <div class="pet-modal report-modal" style="max-width: 600px; padding: 30px; text-align: left; background: #fff; border-radius: 8px;">
            <div class="modal-header" style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #333; font-weight: bold;">Adoption and Data Processing Agreement</h3>
            </div>
            <div class="modal-body custom-scrollbar" style="max-height: 50vh; overflow-y: auto; font-size: 0.95rem; line-height: 1.6; color: #555; padding-right: 10px;">
                <p>By submitting this application, you are making a formal commitment to the welfare of the animal and agreeing to our shelter protocols. Please read and agree to the following terms:</p>
                <br>
                <p><strong>1. Commitment to Animal Welfare</strong><br>
                I agree to comply with Republic Act No. 8485 (The Animal Welfare Act of 1998) and provide humane treatment, proper nutrition, clean water, and a safe living environment for the adopted pet throughout its life.</p>
                <br>
                <p><strong>2. Veterinary and Medical Care</strong><br>
                I agree to take full responsibility for all routine and emergency veterinary care, including keeping vaccinations up to date and providing preventative treatments as recommended by a licensed veterinarian.</p>
                <br>
                <p><strong>3. Post-Adoption Monitoring</strong><br>
                I understand that adoption is a continuing responsibility. I agree to fully comply with the shelter's post-adoption monitoring requirements and will submit accurate digital welfare reports, including current photos or videos of the pet, at the scheduled 3-day, 3-week, and 3-month milestones.</p>
                <br>
                <p><strong>4. Right of Return</strong><br>
                I agree that if I can no longer care for the adopted pet for any reason, I will return the animal directly to the shelter. I will not sell, give away, abandon, or surrender the pet to any other facility or individual.</p>
                <br>
                <p><strong>5. Liability Waiver</strong><br>
                I acknowledge that animal behavior can be unpredictable. I release the shelter, its administrators, and its volunteers from any legal or financial liability for any property damage, medical costs, or injuries caused by the pet after the adoption is finalized.</p>
                <br>
                <p><strong>6. Right of Confiscation</strong><br>
                I understand that the shelter reserves the right to reclaim the pet if there is clear evidence of neglect, abuse, or a direct violation of this agreement or local animal welfare laws.</p>
                <br>
                <p><strong>7. Data Privacy and Algorithmic Matching</strong><br>
                In compliance with Republic Act No. 10173 (Data Privacy Act of 2012), I consent to the collection and secure storage of my personal information, lifestyle details, and submitted identification documents. I explicitly agree to let the system use my adopter profile data in an automated pet matching calculation to help shelter staff find the most compatible animal for my household.</p>
            </div>
            <div class="modal-actions" style="margin-top: 25px; text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="closeTermsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openTermsModal() {
            document.getElementById("termsModal").classList.add("show");
        }
        function closeTermsModal() {
            document.getElementById("termsModal").classList.remove("show");
        }
        document.getElementById("termsModal")?.addEventListener("click", (e) => {
            if (e.target.id === "termsModal") closeTermsModal();
        });
    </script>

@endsection
