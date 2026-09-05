@extends('layouts.app')
@section('title', 'Edit ' . $pet->name)

@section('content')
<div style="max-width:700px;margin:0 auto">
    <div class="page-header">
        <h1>Edit: {{ $pet->name }}</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.pets.update', $pet) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            {{-- Optimistic concurrency version token --}}
            <input type="hidden" name="version" value="{{ $pet->version }}">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $pet->name) }}" required>
                    @error('name') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="species">Species *</label>
                    <select id="species" name="species" required>
                        <option value="Cat" {{ old('species', $pet->species->value) === 'Cat' ? 'selected' : '' }}>🐱 Cat</option>
                        <option value="Dog" {{ old('species', $pet->species->value) === 'Dog' ? 'selected' : '' }}>🐶 Dog</option>
                    </select>
                    @error('species') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="breed">Breed</label>
                    <input id="breed" type="text" name="breed" value="{{ old('breed', $pet->breed) }}">
                </div>
                <div class="form-group">
                    <label for="age">Age (years)</label>
                    <input id="age" type="number" name="age" min="0" value="{{ old('age', $pet->age) }}">
                </div>
                <div class="form-group">
                    <label for="sex">Sex</label>
                    <select id="sex" name="sex">
                        <option value="">Unknown</option>
                        <option value="Male"   {{ old('sex', $pet->sex) === 'Male'   ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('sex', $pet->sex) === 'Female' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="health_status">Health Status</label>
                    <input id="health_status" type="text" name="health_status" value="{{ old('health_status', $pet->health_status) }}">
                </div>
                <div class="form-group">
                    <label for="branch_id">Branch</label>
                    <select id="branch_id" name="branch_id">
                        <option value="">No branch</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id', $pet->branch_id) == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="Available" {{ old('status', $pet->availability_status->value) === 'Available' ? 'selected' : '' }}>Available</option>
                        <option value="Soft-Reserved" {{ old('status', $pet->availability_status->value) === 'Soft-Reserved' ? 'selected' : '' }}>Soft-Reserved</option>
                        <option value="Processing" {{ old('status', $pet->availability_status->value) === 'Processing' ? 'selected' : '' }}>Processing</option>
                        <option value="Adopted" {{ old('status', $pet->availability_status->value) === 'Adopted' ? 'selected' : '' }}>Adopted</option>
                    </select>
                    @error('status') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="behavioral_notes">Behavioral Notes</label>
                <textarea id="behavioral_notes" name="behavioral_notes" rows="3">{{ old('behavioral_notes', $pet->behavioral_notes) }}</textarea>
            </div>

            <div class="form-group">
                <label for="photo">Replace Photo</label>
                @if($pet->photo_path)
                    <img src="{{ Storage::disk('public')->url($pet->photo_path) }}" style="height:80px;border-radius:6px;margin-bottom:0.5rem;display:block">
                @endif
                <input id="photo" type="file" name="photo" accept="image/*" style="padding:0.4rem 0">
                @error('photo') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            @error('concurrency')
                <div class="alert alert-warning">{{ $message }}</div>
            @enderror

            <div style="display:flex;gap:0.75rem">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.animals.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
