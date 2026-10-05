@extends('layouts.app')

@section('title', 'My Check-ins - PAIRfect Paws')

@section('notification-bell-in-header', true)
@section('content')
    @include('partials.post-adoption-demo-notice')

    <div class="nonsticky-header">
        <div class="main-content-header">
            <div class="heading-text">
                <h2>My Check-ins</h2>
                <p>Your post-adoption reporting schedule
                @if($logs->isNotEmpty())
                    for {{ $logs->first()->adoptionApplication->pet->name }}.
                @else
                    .
                @endif</p>
            </div>

            @include('partials.notification-bell')
        </div>

        <div class="content-area !p-0">

            @if($groupedLogs->isEmpty())
                <!-- EMPTY STATE -->
                <div class="empty-state">
                    <i class="fa-solid fa-circle-check"></i>
                    <h3>No check-ins scheduled yet</h3>
                    <p>Once your adoption is finalized, your 3-day, 3-week, and 3-month check-ins will appear here.</p>
                </div>
            @else
                @foreach($groupedLogs as $appId => $appLogs)
                    @php
                        $petName = $appLogs->first()->adoptionApplication->pet->name ?? 'Unknown Pet';
                    @endphp
                    <!-- CHECK-IN SCHEDULE -->
                    <div class="info-card schedule-card shadow-card mb-6">

                        <h2>Check-in Schedule &mdash; {{ $petName }}</h2>

                        @foreach($appLogs as $log)
                            <div class="schedule-item">
                                <div class="schedule-info">
                                    {{-- Status dot --}}
                                    <span class="status-dot
                                        @if($log->display_status === 'Submitted')
                                            completed
                                        @elseif($log->display_status === 'Overdue')
                                            overdue
                                        @elseif(in_array($log->display_status, ['Upcoming', 'Placement ended'], true))
                                            upcoming
                                        @else
                                            pending
                                        @endif
                                    "></span>

                                    <strong> {{ $log->milestone->shortLabel() }}</strong>&middot; Due:{{ $log->scheduled_date->format('F j, Y') }}
                                </div>

                                <div class="schedule-actions">
                                    @php
                                        $badge = match($log->display_status) {
                                            'Submitted' => 'badge-completed',
                                            'Overdue'   => 'badge-overdue',
                                            'Upcoming'  => 'badge-upcoming',
                                            'Placement ended' => 'badge-upcoming',
                                            default     => 'badge-pending',
                                        };
                                    @endphp

                                    <span class="badge {{ $badge }}">{{ $log->display_status }}</span>

                                    {{-- Flagged / Resolution status --}}
                                    @if($log->resolution_outcome)
                                        @if($log->resolved_at)
                                            <span class="badge badge-completed"><i class="fa-solid fa-circle-check mr-1"></i> {{ $log->resolution_outcome === \App\Enums\ResolutionOutcome::Resolved ? 'Flag Resolved' : $log->resolution_outcome->label() }}</span>
                                        @elseif($log->resolution_outcome === \App\Enums\ResolutionOutcome::FollowUpRequired)
                                            <span class="badge badge-flagged"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Follow-up Required</span>
                                            @if($log->follow_up_notes)
                                                <span class="badge badge-completed"><i class="fa-solid fa-check mr-1"></i> Follow-up Provided</span>
                                            @endif
                                        @else
                                            <span class="badge badge-flagged"><i class="fa-solid fa-triangle-exclamation mr-1"></i> {{ $log->resolution_outcome->label() }}</span>
                                        @endif
                                    @elseif($log->display_is_flagged)
                                        <span class="badge badge-flagged"><i class="fa-solid fa-warning mr-1"></i> Flagged</span>
                                    @endif
                                    @if($log->has_presentation_demo)<span class="badge badge-pending">Demo</span>@endif

                                    {{-- Submit report --}}
                                    @if(in_array($log->display_status, ['Pending', 'Overdue'], true))
                                        <a class="btn btn-primary" href="{{ route('monitoring.create', $log) }}"><i class="fa-solid fa-paper-plane"></i>
                                            Submit Now
                                        </a>
                                    @elseif($log->display_status === 'Upcoming')
                                        <span class="text-[#888] text-sm">Opens {{ $log->scheduled_date->format('M d') }}</span>
                                    @elseif($log->display_status === 'Placement ended')
                                        <span class="text-[#888] text-sm">No further report is required</span>
                                    @else
                                        <span class="text-[#888] text-sm">Submitted {{ \App\Support\ManilaTime::format($log->submitted_date, 'M d, Y') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- SUBMITTED REPORTS -->
                    @php
                        $submittedReports = $appLogs->filter(fn($log) => $log->display_status === 'Submitted');
                    @endphp
                    @if($submittedReports->isNotEmpty())
                        <div class="info-card reports-card shadow-card mb-6">
                            <h2>Submitted Reports &mdash; {{ $petName }}</h2>
                            <div class="w-full overflow-x-auto custom-scrollbar">
                                <table class="reports-table min-w-[360px]">
                                    <thead>
                                        <tr>
                                            <th>Milestone</th>
                                            <th>Submitted</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($submittedReports as $report)
                                            <tr>
                                                <td>{{ $report->milestone->shortLabel() }}</td>
                                                <td>{{ \App\Support\ManilaTime::format($report->submitted_date,'F j, Y') }}</td>
                                                <td>
                                                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                                                        <span class="badge badge-completed">Submitted</span>
                                                        @if($report->resolution_outcome)
                                                            @if($report->resolved_at)
                                                                <span class="badge badge-completed"><i class="fa-solid fa-circle-check mr-1"></i> {{ $report->resolution_outcome === \App\Enums\ResolutionOutcome::Resolved ? 'Resolved' : $report->resolution_outcome->label() }}</span>
                                                            @elseif($report->resolution_outcome === \App\Enums\ResolutionOutcome::FollowUpRequired)
                                                                <span class="badge badge-flagged"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Follow-up Required</span>
                                                                @if($report->follow_up_notes)
                                                                    <span class="badge badge-completed"><i class="fa-solid fa-check mr-1"></i> Follow-up Provided</span>
                                                                @endif
                                                            @else
                                                                <span class="badge badge-flagged"><i class="fa-solid fa-triangle-exclamation mr-1"></i> {{ $report->resolution_outcome->label() }}</span>
                                                            @endif
                                                        @elseif($report->display_is_flagged)
                                                            <span class="badge badge-flagged"><i class="fa-solid fa-warning mr-1"></i> Under Review</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td><button type="button" class="btn btn-secondary" onclick="openReportViewModal({{ $report->id }})"><i class="fa-solid fa-eye"></i>View</button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </div>


    <!-- Report View Modal -->
    <div class="modal-overlay" id="reportViewModal">
        <div class="pet-modal report-modal" id="reportViewModalContent">
            <!-- Filled dynamically via fetch() -->
        </div>
    </div>

@endsection


@push('scripts')

    <script src="{{ asset('js/my-check-ins.js') }}"defer></script>

@endpush
