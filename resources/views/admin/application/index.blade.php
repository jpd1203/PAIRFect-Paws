@extends('layouts.app')
@section('title', 'Applications')

@section('content')
<div class="page-header">
    <h1>Adoption Applications</h1>
</div>

<div class="card">
    <table>
        <thead>
            <tr><th>Applicant</th><th>Pet</th><th>Status</th><th>Interview</th><th>Submitted</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @foreach($applications as $app)
            <tr>
                <td>
                    <strong>{{ $app->user->first_name }} {{ $app->user->last_name }}</strong><br>
                    <span style="color:var(--muted);font-size:0.8rem">{{ $app->user->email }}</span>
                </td>
                <td>{{ $app->pet->name }}</td>
                <td>
                    <span class="badge {{ match($app->status->value) { 'Pending'=>'badge-yellow','UnderReview'=>'badge-blue','InterviewScheduled'=>'badge-purple','Approved'=>'badge-green','Rejected'=>'badge-red',default=>'badge-gray' } }}">
                        {{ $app->status->value }}
                    </span>
                </td>
                <td>{{ $app->interview_date ? $app->interview_date->format('M d, Y g:i A') : '—' }}</td>
                <td style="font-size:0.8rem;color:var(--muted)">{{ $app->created_at->format('M d, Y') }}</td>
                <td style="display:flex;gap:0.4rem;flex-wrap:wrap">
                    {{-- Schedule Interview --}}
                    @if(in_array($app->status->value, ['Pending','UnderReview']))
                    <form method="POST" action="{{ route('admin.applications.interview', $app) }}" style="display:flex;gap:0.25rem">
                        @csrf
                        <input type="datetime-local" name="interview_date" required style="font-size:0.75rem;padding:0.3rem;width:auto">
                        <button class="btn btn-secondary btn-sm">Schedule</button>
                    </form>
                    @endif
                    {{-- Decision --}}
                    @if($app->status->value === 'InterviewScheduled')
                    <form method="POST" action="{{ route('admin.applications.decision', $app) }}">
                        @csrf <input type="hidden" name="decision" value="Approved">
                        <button class="btn btn-primary btn-sm">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.applications.decision', $app) }}">
                        @csrf <input type="hidden" name="decision" value="Rejected">
                        <button class="btn btn-danger btn-sm">Reject</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="pagination">{{ $applications->links() }}</div>
@endsection
