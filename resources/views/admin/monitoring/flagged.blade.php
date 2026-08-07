@extends('layouts.app')
@section('title', 'Flagged Cases')

@section('content')
<div class="page-header">
    <h1>🚩 Flagged Cases</h1>
    <p>Post-adoption logs requiring staff review</p>
</div>

@if($flagged->isEmpty())
    <div style="text-align:center;padding:4rem;color:var(--muted)">
        <div style="font-size:3rem">🎉</div>
        <p>No flagged cases. All good!</p>
    </div>
@else
<div class="card">
    @foreach($flagged as $log)
    <div style="border-bottom:1px solid var(--border);padding:1.25rem 0">
        <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem">
            <div>
                <strong>{{ $log->adoptionApplication->user->first_name }} {{ $log->adoptionApplication->user->last_name }}</strong>
                <span style="color:var(--muted);font-size:0.85rem"> — {{ $log->adoptionApplication->pet->name }}</span>
                <span class="badge badge-red" style="margin-left:0.5rem">{{ match($log->milestone->value) { 'ThreeDays' => '3-Day', 'ThreeWeeks' => '3-Week', 'ThreeMonths' => '3-Month' } }}</span>
            </div>
            <span style="color:var(--muted);font-size:0.8rem">{{ $log->reminders_sent }} reminder(s) sent</span>
        </div>

        @if($log->pet_current_status)
            <p style="font-size:0.875rem;margin-bottom:0.5rem">Status: <strong>{{ $log->pet_current_status->value }}</strong></p>
        @endif
        @if($log->concerns)
            <p style="font-size:0.85rem;color:var(--muted);margin-bottom:0.75rem">Concerns: {{ $log->concerns }}</p>
        @endif

        <form method="POST" action="{{ route('admin.monitoring.resolve', $log) }}" style="display:flex;gap:0.75rem;align-items:flex-end">
            @csrf
            <div style="flex:1">
                <label style="font-size:0.8rem;font-weight:600">Resolution Note</label>
                <textarea name="resolution_note" rows="2" required placeholder="Describe the resolution action taken…" style="margin-top:0.25rem"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap">Mark Resolved</button>
        </form>
    </div>
    @endforeach
</div>
@endif
@endsection
