@php
    $bellUser = auth()->user();
    $unreadNotifications = $bellUser->inAppNotifications()->whereNull('read_at')->count();
    $recentNotifications = $bellUser->inAppNotifications()->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();
@endphp

<div class="notification-bell relative z-[9999]">
    <details class="relative group">
        <summary class="btn btn-secondary cursor-pointer list-none relative" aria-label="Notifications ({{ $unreadNotifications }} unread)">
            <i class="fa-solid fa-bell"></i><span class="ml-2 notification-label">Notifications</span>
            @if ($unreadNotifications > 0)
                <span class="ml-2 rounded-full bg-primary text-white text-xs font-bold px-2 py-0.5">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
            @endif
        </summary>
        <div class="absolute right-0 top-full z-[99999] mt-2 w-[min(22rem,calc(100vw-2rem))] max-h-[70vh] overflow-y-auto rounded-xl border border-[#e2ddd7] bg-white shadow-xl p-3">            <h2 class="font-bold text-text-dark text-sm px-2 py-1">Recent notifications</h2>
            @forelse ($recentNotifications as $notice)
                <a href="{{ route('notifications.open', $notice) }}" class="block rounded-lg p-2 my-1 hover:bg-neutral-light {{ $notice->read_at ? '' : 'bg-primary-muted/30' }} no-underline">
                    <span class="block text-sm font-bold text-text-dark break-words">{{ $notice->title }}</span>
                    <span class="block text-xs text-text-muted break-words">{{ $notice->body }}</span>
                    <span class="block text-xs text-text-muted mt-1">{{ $notice->created_at->diffForHumans() }}</span>
                </a>
            @empty
                <p class="text-sm text-text-muted px-2 py-3">No notifications yet.</p>
            @endforelse
            <a href="{{ route('notifications.index') }}"
                class="block text-center text-sm font-bold text-primary border-t border-[#e2ddd7] pt-3 mt-2 transition-colors duration-200 hover:text-primary-dark hover:underline">
                    View All Notifications
            </a>
        </div>
    </details>
</div>

<script>
document.addEventListener('click', function (event) {
    const notification = document.querySelector('.notification-bell');
    const details = notification?.querySelector('details');

    if (!notification || !details) return;

    if (!notification.contains(event.target)) {
        details.removeAttribute('open');
    }
});
</script>