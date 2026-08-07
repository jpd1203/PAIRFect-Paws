@extends('layouts.app')
@section('title', 'Add New Pet')

@section('content')
<div style="max-width:700px;margin:0 auto">
    <div class="page-header">
        <h1>Add New Pet</h1>
        <p>Fill in the details to add a new animal to the catalog</p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.pets.store') }}" enctype="multipart/form-data">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required>
                    @error('name') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="species">Species *</label>
                    <select id="species" name="species" required>
                        <option value="">Select species</option>
                        <option value="Cat" {{ old('species') === 'Cat' ? 'selected' : '' }}>🐱 Cat</option>
                        <option value="Dog" {{ old('species') === 'Dog' ? 'selected' : '' }}>🐶 Dog</option>
                    </select>
                    @error('species') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="breed">Breed</label>
                    <input id="breed" type="text" name="breed" value="{{ old('breed') }}">
                </div>
                <div class="form-group">
                    <label for="age">Age (years)</label>
                    <input id="age" type="number" name="age" min="0" value="{{ old('age') }}">
                </div>
                <div class="form-group">
                    <label for="sex">Sex</label>
                    <select id="sex" name="sex">
                        <option value="">Unknown</option>
                        <option value="Male"   {{ old('sex') === 'Male'   ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('sex') === 'Female' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="health_status">Health Status</label>
                    <input id="health_status" type="text" name="health_status" value="{{ old('health_status') }}">
                </div>
                <div class="form-group">
                    <label for="branch_id">Branch</label>
                    <select id="branch_id" name="branch_id">
                        <option value="">No branch</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="intake_date">Intake Date</label>
                    <input id="intake_date" type="date" name="intake_date" value="{{ old('intake_date', now()->toDateString()) }}">
                </div>
            </div>

            <div class="form-group">
                <label for="behavioral_notes">Behavioral Notes</label>
                <textarea id="behavioral_notes" name="behavioral_notes" rows="3">{{ old('behavioral_notes') }}</textarea>
            </div>

            <div class="form-group">
                <label for="photo">Photo</label>
                <input id="photo" type="file" name="photo" accept="image/*" style="padding:0.4rem 0">
                @error('photo') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div style="display:flex;gap:0.75rem">
                <button type="submit" class="btn btn-primary">Add Pet</button>
                <a href="{{ route('admin.animals.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
