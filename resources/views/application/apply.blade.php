@extends('layouts.app')

@section('title', "Apply to Adopt {$pet->name} - PAIRfect Paws")

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Apply to Adopt {{ $pet->name }}</h2>
            <p>Complete all fields to submit your application</p>
        </div>
    </div>

    <div class="content-area">

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
                        <label for="first_name">First Name *</label>
                        <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number *</label>
                        <input id="phone_number" name="phone_number" type="text" value="{{ old('phone_number') }}" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">Address *</label>
                        <input id="address" name="address" type="text" value="{{ old('address') }}" required>
                    </div>

                </div>

            </div>

            <!-- ADOPTER PROFILE -->
            <div class="form-section !mt-6">

                <h3 class="!mb-3">Adopter Profile</h3>

                <div class="form-grid !gap-y-3">

                    <div class="form-group">
                        <label for="physical_activity_level">Physical Activity Level *</label>
                        <select id="physical_activity_level" name="physical_activity_level" required>
                            <option value="">Select Activity Level</option>
                            @foreach (\App\Support\ApplicationOptions::PHYSICAL_ACTIVITY_LEVELS as $option)
                                <option value="{{ $option }}" @selected(old('physical_activity_level') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="time_availability">Time Availability *</label>
                        <select id="time_availability" name="time_availability" required>
                            <option value="">Select Time Availability</option>
                            @foreach (\App\Support\ApplicationOptions::TIME_AVAILABILITY_OPTIONS as $option)
                                <option value="{{ $option }}" @selected(old('time_availability') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prior_pet_experience">Prior Pet Experience? *</label>
                        <select id="prior_pet_experience" name="prior_pet_experience" required>
                            <option value="">Select Experience</option>
                            @foreach (\App\Support\ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS as $option)
                                <option value="{{ $option }}" @selected(old('prior_pet_experience') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="housing_type">Housing Type *</label>
                        <select id="housing_type" name="housing_type" required>
                            <option value="">Select Housing Type</option>
                            @foreach (\App\Support\ApplicationOptions::HOUSING_TYPES as $option)
                                <option value="{{ $option }}" @selected(old('housing_type') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="household_composition">Household Composition *</label>
                        <select id="household_composition" name="household_composition" required>
                            <option value="">Select Household Composition</option>
                            @foreach (\App\Support\ApplicationOptions::HOUSEHOLD_COMPOSITIONS as $option)
                                <option value="{{ $option }}" @selected(old('household_composition') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="monthly_income_range">Monthly Income Range *</label>
                        <select id="monthly_income_range" name="monthly_income_range" required>
                            <option value="">Select Income Range</option>
                            @foreach (\App\Support\ApplicationOptions::INCOME_RANGES as $option)
                                <option value="{{ $option }}" @selected(old('monthly_income_range') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

            </div>

            <!-- DOCUMENT -->
            <div class="form-section">

                <div class="form-group">
                    <label>
                        Upload Valid ID / Proof of Residence *
                    </label>

                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small class="text-[.72rem] text-[#999]">PDF, JPG, or PNG — max 5MB.</small>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="agreement" name="agreed_to_animal_welfare_act" value="1" required>

                    <label for="agreement">
                        I agree to comply with Republic Act No. 8485
                        (Animal Welfare Act) and provide proper care
                        for the adopted pet.
                    </label>
                </div>

            </div>

            <div class="submit-container">
                <button type="submit" class="btn btn-primary">
                    Submit Application
                </button>
            </div>

        </form>

    </div>

@endsection
