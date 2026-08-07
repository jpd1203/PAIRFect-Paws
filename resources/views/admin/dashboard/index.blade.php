@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <h1>Admin Dashboard</h1>
    <p>Real-time overview of the shelter system</p>
</div>

{{-- Stats cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:2rem">
    @php
        $stats_items = [
            ['label' => 'Total Animals',      'value' => $stats['total_animals'],      'icon' => '🐾', 'color' => '#5b6af0'],
            ['label' => 'Available',           'value' => $stats['available_animals'],  'icon' => '✅', 'color' => '#10b981'],
            ['label' => 'Under Review',        'value' => $stats['pending_applications'],'icon' => '📋', 'color' => '#f59e0b'],
            ['label' => 'Active Monitoring',   'value' => $stats['active_monitoring'],  'icon' => '📊', 'color' => '#6366f1'],
            ['label' => 'Flagged Cases',       'value' => $stats['flagged_count'],      'icon' => '🚩', 'color' => '#ef4444'],
        ];
    @endphp
    @foreach($stats_items as $s)
    <div class="card" style="text-align:center;border-top:3px solid {{ $s['color'] }}">
        <div style="font-size:1.75rem">{{ $s['icon'] }}</div>
        <div style="font-size:2rem;font-weight:700;color:{{ $s['color'] }}">{{ $s['value'] }}</div>
        <div style="color:var(--muted);font-size:0.85rem">{{ $s['label'] }}</div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
    {{-- Recent Applications --}}
    <div class="card">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Recent Applications</h2>
        @foreach($recentApplications as $app)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--border)">
            <div>
                <strong style="font-size:0.9rem">{{ $app->user->first_name }} {{ $app->user->last_name }}</strong><br>
                <span style="color:var(--muted);font-size:0.8rem">→ {{ $app->pet->name }}</span>
            </div>
            <span class="badge {{ match($app->status->value) { 'Pending'=>'badge-yellow','UnderReview'=>'badge-blue','InterviewScheduled'=>'badge-purple','Approved'=>'badge-green','Rejected'=>'badge-red',default=>'badge-gray' } }}">{{ $app->status->value }}</span>
        </div>
        @endforeach
        <a href="{{ route('admin.applications.index') }}" style="display:block;text-align:center;margin-top:1rem;color:var(--primary);font-size:0.85rem">View all →</a>
    </div>

    {{-- Post-Adoption Alerts --}}
    <div class="card">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem">🚩 Post-Adoption Alerts</h2>
        @forelse($postAdoptionAlerts as $alert)
        <div style="padding:0.6rem 0;border-bottom:1px solid var(--border)">
            <strong style="font-size:0.9rem">{{ $alert->adoptionApplication->pet->name }}</strong><br>
            <span style="color:var(--muted);font-size:0.8rem">{{ $alert->adoptionApplication->user->first_name }} — {{ $alert->milestone->value }}</span>
        </div>
        @empty
        <p style="color:var(--muted);font-size:0.9rem">No flagged cases 🎉</p>
        @endforelse
        <a href="{{ route('admin.monitoring.flagged') }}" style="display:block;text-align:center;margin-top:1rem;color:var(--primary);font-size:0.85rem">View all →</a>
    </div>
</div>
@endsection
