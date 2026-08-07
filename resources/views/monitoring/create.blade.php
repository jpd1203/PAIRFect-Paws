@extends('layouts.app')
@section('title', 'Submit Welfare Report')

@section('content')
<div style="max-width:700px;margin:0 auto">
    <div class="page-header">
        <h1>Submit Welfare Report</h1>
        <p>{{ match($log->milestone->value) { 'ThreeDays' => '3-Day', 'ThreeWeeks' => '3-Week', 'ThreeMonths' => '3-Month' } }} check-in for <strong>{{ $log->adoptionApplication->pet->name }}</strong></p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('monitoring.submit', $log) }}" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
                <label for="pet_current_status">Current Status of {{ $log->adoptionApplication->pet->name }} *</label>
                <select id="pet_current_status" name="pet_current_status" required>
                    <option value="">Select…</option>
                    <option value="Good" {{ old('pet_current_status') === 'Good' ? 'selected' : '' }}>😊 Good</option>
                    <option value="Fair" {{ old('pet_current_status') === 'Fair' ? 'selected' : '' }}>😐 Fair</option>
                    <option value="Poor" {{ old('pet_current_status') === 'Poor' ? 'selected' : '' }}>😟 Poor</option>
                </select>
                @error('pet_current_status') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="living_conditions">Living Conditions</label>
                <textarea id="living_conditions" name="living_conditions" rows="3" placeholder="Describe where the pet sleeps, how much space they have…">{{ old('living_conditions') }}</textarea>
            </div>

            <div class="form-group">
                <label for="eating_habits">Eating Habits</label>
                <textarea id="eating_habits" name="eating_habits" rows="2" placeholder="Diet, appetite, feeding schedule…">{{ old('eating_habits') }}</textarea>
            </div>

            <div class="form-group">
                <label for="behavioral_observations">Behavioral Observations</label>
                <textarea id="behavioral_observations" name="behavioral_observations" rows="3" placeholder="Any changes in behavior, social interactions…">{{ old('behavioral_observations') }}</textarea>
            </div>

            <div class="form-group">
                <label for="vet_visit_details">Vet Visit Details</label>
                <textarea id="vet_visit_details" name="vet_visit_details" rows="2" placeholder="Recent vet visits, vaccinations, medications…">{{ old('vet_visit_details') }}</textarea>
            </div>

            <div class="form-group">
                <label for="concerns">Any Concerns?</label>
                <textarea id="concerns" name="concerns" rows="2" placeholder="Anything you'd like the shelter to know…">{{ old('concerns') }}</textarea>
            </div>

            <div class="form-group">
                <label for="photo">Photo (optional)</label>
                <input id="photo" type="file" name="photo" accept="image/*" style="padding:0.4rem 0">
            </div>

            <button type="submit" class="btn btn-primary">Submit Report</button>
        </form>
    </div>
</div>
@endsection
