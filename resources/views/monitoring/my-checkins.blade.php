@extends('layouts.app')
@section('title', 'My Check-ins')

@section('content')
<div class="page-header">
    <h1>My Post-Adoption Check-ins</h1>
    <p>Complete your required welfare reports for each milestone</p>
</div>

@if($logs->isEmpty())
    <div style="text-align:center;padding:4rem;color:var(--muted)">
        <div style="font-size:3rem">✅</div>
        <p style="margin-top:0.5rem">No check-ins scheduled yet. They appear after your adoption is approved.</p>
    </div>
@else
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Pet</th>
                <th>Milestone</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr>
                <td><strong>{{ $log->adoptionApplication->pet->name }}</strong></td>
                <td>{{ $log->milestone->shortLabel() }}</td>
                <td>{{ $log->scheduled_date->format('M d, Y') }}</td>
                <td>
                    @php
                        $badge = match($log->display_status) {
                            'Submitted' => 'badge-completed',
                            'Overdue'   => 'badge-overdue',
                            'Upcoming'  => 'badge-upcoming',
                            default     => 'badge-pending',
                        };
                    @endphp
                    <span class="badge {{ $badge }}">{{ $log->display_status }}</span>
                    @if($log->is_flagged && !$log->resolved_at)
                        <span class="badge badge-flagged" style="margin-left:0.25rem">⚠ Flagged</span>
                    @endif
                </td>
                <td>
                    @if(in_array($log->display_status, ['Pending', 'Overdue'], true))
                        <a href="{{ route('monitoring.create', $log) }}" class="btn btn-primary btn-sm">Submit Report</a>
                    @elseif($log->display_status === 'Upcoming')
                        <span style="color:var(--muted);font-size:0.85rem">Opens {{ $log->scheduled_date->format('M d') }}</span>
                    @else
                        <span style="color:var(--muted);font-size:0.85rem">Submitted {{ \App\Support\ManilaTime::format($log->submitted_date, 'M d') }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
