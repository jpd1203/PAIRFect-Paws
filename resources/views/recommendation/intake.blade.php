@extends('layouts.app')

@section('title', 'Pet Recommendation - PAIRfect Paws')

@section('content')

    <div class="nonsticky-header">
        <div class="heading-text">
            <h2>Pet Recommendation</h2>
            <p>Tell us about your lifestyle so we can find your most compatible pet.</p>
        </div>
    
        <div class="content-area-nonsticky custom-scrollbar">

            <div class="reco-banner my-3">
                This information helps tailor your compatibility results. All pairings are still reviewed
                and decided manually by shelter staff — this is a guide, not a final decision.
            </div>

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

            <form action="{{ route('recommendation.start') }}" method="POST" novalidate>
                @csrf

                <div class="form-section mt-0">

                <div class="info-card shadow-card">
                    <h3>Your Adopter Profile</h3>

                        <div class="form-grid">

                            <div class="form-group">
                                <label for="physical_activity_level">Physical Activity Level*</label>
                                <div class="select-wrapper">
                                    <select id="physical_activity_level" name="physical_activity_level" required>
                                        <option value="">Select Activity Level</option>
                                        @foreach ($options::PHYSICAL_ACTIVITY_LEVELS as $option)
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
                                        @foreach ($options::TIME_AVAILABILITY_OPTIONS as $option)
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
                                        @foreach ($options::PRIOR_EXPERIENCE_OPTIONS as $option)
                                            <option value="{{ $option }}" @selected(old('prior_pet_experience', $profile?->prior_pet_experience) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="housing_type">Housing Type*</label>
                                <div class="select-wrapper">
                                    <select id="housing_type" name="housing_type" required>
                                        <option value="">Select Housing Type</option>
                                        @foreach ($options::HOUSING_TYPES as $option)
                                            <option value="{{ $option }}" @selected(old('housing_type', $profile?->housing_type) === $option)>{{ $option }}</option>
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
                                        @foreach ($options::HOUSEHOLD_COMPOSITIONS as $option)
                                            <option value="{{ $option }}" @selected(old('household_composition', $profile?->household_composition) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="monthly_income_range">Monthly Income Range*</label>
                                <div class="select-wrapper">
                                    <select id="monthly_income_range" name="monthly_income_range" required>
                                        <option value="">Select Income Range</option>
                                        @foreach ($options::INCOME_RANGES as $option)
                                            <option value="{{ $option }}" @selected(old('monthly_income_range', $profile?->monthly_income_range) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                                </div>
                            </div>
                        </div>
                        <div class="submit-container">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-heart"></i>
                            Start Matching
                        </button>
                    </div>
                    </div>
                </div>

            </form>

        </div>
    </div>

@endsection
