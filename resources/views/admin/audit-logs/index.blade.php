@extends('admin.layouts.app')

@section('title', 'Audit Logs - PAIRfect Paws Admin')

@section('content')

    <div class="flex justify-between items-start flex-wrap gap-3">
        <div class="heading-text">
            <h2>Audit Logs</h2>
            <p>A record of every meaningful staff action across the system.</p>
        </div>
        <a href="{{ route('admin.audit-logs.export') }}" class="btn btn-secondary"><i class="fa-solid fa-download"></i> Export CSV</a>
    </div>

    <div class="my-3">
        <input type="text" data-search-input data-search-scope="auditTableBody" class="search-input w-full" placeholder="Search by user or action…">
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr><th>Date / Time</th><th>User</th><th>Role</th><th>Action</th></tr>
                </thead>
                <tbody id="auditTableBody">
                    @forelse ($logs as $log)
                        <tr data-search-row data-search-text="{{ $log->user_name }} {{ $log->action }}">
                            <td>{{ $log->timestamp->format('M j, Y g:i A') }}</td>
                            <td class="font-semibold">{{ $log->user_name }}</td>
                            <td><span class="badge {{ $log->role === 'Admin' ? 'badge-approved' : ($log->role === 'Volunteer' ? 'badge-scheduled' : 'badge-pending') }}">{{ $log->role }}</span></td>
                            <td class="!text-left">{{ $log->action }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-[#888] py-6">No activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>

@endsection
