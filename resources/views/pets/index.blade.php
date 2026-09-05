@extends('layouts.app')
@section('title', 'Browse Pets')
@section('meta_description', 'Browse available cats and dogs for adoption at PAIRfect Paws.')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end">
    <div>
        <h1>Browse Pets</h1>
        <p>Find your perfect companion</p>
    </div>
    @auth @if(auth()->user()->isStaff())
        <a href="{{ route('admin.pets.create') }}" class="btn btn-primary">+ Add Pet</a>
    @endif @endauth
</div>

{{-- Filters --}}
<form method="GET" style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <select name="species" onchange="this.form.submit()" style="width:auto">
        <option value="">All Species</option>
        <option value="Cat" {{ request('species') === 'Cat' ? 'selected' : '' }}>🐱 Cats</option>
        <option value="Dog" {{ request('species') === 'Dog' ? 'selected' : '' }}>🐶 Dogs</option>
    </select>
    <select name="status" onchange="this.form.submit()" style="width:auto">
        <option value="">All Statuses</option>
        <option value="Available"  {{ request('status') === 'Available'  ? 'selected' : '' }}>Available</option>
        <option value="Soft-Reserved" {{ request('status') === 'Soft-Reserved' ? 'selected' : '' }}>Processing - Under Evaluation</option>
        <option value="Processing" {{ request('status') === 'Processing' ? 'selected' : '' }}>Processing</option>
        <option value="Adopted"    {{ request('status') === 'Adopted'    ? 'selected' : '' }}>Adopted</option>
    </select>
</form>

@if($pets->isEmpty())
    <div style="text-align:center;padding:4rem;color:var(--muted)">
        <div style="font-size:3rem">🐾</div>
        <p style="margin-top:0.5rem">No pets found matching your filters.</p>
    </div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.25rem">
    @foreach($pets as $pet)
    <div class="card" style="display:flex;flex-direction:column;gap:0.75rem;padding:0;overflow:hidden">
        @if($pet->photo_path)
            <img src="{{ asset('storage/' . $pet->photo_path) }}" alt="{{ $pet->name }}" style="width:100%;height:180px;object-fit:cover">
        @else
            <div style="width:100%;height:180px;background:linear-gradient(135deg,#ede9fe,#dbeafe);display:flex;align-items:center;justify-content:center;font-size:3rem">
                {{ $pet->species->value === 'Cat' ? '🐱' : '🐶' }}
            </div>
        @endif
        <div style="padding:1rem">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem">
                <h2 style="font-size:1.1rem;font-weight:700">{{ $pet->name }}</h2>
                @php
                    $badgeClass = match($pet->availability_status->value) {
                        'Available'  => 'badge-green',
                        'Soft-Reserved' => 'badge-yellow',
                        'Processing' => 'badge-yellow',
                        'Adopted'    => 'badge-gray',
                        default      => 'badge-gray',
                    };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $pet->availability_status->value }}</span>
            </div>
            <p style="color:var(--muted);font-size:0.85rem">{{ $pet->breed ?? $pet->species->value }} &bull; {{ $pet->age ? $pet->age . ' yrs' : 'Age unknown' }}</p>
            @if($pet->availability_status->value === 'Soft-Reserved')
                <p style="color:#92400e;font-size:0.8rem;font-weight:700;margin-top:0.35rem">Processing - Under Evaluation. New applications are paused.</p>
            @endif
            @if($pet->branch)
                <p style="color:var(--muted);font-size:0.8rem;margin-top:0.25rem">📍 {{ $pet->branch->name }}</p>
            @endif
            <div style="margin-top:0.75rem;display:flex;gap:0.5rem">
                <a href="{{ route('pets.show', $pet) }}" class="btn btn-secondary btn-sm">View Details</a>
                @auth @if(auth()->user()->isAdopter() && $pet->availability_status->value === 'Available')
                    <a href="{{ route('applications.create', $pet) }}" class="btn btn-primary btn-sm">Adopt</a>
                @endif @endauth
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="pagination">{{ $pets->withQueryString()->links() }}</div>
@endif
@endsection
