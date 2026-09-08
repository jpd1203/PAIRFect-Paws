@extends('layouts.app')
@section('title', 'My Applications')

@section('content')
<div class="page-header">
    <h1>My Applications</h1>
    <p>Track the status of all your adoption applications</p>
</div>

@if($applications->isEmpty())
    <div style="text-align:center;padding:4rem;color:var(--muted)">
        <div style="font-size:3rem">📋</div>
        <p style="margin-top:0.5rem">You haven't submitted any applications yet.</p>
        <a href="{{ route('pets.index') }}" class="btn btn-primary" style="margin-top:1rem">Browse Pets</a>
    </div>
@else
<div class="card overflow-hidden">
    <div class="table-responsive overflow-x-auto w-full">
        <table class="w-full min-w-[550px]">
            <thead>
                <tr>
                    <th>Pet</th>
                    <th>Status</th>
                    <th>Interview Date</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                @foreach($applications as $app)
                <tr>
                    <td>
                        <strong>{{ $app->pet->name }}</strong><br>
                        <span style="color:var(--muted);font-size:0.8rem">{{ $app->pet->breed ?? $app->pet->species->value }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $app->status_badge_class }}">{{ $app->status_display }}</span>
                    </td>
                    <td class="whitespace-nowrap">{{ $app->interview_date ? \App\Support\ManilaTime::format($app->interview_date, 'M d, Y g:i A') : '—' }}</td>
                    <td class="whitespace-nowrap" style="color:var(--muted);font-size:0.85rem">{{ \App\Support\ManilaTime::format($app->created_at, 'M d, Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
