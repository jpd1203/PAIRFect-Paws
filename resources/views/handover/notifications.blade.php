@extends('layouts.app')

@section('title', 'Notifications - ' . ($record->pet?->name ?? 'Pet') . ' - PAIRfect Paws')

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Adoption Notifications</h2>
            <p>Updates and alerts regarding {{ $record->pet?->name ?? 'your pet' }}'s handover and release.</p>
        </div>
    </div>

    <div class="content-area">

        <div class="max-w-[860px] mx-auto space-y-5 py-3">

            <!-- Sub Navigation Tabs & Actions -->
            <div class="flex items-center justify-between gap-4 border-b border-[#e2ddd7] pb-3 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('adopter.handover.status', $record) }}" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-shield-cat mr-1"></i> Handover Status
                    </a>

                    <span class="btn btn-primary btn-sm pointer-events-none">
                        <i class="fa-solid fa-bell mr-1"></i> Notifications
                        @if ($unreadCount > 0)
                            <span class="rounded-full px-1.5 py-0.2 text-xs font-bold bg-white text-primary ml-1">
                                {{ $unreadCount }}
                            </span>
                        @endif
                    </span>

                    @if (!$record->adopter_outcome)
                        <a href="{{ route('adopter.confirm', $record) }}" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-clipboard-check mr-1"></i> Confirm Receipt
                        </a>
                    @endif
                </div>

                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('adopter.handover.notifications.read-all', $record) }}">
                        @csrf
                        <button type="submit" class="text-xs font-bold text-primary hover:underline cursor-pointer bg-transparent border-0 p-0">
                            <i class="fa-solid fa-check-double mr-1"></i> Mark all as read
                        </button>
                    </form>
                @endif
            </div>

            <!-- Notifications List -->
            @if ($notifications->isEmpty())
                <div class="empty-state">
                    <i class="fa-solid fa-bell-slash text-4xl text-[#bbb] mb-3 block"></i>
                    <h3 class="font-primary font-bold text-text-dark text-base m-0">No notifications yet</h3>
                    <p class="text-sm text-[#777] mt-1 m-0">Updates regarding {{ $record->pet?->name ?? 'your pet' }}'s handover will appear here.</p>
                </div>
            @else
                <div class="space-y-3.5">
                    @foreach ($notifications as $notif)
                        @php
                            $meta = $notif->meta;
                            $isActionNeeded = in_array($notif->kind, ['released', 'reminder']);
                        @endphp

                        <article class="relative overflow-hidden rounded-card border bg-white shadow-card transition {{ $notif->read ? 'border-[#e2ddd7]' : 'border-primary/40 ring-1 ring-primary/20' }}">

                            <!-- Left Accent Stripe -->
                            <span class="absolute inset-y-0 left-0 w-1.5 {{ $meta['accent'] }}" aria-hidden="true"></span>

                            <div class="p-5 pl-6">
                                <div class="flex items-start gap-3.5">

                                    <!-- Category Icon Chip -->
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $meta['chip'] }} {{ $meta['chipText'] }} mt-0.5">
                                        <i class="{{ $meta['icon'] }} text-sm"></i>
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <!-- Top Row: Category chip, New badge, Timestamp -->
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $meta['chip'] }} {{ $meta['chipText'] }}">
                                                {{ $meta['label'] }}
                                            </span>

                                            @if (!$notif->read)
                                                <span class="inline-flex items-center gap-1 text-xs font-bold text-primary">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                                                    New
                                                </span>
                                            @endif

                                            <span class="text-xs text-[#9e9e9e] ml-auto">
                                                {{ $notif->created_at->diffForHumans() }} &middot; {{ $notif->created_at->format('M j, g:i A') }}
                                            </span>
                                        </div>

                                        <!-- Title -->
                                        <h3 class="mt-2 text-sm leading-snug font-bold text-text-dark font-primary m-0">
                                            {{ $notif->title }}
                                        </h3>

                                        <!-- Message Body -->
                                        <p class="mt-1 text-sm leading-relaxed text-text-muted m-0">
                                            {{ $notif->body }}
                                        </p>

                                        <!-- Bottom Action Row -->
                                        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-[#f0ece5] pt-3">
                                            @if ($notif->action_label)
                                                <a href="{{ $notif->action_url ?: route('adopter.handover.status', $record) }}"
                                                   class="btn {{ $isActionNeeded ? 'btn-primary' : 'btn-secondary' }} btn-sm">
                                                    {{ $notif->action_label }} <i class="fa-solid fa-arrow-right text-[10px] ml-1"></i>
                                                </a>
                                            @endif

                                            @if (!$notif->read)
                                                <form method="POST" action="{{ route('adopter.handover.notification.read', $notif) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-[#777] underline underline-offset-4 hover:text-text-dark transition bg-transparent border-0 cursor-pointer p-0">
                                                        Mark as read
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

        </div>

    </div>

@endsection
