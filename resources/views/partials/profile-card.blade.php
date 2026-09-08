@php $user = auth()->user(); @endphp

<div class="user-card">

    <div class="avatar">
        {{ $user?->avatar_initial ?? '?' }}
    </div>

    <div class="user-info">
        <strong>{{ $user?->full_name ?? 'Guest' }}</strong>
        <small>{{ $user?->email ?? '' }}</small>
    </div>

    @if (auth()->check())
        <form action="{{ route('logout') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="logout-btn" title="Log out" aria-label="Log out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </button>
        </form>
    @else
        <a href="{{ route('login') }}" class="logout-btn" title="Sign in" aria-label="Sign in">
            <i class="fa-solid fa-right-to-bracket"></i>
        </a>
    @endif

</div>

