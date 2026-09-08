@extends('layouts.app')
@section('title', 'Apply to Adopt ' . $pet->name)

@section('content')
<div style="max-width:700px;margin:0 auto">
    <div class="page-header">
        <h1>Adopt {{ $pet->name }}</h1>
        <p>{{ $pet->breed ?? $pet->species->value }} &bull; {{ $pet->branch?->name ?? '' }}</p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('applications.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="pet_id" value="{{ $pet->id }}">

            <div class="form-group">
                <label for="motivation_statement">Why do you want to adopt {{ $pet->name }}?*</label>
                <textarea id="motivation_statement" name="motivation_statement" rows="5" required placeholder="Tell us about your lifestyle and why you'd be a great match...">{{ old('motivation_statement') }}</textarea>
                @error('motivation_statement') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="form-group">
                    <label for="housing_type">Housing Type*</label>
                    <div class="select-wrapper">
                        <select id="housing_type" name="housing_type" required>
                            <option value="">Select…</option>
                            <option value="House with yard"     {{ old('housing_type') === 'House with yard'     ? 'selected' : '' }}>House with yard</option>
                            <option value="House without yard"  {{ old('housing_type') === 'House without yard'  ? 'selected' : '' }}>House without yard</option>
                            <option value="Apartment"           {{ old('housing_type') === 'Apartment'           ? 'selected' : '' }}>Apartment</option>
                            <option value="Condominium"         {{ old('housing_type') === 'Condominium'         ? 'selected' : '' }}>Condominium</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    @error('housing_type') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="income_range">Monthly Income Range*</label>
                    <div class="select-wrapper">
                        <select id="income_range" name="income_range" required>
                            <option value="">Select…</option>
                            <option value="Below ₱15,000" {{ old('income_range') === 'Below ₱15,000' ? 'selected' : '' }}>Below ₱15,000</option>
                            <option value="₱15,000–₱30,000" {{ old('income_range') === '₱15,000–₱30,000' ? 'selected' : '' }}>₱15,000–₱30,000</option>
                            <option value="₱30,000–₱60,000" {{ old('income_range') === '₱30,000–₱60,000' ? 'selected' : '' }}>₱30,000–₱60,000</option>
                            <option value="Above ₱60,000" {{ old('income_range') === 'Above ₱60,000' ? 'selected' : '' }}>Above ₱60,000</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-arrow"></i>
                    </div>
                    @error('income_range') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="document">Supporting Document* <span style="color:var(--muted);font-weight:400">(Government ID or Proof of Address — PDF, JPG, PNG, max 4MB)</span></label>
                <input id="document" type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png" style="padding:0.4rem 0">
                @error('document') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary">Submit Application</button>
        </form>
    </div>
</div>
@endsection
