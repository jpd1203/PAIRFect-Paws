@extends('layouts.app')
@section('title', 'Adoption Profiles')

@section('content')
<div class="page-header">
    <h1>Adoption Profiles</h1>
    <p>All approved adoptions — contact directory</p>
</div>

<div class="card">
    <table>
        <thead>
            <tr><th>Adopter</th><th>Email</th><th>Pet</th><th>Approved</th><th>Document</th></tr>
        </thead>
        <tbody>
            @foreach($profiles as $profile)
            <tr>
                <td><strong>{{ $profile->user->first_name }} {{ $profile->user->last_name }}</strong></td>
                <td>{{ $profile->user->email }}</td>
                <td>{{ $profile->pet->name }}</td>
                <td style="font-size:0.85rem;color:var(--muted)">{{ $profile->updated_at->format('M d, Y') }}</td>
                <td>
                    @if($profile->document_path)
                        <a href="{{ route('admin.adoption-profiles.document', $profile) }}" class="btn btn-secondary btn-sm" target="_blank">View ID</a>
                    @else
                        <span style="color:var(--muted)">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="pagination">{{ $profiles->links() }}</div>
@endsection
