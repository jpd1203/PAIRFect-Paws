@extends(auth()->user()->isStaff() ? 'admin.layouts.app' : 'layouts.app')

@section('title', 'Notifications - PAIRfect Paws')

@section('notification-bell-in-header', true)
@section('content')
<div class="nonsticky-header">
    <div class="mb-2"> 
        <button type="button" onclick="window.history.back()" 
            class="inline-flex items-center gap-2 text-sm font-semibold text-text-muted hover:text-primary transition-colors" > 
            <i class="fa-solid fa-arrow-left"></i><span>Back</span> 
        </button> 
    </div>
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
            <article class="rounded-card border border-[#e2ddd7] bg-white p-4 shadow-card transition-all duration-300 ease-out hover:-translate-y-1 hover:scale-[1.01] hover:shadow-lg hover:border-primary/30 {{ $notice->read_at ? '' : 'border-primary/40' }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-text-dark text-m m-0 break-words"><i class="fa-solid fa-bell mr-2 text-primary"></i>{{ $notice->title }}</h3>
                        <p class="text-sm text-text-muted mt-1 mb-0 break-words">{{ $notice->body }}</p>
                        <p class="text-xs text-text-muted mt-2 mb-0">{{ $notice->created_at->diffForHumans() }}</p>
                    </div>
                    @if (! $notice->read_at)<span class="rounded-full bg-primary-muted text-primary text-xs font-bold px-2 py-1">New</span>@endif
                </div>
                <div class="flex flex-wrap gap-3 mt-3">
                    <a href="{{ route('notifications.open', $notice) }}" class="btn btn-primary btn-xs">View details</a>
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
</div>
@endsection
