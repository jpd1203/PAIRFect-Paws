@extends('layouts.app')
@section('title', 'Monitoring Dashboard')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end">
    <div><h1>Post-Adoption Monitoring</h1></div>
    <a href="{{ route('admin.monitoring.flagged') }}" class="btn btn-danger">🚩 Flagged Cases</a>
</div>

@foreach([['label' => '⚠ Overdue', 'logs' => $overdue, 'color' => '#ef4444'], ['label' => '⏰ Due Soon (next 3 days)', 'logs' => $dueSoon, 'color' => '#f59e0b'], ['label' => '✅ Compliant', 'logs' => $compliant, 'color' => '#10b981']] as $group)
@if($group['logs']->isNotEmpty())
<div style="margin-bottom:2rem">
    <h2 style="font-size:1rem;font-weight:700;color:{{ $group['color'] }};margin-bottom:0.75rem">{{ $group['label'] }} ({{ $group['logs']->count() }})</h2>
    <div class="card" style="padding:0">
        <table>
            <thead><tr><th>Adopter</th><th>Pet</th><th>Milestone</th><th>Due Date</th><th>Reminders Sent</th></tr></thead>
            <tbody>
                @foreach($group['logs'] as $log)
                <tr>
                    <td>{{ $log->adoptionApplication->user->first_name }} {{ $log->adoptionApplication->user->last_name }}</td>
                    <td>{{ $log->adoptionApplication->pet->name }}</td>
                    <td>{{ match($log->milestone->value) { 'ThreeDays' => '3-Day', 'ThreeWeeks' => '3-Week', 'ThreeMonths' => '3-Month' } }}</td>
                    <td>{{ $log->scheduled_date->format('M d, Y') }}</td>
                    <td>{{ $log->reminders_sent }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endforeach
@endsection
