@extends('layouts.app')
@section('title', $pet->name)

@section('content')
<div style="max-width:860px;margin:0 auto">
    <div style="margin-bottom:1rem"><a href="{{ route('pets.index') }}" style="color:var(--primary);text-decoration:none">← Back to catalog</a></div>

    <div class="card" style="display:grid;grid-template-columns:320px 1fr;gap:2rem;padding:0;overflow:hidden">
        {{-- Photo --}}
        <div>
            @if($pet->photo_path)
                <img src="{{ Storage::disk('public')->url($pet->photo_path) }}" alt="{{ $pet->name }}" style="width:100%;height:100%;object-fit:cover;min-height:300px">
            @else
                <div style="width:100%;height:300px;background:linear-gradient(135deg,#ede9fe,#dbeafe);display:flex;align-items:center;justify-content:center;font-size:5rem">
                    {{ $pet->species->value === 'Cat' ? '🐱' : '🐶' }}
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div style="padding:2rem">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:0.5rem">
                <h1 style="font-size:2rem;font-weight:700">{{ $pet->name }}</h1>
                @php $badgeClass = match($pet->availability_status->value) { 'Available'=>'badge-green','Processing'=>'badge-yellow','Adopted'=>'badge-gray',default=>'badge-gray' }; @endphp
                <span class="badge {{ $badgeClass }}">{{ $pet->availability_status->value }}</span>
            </div>

            <p style="color:var(--muted);margin-bottom:1.5rem">{{ $pet->breed ?? $pet->species->value }}</p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1.5rem;font-size:0.9rem">
                <div><strong>Species</strong><br>{{ $pet->species->value }}</div>
                <div><strong>Age</strong><br>{{ $pet->age ? $pet->age . ' years' : 'Unknown' }}</div>
                <div><strong>Sex</strong><br>{{ $pet->sex ?? 'Unknown' }}</div>
                <div><strong>Health</strong><br>{{ $pet->health_status ?? 'Good' }}</div>
                <div><strong>Branch</strong><br>{{ $pet->branch?->name ?? 'N/A' }}</div>
                <div><strong>Intake Date</strong><br>{{ $pet->intake_date?->format('M d, Y') ?? 'N/A' }}</div>
            </div>

            @if($pet->behavioral_notes)
                <div style="margin-bottom:1.5rem">
                    <strong style="font-size:0.875rem">Behavioral Notes</strong>
                    <p style="color:var(--muted);font-size:0.9rem;margin-top:0.25rem">{{ $pet->behavioral_notes }}</p>
                </div>
            @endif

            <div style="display:flex;gap:0.75rem;flex-wrap:wrap">
                @auth
                    @if(auth()->user()->isAdopter() && $pet->availability_status->value === 'Available')
                        <a href="{{ route('applications.create', $pet) }}" class="btn btn-primary">Apply to Adopt</a>
                    @endif
                    @if(auth()->user()->isStaff())
                        <a href="{{ route('admin.pets.edit', $pet) }}" class="btn btn-secondary">Edit</a>
                        @if(auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('admin.pets.archive', $pet) }}" onsubmit="return confirm('Archive {{ $pet->name }}?')">
                            @csrf
                            <button type="submit" class="btn btn-danger">Archive</button>
                        </form>
                        @endif
                    @endif
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
