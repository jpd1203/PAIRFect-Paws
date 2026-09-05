@extends('layouts.app')
@section('title', 'Submit Welfare Report')

@php
    $milestoneValue = $log->milestone?->value ?? (string) $log->milestone;
    $milestoneLabel = match ($milestoneValue) {
        'ThreeDays', '3_days' => '3-Day',
        'ThreeWeeks', '3_weeks' => '3-Week',
        'ThreeMonths', '3_months' => '3-Month',
        default => 'Post-Adoption',
    };
@endphp

@section('content')
<div class="post-adoption-report-page">
    <div class="page-header">
        <h1>Submit Welfare Report</h1>
        <p>{{ $milestoneLabel }} check-in for <strong>{{ $log->adoptionApplication->pet->name }}</strong></p>
    </div>

    <div class="card post-adoption-report-card">
        <form
            method="POST"
            action="{{ route('monitoring.submit', $log) }}"
            enctype="multipart/form-data"
            data-post-adoption-camera-form
            data-success-url="{{ route('monitoring.my-checkins') }}"
            data-camera-challenge-url="{{ route('monitoring.capture-challenge', $log) }}"
        >
            @csrf
            <input type="hidden" name="camera_captured_at" value="" data-camera-captured-at>
            <input type="hidden" name="recording_duration_ms" value="" data-camera-recording-duration>

            <div class="form-group">
                <label for="pet_current_status">Current Status of {{ $log->adoptionApplication->pet->name }} *</label>
                <select id="pet_current_status" name="pet_current_status" required>
                    <option value="">Select&hellip;</option>
                    <option value="Good" {{ old('pet_current_status') === 'Good' ? 'selected' : '' }}>Good</option>
                    <option value="Fair" {{ old('pet_current_status') === 'Fair' ? 'selected' : '' }}>Fair</option>
                    <option value="Poor" {{ old('pet_current_status') === 'Poor' ? 'selected' : '' }}>Poor</option>
                </select>
                <div class="field-error" data-validation-for="pet_current_status">@error('pet_current_status'){{ $message }}@enderror</div>
            </div>

            <div class="form-group">
                <label for="living_conditions">Living Conditions *</label>
                <textarea id="living_conditions" name="living_conditions" rows="3" required placeholder="Describe where the pet sleeps and how much space they have.">{{ old('living_conditions') }}</textarea>
                <div class="field-error" data-validation-for="living_conditions">@error('living_conditions'){{ $message }}@enderror</div>
            </div>

            <div class="form-group">
                <label for="eating_habits">Eating Habits *</label>
                <textarea id="eating_habits" name="eating_habits" rows="2" required placeholder="Describe their diet, appetite, and feeding schedule.">{{ old('eating_habits') }}</textarea>
                <div class="field-error" data-validation-for="eating_habits">@error('eating_habits'){{ $message }}@enderror</div>
            </div>

            <div class="form-group">
                <label for="behavioral_observations">Behavioral Observations *</label>
                <textarea id="behavioral_observations" name="behavioral_observations" rows="3" required placeholder="Describe any changes in behavior or social interaction.">{{ old('behavioral_observations') }}</textarea>
                <div class="field-error" data-validation-for="behavioral_observations">@error('behavioral_observations'){{ $message }}@enderror</div>
            </div>

            <div class="form-group">
                <label for="vet_visit_details">Vet Visit Details</label>
                <textarea id="vet_visit_details" name="vet_visit_details" rows="2" placeholder="Share recent vet visits, vaccinations, or medications.">{{ old('vet_visit_details') }}</textarea>
                <div class="field-error" data-validation-for="vet_visit_details">@error('vet_visit_details'){{ $message }}@enderror</div>
            </div>

            <div class="form-group">
                <label for="concerns">Any Concerns?</label>
                <textarea id="concerns" name="concerns" rows="2" placeholder="Tell the shelter anything else it should know.">{{ old('concerns') }}</textarea>
                <div class="field-error" data-validation-for="concerns">@error('concerns'){{ $message }}@enderror</div>
            </div>

            <section class="post-adoption-camera" data-camera-capture aria-labelledby="liveVideoHeading">
                <div class="post-adoption-camera__heading">
                    <div>
                        <h2 id="liveVideoHeading">Live 3-second welfare video *</h2>
                        <p>Record a current video using this device's camera. Gallery uploads are not accepted.</p>
                    </div>
                    <span class="post-adoption-camera__badge">3-second live recording</span>
                </div>

                <div class="post-adoption-camera__notice">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <p>
                        A five-minute, single-use challenge links this recording to your signed-in account and check-in.
                        Recording stops automatically after three seconds. The server securely hashes the video and
                        rejects any identical file that has already been used for another check-in.
                    </p>
                </div>

                <div class="post-adoption-camera__viewport" data-camera-viewport>
                    <div class="post-adoption-camera__placeholder" data-camera-placeholder>
                        <i class="fa-solid fa-video" aria-hidden="true"></i>
                        <span>Your camera preview will appear here.</span>
                    </div>
                    <video data-camera-video autoplay muted playsinline hidden aria-label="Live camera preview"></video>
                    <video data-camera-preview controls muted playsinline hidden aria-label="Recorded live welfare video preview"></video>
                </div>

                <p class="post-adoption-camera__status" data-camera-status aria-live="polite" aria-atomic="true">
                    Camera access has not started.
                </p>
                <p class="post-adoption-camera__security-note" data-camera-challenge-status hidden></p>

                <div class="post-adoption-camera__actions">
                    <button type="button" class="btn btn-secondary" data-camera-start>
                        <i class="fa-solid fa-video" aria-hidden="true"></i>
                        Start Camera
                    </button>
                    <button type="button" class="btn btn-primary" data-camera-record hidden disabled>
                        <i class="fa-solid fa-circle" aria-hidden="true"></i>
                        Record 3-Second Video
                    </button>
                    <button type="button" class="btn btn-secondary" data-camera-retake hidden>
                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                        Record Again
                    </button>
                </div>

                <div class="field-error" data-validation-for="video" data-camera-error role="alert">@error('video'){{ $message }}@enderror</div>
                <div class="field-error" data-validation-for="camera_captured_at">@error('camera_captured_at'){{ $message }}@enderror</div>
                <div class="field-error" data-validation-for="recording_duration_ms">@error('recording_duration_ms'){{ $message }}@enderror</div>
                <div class="field-error" data-validation-for="capture_challenge">@error('capture_challenge'){{ $message }}@enderror</div>
                <p class="post-adoption-camera__security-note">
                    Camera access requires HTTPS, except when using localhost. The camera stream stays on this page and stops after recording.
                </p>
                <noscript>
                    <p class="field-error">JavaScript is required to open the live camera and establish a secure capture session.</p>
                </noscript>
            </section>

            <div class="post-adoption-report-actions">
                <p data-camera-submit-hint>Record a live 3-second video to enable submission.</p>
                <button type="submit" class="btn btn-primary" data-camera-submit disabled>
                    Submit Report
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
