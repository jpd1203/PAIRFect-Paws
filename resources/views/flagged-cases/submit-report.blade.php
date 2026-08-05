@extends('layouts.app')

@section('title', 'Submit Post-Adoption Report - PAIRfect Paws')

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Submit Post-Adoption Report</h2>
            <p>Complete your scheduled welfare check-in{{ $pet ? " for {$pet->name}" : '' }}.</p>
        </div>
    </div>

    <div class="content-area">

        <div class="contact-card">
            <p>
                Pending check-in: <strong>{{ $checkIn?->milestone_report_label }}</strong> for <strong>{{ $pet?->name }}</strong>. Please complete and submit below.
            </p>
        </div>

        <form id="submitReportForm" action="{{ route('flagged.previewReport') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            <input type="hidden" name="check_in_id" value="{{ $checkIn?->id }}">
            <input type="hidden" name="pet_id" value="{{ $pet?->id }}">
            <input type="hidden" name="milestone" value="{{ $checkIn?->milestone }}">

            <div class="form-section">

                <h3>Check-in Details</h3>

                <div class="form-grid">

                    <div class="form-group">
                        <label>Adopted Pet</label>
                        <input type="text" value="{{ $pet?->name }}" disabled>
                    </div>

                    <div class="form-group">
                        <label>Milestone</label>
                        <input type="text" value="{{ $checkIn?->milestone_display }}" disabled>
                    </div>

                    <div class="form-group">
                        <label>Adopter Name</label>
                        <input type="text" value="{{ $adopter?->full_name }}" disabled>
                    </div>

                    <div class="form-group">
                        <label>Report Date</label>
                        <input type="text" value="{{ now()->format('F j, Y') }}" disabled>
                    </div>
                </div>
            </div>

            <!-- PET CONDITION -->
            <div class="form-section">

                <h3>Pet's Current Condition</h3>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="healthStatus">Overall Health Status</label>
                        <select id="healthStatus" name="health_status" required>
                            <option value="">Select Health Status</option>
                            @foreach (\App\Support\ReportOptions::HEALTH_STATUSES as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="eatingHabits">Eating &amp; Drinking Habits</label>
                        <select id="eatingHabits" name="eating_and_drinking" required>
                            <option value="">Select Habits</option>
                            @foreach (\App\Support\ReportOptions::EATING_HABITS as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="behavior">Behavior at Home</label>
                        <select id="behavior" name="behavior" required>
                            <option value="">Select Behavior</option>
                            @foreach (\App\Support\ReportOptions::BEHAVIORS as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="livingConditions">Living Conditions</label>
                        <select id="livingConditions" name="living_conditions" required>
                            <option value="">Select Living Condition</option>
                            @foreach (\App\Support\ReportOptions::LIVING_CONDITIONS as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Veterinary visit</label>
                        <div class="radio-group">
                            <label><input type="radio" name="vet_visit" value="1" required> Yes</label>
                            <label><input type="radio" name="vet_visit" value="0"> No</label>
                        </div>
                    </div>
                </div>

                <div class="form-section">

                    <h3>Concerns &amp; Notes</h3>

                    <div class="form-group full-width">
                        <label for="concerns">Any concerns to raise?</label>
                        <textarea id="concerns" name="concerns" rows="5"></textarea>
                    </div>
                </div>

                <!-- DOCUMENT -->
                <div class="form-section">

                    <div class="form-group">
                        <label>
                            Upload a photo of your pet
                        </label>

                        <input type="file" name="photo" accept="image/*">
                        <small>We love seeing how they're doing!</small>
                    </div>

                </div>

                <div class="submit-container">
                    <button type="submit" class="btn btn-primary">
                        Submit Report
                    </button>
                </div>
            </div>

        </form>

    </div>

    <!-- Confirm Report Modal -->
    <div class="modal-overlay" id="confirmReportModal">
        <div class="pet-modal report-modal" id="confirmReportModalContent">
            <!-- Filled dynamically via fetch() after preview -->
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/submit-report.js') }}" defer></script>
@endpush
