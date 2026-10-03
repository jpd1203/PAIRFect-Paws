@extends('layouts.app')

@section('title', 'My Check-ins - PAIRfect Paws')

@section('content')

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
                                        @elseif($log->display_status === 'Upcoming')
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
                                            default     => 'badge-pending',
                                        };
                                    @endphp

                                    <span class="badge {{ $badge }}">{{ $log->display_status }}</span>

                                    {{-- Flagged status --}}
                                    @if($log->is_flagged && !$log->resolved_at)
                                        <span class="badge badge-flagged"><i class="fa-solid fa-warning mr-1"></i> Flagged</span>
                                    @endif

                                    {{-- Submit report --}}
                                    @if(in_array($log->display_status, ['Pending', 'Overdue'], true))
                                        <a class="btn btn-primary" href="{{ route('monitoring.create', $log) }}"><i class="fa-solid fa-paper-plane"></i>
                                            Submit Now
                                        </a>
                                    @elseif($log->display_status === 'Upcoming')
                                        <span class="text-[#888] text-sm">Opens {{ $log->scheduled_date->format('M d') }}</span>
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
                                                <td><span class="badge badge-completed">Submitted</span></td>
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