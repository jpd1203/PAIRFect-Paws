@extends('layouts.app')

@section('title', 'Overdue Check-in Notice')

@section('content')
    <div class="nonsticky-header">
        <div class="main-content-header">
            <div class="heading-text">
                <h2>Overdue Check-in Notice</h2>
                <p>Review any welfare reports that have passed their scheduled date.</p>
            </div>

            @include('partials.notification-bell')
        </div>

        <div class="content-area-nonsticky">
            @if ($overdueLogs->isEmpty())
                <section class="empty-state mt-6 rounded-2xl border border-[#ded9d1] bg-white" aria-labelledby="noOverdueTitle">
                    <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                    <h3 id="noOverdueTitle">You have no overdue check-ins</h3>
                    <p>Your post-adoption reporting schedule is currently up to date.</p>
                    <a class="btn btn-secondary mt-5" href="{{ route('monitoring.my-checkins') }}">
                        View My Check-ins
                    </a>
                </section>
            @else
                <div class="alert-card" role="status">
                    <h3>{{ $overdueLogs->count() }} overdue {{ Str::plural('report', $overdueLogs->count()) }} need attention</h3>
                    <p>Please submit each report as soon as possible. Continued missed check-ins may be escalated to shelter staff for follow-up.</p>
                </div>

                <section class="mt-6 overflow-hidden rounded-2xl border border-[#e4b9b9] bg-white shadow-card" aria-labelledby="overdueReportsTitle">
                    <h3 id="overdueReportsTitle" class="sr-only">Overdue reports</h3>
                    <div class="divide-y divide-[#ead7d7]">
                        @foreach ($overdueLogs as $log)
                            <article class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-start gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-status-danger-bg text-status-danger-text" aria-hidden="true">
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                    </span>
                                    <div>
                                        <h4 class="font-primary text-lg font-bold text-text-dark">
                                            {{ $log->adoptionApplication?->pet?->name ?? 'Adopted pet' }}
                                        </h4>
                                        <p class="mt-1 text-sm text-[#625c59]">
                                            {{ $log->milestone?->label() ?? 'Welfare check-in' }} was due
                                            {{ $log->scheduled_date->format('F j, Y') }}
                                        </p>
                                        <p class="mt-2 text-xs text-[#77716e]">
                                            @if ($log->reminders_sent > 0)
                                                {{ $log->reminders_sent }} {{ Str::plural('reminder', $log->reminders_sent) }} sent
                                                @if ($log->last_reminder_sent_at)
                                                    &middot; Latest {{ \App\Support\ManilaTime::format($log->last_reminder_sent_at, 'F j, Y') }}
                                                @endif
                                            @else
                                                No email reminders have been recorded yet.
                                            @endif
                                        </p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span class="badge badge-overdue">Overdue</span>
                                            @if ($log->is_flagged && ! $log->resolved_at)
                                                <span class="badge badge-flagged">Under staff review</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <a class="btn btn-primary self-start sm:self-auto" href="{{ route('monitoring.create', $log) }}"><i class="fa-solid fa-file-circle-exclamation"></i>
                                    Submit Overdue Report
                                </a>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
