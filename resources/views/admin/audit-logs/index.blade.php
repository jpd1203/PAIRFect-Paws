@extends('admin.layouts.app')

@section('title', 'Audit Logs - PAIRfect Paws Admin')

@section('notification-bell-in-header', true)
@section('content')
    <div class="main-content-header">
        <div class="heading-text">
            <h2>Audit Logs</h2>
            <p>A record of every meaningful staff action across the system.</p>
        </div>

        @include('partials.notification-bell')
    </div>

    <div class="flex flex-wrap gap-3 items-center my-5">
        <div class="relative flex-1 min-w-[220px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
            <input type="text" data-search-input data-search-scope="auditTableBody" class="search-input !pl-10 flex-1 min-w-[220px]" placeholder="Search by user, role, action, or details…">
        </div>
        <a href="{{ route('admin.audit-logs.export') }}" class="btn btn-primary"><i class="fa-solid fa-download"></i> Export CSV</a>
    </div>

    <div class="records-container">
        <div class="table-responsive custom-scrollbar">
            <table class="w-full">
                <thead>
                    <tr><th>Date / Time</th><th>User</th><th>Role</th><th>Action</th></tr>
                </thead>
                <tbody id="auditTableBody">
                    @forelse ($logs as $log)
                        <tr data-search-row data-filter-row data-search-text="{{ $log->user_name }} {{ $log->role }} {{ $log->display_action }} {{ $log->notes }} {{ $log->timestamp?->format('M j, Y') }}">
                            <td>{{ $log->timestamp?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="font-semibold">{{ $log->user_name }}</td>
                            <td><span class="badge {{ match (strtolower($log->role ?? '')) {
                                'administrator', 'admin' => 'badge-pending',
                                'volunteer' => 'badge-scheduled',
                                'adopter' => 'badge-approved',
                                default => 'badge-upcoming',
                            } }}">{{ $log->role }}</span></td>
                            <td>
                                <strong class="block">{{ $log->display_action }}</strong>
                                @if (filled($log->notes))
                                    <small class="mt-1 block max-w-[48rem] text-[#6f6865]">{{ $log->notes }}</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-[#888] py-6 text-center">No activity recorded yet.</td></tr>
                    @endforelse
                    <tr class="search-empty-row" style="display: none;">
                        <td colspan="4" class="text-center text-[#888] py-6">No matching logs found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>

@endsection
