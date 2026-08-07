@extends('layouts.app')
@section('title', 'Audit Logs')

@section('content')
<div class="page-header">
    <h1>Audit Logs</h1>
    <p>Chronological, immutable record of all system actions</p>
</div>

<div class="card">
    <table>
        <thead>
            <tr><th>Timestamp</th><th>User</th><th>Action</th><th>Entity</th><th>Notes</th></tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr>
                <td style="white-space:nowrap;font-size:0.8rem;color:var(--muted)">{{ $log->created_at->format('M d, Y H:i') }}</td>
                <td style="font-size:0.875rem">{{ $log->user ? $log->user->first_name . ' ' . $log->user->last_name : 'System' }}</td>
                <td style="font-size:0.875rem"><strong>{{ $log->action }}</strong></td>
                <td style="font-size:0.8rem;color:var(--muted)">{{ $log->entity_name ? $log->entity_name . ' #' . $log->entity_id : '—' }}</td>
                <td style="font-size:0.8rem;color:var(--muted);max-width:300px">{{ $log->notes ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="pagination">{{ $logs->links() }}</div>
@endsection
