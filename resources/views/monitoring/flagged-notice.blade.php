@extends('layouts.app')

@section('title', 'Flagged Welfare Notice')

@section('content')
    <div class="sticky-header">
        <div class="heading-text">
            <h2>Flagged Welfare Notice</h2>
            <p>See post-adoption check-ins that are currently being reviewed by shelter staff.</p>
        </div>
    </div>

    <div class="content-area">
        @if ($flaggedLogs->isEmpty())
            <section class="empty-state mt-6 rounded-2xl border border-[#ded9d1] bg-white" aria-labelledby="noFlagsTitle">
                <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                <h3 id="noFlagsTitle">You have no active flagged notices</h3>
                <p>There are no unresolved welfare concerns on your post-adoption check-ins.</p>
                <a class="btn btn-secondary mt-5" href="{{ route('monitoring.my-checkins') }}">
                    View My Check-ins
                </a>
            </section>
        @else
            <div class="mt-6 rounded-xl border border-status-flagged-text bg-status-flagged-bg px-6 py-5 text-status-flagged-text" role="status">
                <h3 class="font-primary text-xl font-bold">{{ $flaggedLogs->count() }} active {{ Str::plural('notice', $flaggedLogs->count()) }}</h3>
                <p class="mt-2 text-sm leading-relaxed">Shelter staff may contact you for clarification or a welfare follow-up. You can continue to view and submit your scheduled check-ins normally.</p>
            </div>

            <section class="mt-6 overflow-hidden rounded-2xl border border-status-flagged-text bg-white shadow-card" aria-labelledby="flaggedReportsTitle">
                <h3 id="flaggedReportsTitle" class="sr-only">Flagged check-ins</h3>
                <div class="divide-y divide-[#e6d9e6]">
                    @foreach ($flaggedLogs as $log)
                        <article class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-status-flagged-bg text-status-flagged-text" aria-hidden="true">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </span>
                                <div>
                                    <h4 class="font-primary text-lg font-bold text-text-dark">
                                        {{ $log->adoptionApplication?->pet?->name ?? 'Adopted pet' }}
                                    </h4>
                                    <p class="mt-1 text-sm text-[#625c59]">
                                        {{ $log->milestone?->label() ?? 'Welfare check-in' }}
                                        <span aria-hidden="true">&middot;</span>
                                        @if ($log->submitted_date)
                                            Submitted {{ \App\Support\ManilaTime::format($log->submitted_date, 'F j, Y') }}
                                        @elseif ($log->display_status === 'Overdue')
                                            Due {{ $log->scheduled_date->format('F j, Y') }}
                                        @else
                                            Scheduled for {{ $log->scheduled_date->format('F j, Y') }}
                                        @endif
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span class="badge badge-flagged">Under staff review</span>
                                        @if ($log->submitted_date)
                                            <span class="badge badge-completed">Report submitted</span>
                                        @elseif ($log->display_status === 'Overdue')
                                            <span class="badge badge-overdue">Overdue</span>
                                        @elseif ($log->display_status === 'Pending')
                                            <span class="badge badge-pending">Due today</span>
                                        @else
                                            <span class="badge badge-upcoming">Upcoming</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if (! $log->submitted_date && in_array($log->display_status, ['Pending', 'Overdue'], true))
                                <a class="btn btn-primary self-start sm:self-auto" href="{{ route('monitoring.create', $log) }}">
                                    Submit Report
                                </a>
                            @else
                                <a class="btn btn-secondary self-start sm:self-auto" href="{{ route('monitoring.my-checkins') }}">
                                    View Check-in Schedule
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <div class="contact-card">
                <p>If shelter staff need more information, they will contact you using the details on your account. Keep your contact information current and respond promptly to follow-up requests.</p>
            </div>
        @endif
    </div>
@endsection
