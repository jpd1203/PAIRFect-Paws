@extends(auth()->user()->isStaff() ? 'admin.layouts.app' : 'layouts.app')

@section('title', 'Notifications - PAIRfect Paws')

@section('content')
<div class="heading-text mb-5">
    <h2>Notifications</h2>
    <p>Important updates about your PAIRfect Paws activity.</p>
</div>
<div class="max-w-[860px] mx-auto space-y-4">
    @if (auth()->user()->inAppNotifications()->whereNull('read_at')->exists())
        <form method="POST" action="{{ route('notifications.read-all') }}" class="text-right">
            @csrf
            <button type="submit" class="btn btn-secondary btn-sm">Mark all as read</button>
        </form>
    @endif

    @forelse ($notifications as $notice)
        <article class="rounded-card border border-[#e2ddd7] bg-white p-4 shadow-card {{ $notice->read_at ? '' : 'border-primary/40' }}">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-text-dark text-sm m-0 break-words">{{ $notice->title }}</h3>
                    <p class="text-sm text-text-muted mt-1 mb-0 break-words">{{ $notice->body }}</p>
                    <p class="text-xs text-text-muted mt-2 mb-0">{{ $notice->created_at->diffForHumans() }}</p>
                </div>
                @if (! $notice->read_at)<span class="rounded-full bg-primary-muted text-primary text-xs font-bold px-2 py-1">New</span>@endif
            </div>
            <div class="flex flex-wrap gap-3 mt-3">
                <a href="{{ route('notifications.open', $notice) }}" class="btn btn-primary btn-sm">View details</a>
                @if (! $notice->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notice) }}">@csrf<button class="btn btn-secondary btn-sm" type="submit">Mark as read</button></form>
                @endif
            </div>
        </article>
    @empty
        <p class="rounded-card border border-[#e2ddd7] bg-white p-6 text-text-muted">No notifications yet.</p>
    @endforelse

    {{ $notifications->links() }}
</div>
@endsection
