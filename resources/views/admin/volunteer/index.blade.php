@extends('layouts.app')
@section('title', 'Staff Management')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end">
    <div>
        <h1>Staff &amp; Volunteer Management</h1>
        <p>All internal staff accounts</p>
    </div>
    <a href="{{ route('admin.volunteers.create') }}" class="btn btn-primary">Create Staff Account</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th>Active</th></tr>
        </thead>
        <tbody>
            @foreach($staff as $member)
            <tr>
                <td><strong>{{ $member->first_name }} {{ $member->last_name }}</strong></td>
                <td style="font-size:0.875rem">{{ $member->email }}</td>
                <td>
                    <span class="badge {{ $member->role->value === 'Administrator' ? 'badge-purple' : 'badge-blue' }}">
                        {{ $member->role->value }}
                    </span>
                </td>
                <td style="font-size:0.85rem;color:var(--muted)">{{ $member->branch?->name ?? '—' }}</td>
                <td>
                    <span class="badge {{ $member->is_active ? 'badge-green' : 'badge-red' }}">
                        {{ $member->is_active ? 'Active' : 'Deactivated' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
