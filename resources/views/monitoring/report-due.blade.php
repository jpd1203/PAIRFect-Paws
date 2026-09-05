@extends('layouts.app')

@section('title', 'Submit Post-Adoption Report')

@section('content')
    <div class="sticky-header">
        <div class="heading-text">
            <h2>Submit Post-Adoption Report</h2>
            <p>Choose a welfare check-in that is ready for submission.</p>
        </div>
    </div>

    <div class="content-area">
        @if ($logs->isEmpty())
            <section class="empty-state mt-6 rounded-2xl border border-[#ded9d1] bg-white" aria-labelledby="noReportsDueTitle">
                <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                <h3 id="noReportsDueTitle">No reports are due right now</h3>
                <p>Your next report will appear here on its scheduled check-in date.</p>
                <a class="btn btn-secondary mt-5" href="{{ route('monitoring.my-checkins') }}">
                    View My Check-ins
                </a>
            </section>
        @else
            <section class="mt-6 overflow-hidden rounded-2xl border border-[#ded9d1] bg-white shadow-card" aria-labelledby="reportsDueTitle">
                <div class="border-b border-[#e8e3dc] bg-[#faf8f5] px-6 py-4">
                    <h3 id="reportsDueTitle" class="font-primary text-xl font-bold text-text-dark">
                        {{ $logs->count() }} {{ Str::plural('report', $logs->count()) }} ready
                    </h3>
                    <p class="mt-1 text-sm text-[#6f6865]">Complete overdue reports first to keep your monitoring record up to date.</p>
                </div>

                <div class="divide-y divide-[#e8e3dc]">
                    @foreach ($logs as $log)
                        <article class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-muted text-primary" aria-hidden="true">
                                    <i class="fa-solid fa-paw"></i>
                                </span>
                                <div>
                                    <h4 class="font-primary text-lg font-bold text-text-dark">
                                        {{ $log->adoptionApplication?->pet?->name ?? 'Adopted pet' }}
                                    </h4>
                                    <p class="mt-1 text-sm text-[#625c59]">
                                        {{ $log->milestone?->label() ?? 'Welfare check-in' }}
                                        <span aria-hidden="true">&middot;</span>
                                        Due {{ $log->scheduled_date->format('F j, Y') }}
                                    </p>
                                    @php
                                        $statusBadge = $log->display_status === 'Overdue'
                                            ? 'badge-overdue'
                                            : 'badge-pending';
                                    @endphp
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span class="badge {{ $statusBadge }}">{{ $log->display_status }}</span>
                                        @if ($log->is_flagged && ! $log->resolved_at)
                                            <span class="badge badge-flagged">Under staff review</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <a class="btn btn-primary self-start sm:self-auto" href="{{ route('monitoring.create', $log) }}">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                Open Report
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
