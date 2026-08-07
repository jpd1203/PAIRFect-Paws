@extends('layouts.app')
@section('title', 'All Animals')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end">
    <div>
        <h1>All Animals</h1>
        <p>Complete list including all adoption statuses</p>
    </div>
    <a href="{{ route('admin.pets.create') }}" class="btn btn-primary">+ Add Pet</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Name</th><th>Species</th><th>Breed</th><th>Status</th><th>Branch</th><th>Archived</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pets as $pet)
            <tr style="{{ $pet->is_archived ? 'opacity:0.5' : '' }}">
                <td><strong>{{ $pet->name }}</strong></td>
                <td>{{ $pet->species->value }}</td>
                <td>{{ $pet->breed ?? '—' }}</td>
                <td>
                    <span class="badge {{ match($pet->availability_status->value) { 'Available'=>'badge-green','Processing'=>'badge-yellow','Adopted'=>'badge-gray',default=>'badge-gray' } }}">
                        {{ $pet->availability_status->value }}
                    </span>
                </td>
                <td>{{ $pet->branch?->name ?? '—' }}</td>
                <td>{{ $pet->is_archived ? '🗄 Yes' : '—' }}</td>
                <td>
                    @if(!$pet->is_archived)
                        <a href="{{ route('admin.pets.edit', $pet->id) }}" class="btn btn-secondary btn-sm">Edit</a>
                        @if(auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('admin.pets.archive', $pet->id) }}" style="display:inline" onsubmit="return confirm('Archive {{ $pet->name }}?')">
                            @csrf
                            <button class="btn btn-danger btn-sm">Archive</button>
                        </form>
                        @endif
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="pagination">{{ $pets->links() }}</div>
@endsection
