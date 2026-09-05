@php
    $postAdoptionClock = app(\App\Services\PostAdoptionClock::class);
    $testDate = $postAdoptionClock->travelDate();
@endphp

@if ($testDate)
    <div
        role="status"
        style="margin-bottom: 1rem; border: 2px solid #d97706; border-radius: .75rem; background: #fffbeb; color: #78350f; padding: .8rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;"
    >
        <div>
            <strong><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Testing clock active</strong>
            <span style="margin-left: .35rem;">
                Post-adoption check-ins are using {{ $testDate->format('F j, Y') }}.
                The real date is {{ $postAdoptionClock->realToday()->format('F j, Y') }}.
            </span>
        </div>

        @if (auth()->user()?->isAdmin())
            <a href="{{ route('time-travel.index') }}" style="color: #78350f; font-weight: 700; text-decoration: underline;">
                Manage or reset
            </a>
        @endif
    </div>
@endif
