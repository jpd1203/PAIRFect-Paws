@extends('layouts.app')

@section('title', 'My Check-ins - PAIRfect Paws')

@php use App\Models\CheckIn; @endphp

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>My Check-ins</h2>
            <p>Your post-adoption reporting schedule{{ $pet ? " for {$pet->name}" : '' }}.</p>
        </div>
    </div>

    <div class="content-area">

        @if (!$hasActiveAdoption)
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                <h3>No check-ins scheduled yet</h3>
                <p>Once your adoption is finalized, your 3-day, 3-week, and 3-month check-ins will appear here.</p>
            </div>
        @else
            <div class="info-card schedule-card">

                <h2>Check-in Schedule &mdash; {{ $pet?->name }}</h2>

                @foreach ($schedule as $checkIn)
                    <div class="schedule-item">

                        <div class="schedule-info">
                            <span class="status-dot {{ $checkIn->status_dot_class }}"></span>
                            <strong>{{ $checkIn->milestone_display }}</strong> &middot; Due: {{ $checkIn->due_date->format('F j, Y') }}
                        </div>

                        <div class="schedule-actions">

                            <span class="badge {{ $checkIn->status_badge_class }}">
                                {{ $checkIn->status_display }}
                            </span>

                            @if (in_array($checkIn->status, [CheckIn::STATUS_PENDING, CheckIn::STATUS_OVERDUE]))
                                <a class="btn btn-primary" href="{{ route('monitoring.my-checkins') }}">
                                    Submit Now
                                </a>
                            @endif

                        </div>

                    </div>
                @endforeach

            </div>

            <!-- SUBMITTED REPORTS -->
            <div class="info-card reports-card">

                <h2>Submitted Reports</h2>

                @if ($submittedReports->isEmpty())
                    <p class="text-[#888] text-center py-3">No reports submitted yet.</p>
                @else
                    <div class="table-responsive overflow-x-auto w-full">
                        <table class="reports-table w-full min-w-[480px]">

                            <thead>
                                <tr>
                                    <th>Milestone</th>
                                    <th>Submitted</th>
                                    <th>Health Status</th>
                                    <th></th>
                                </tr>
                            </thead>

                            <tbody>

                                @foreach ($submittedReports as $report)
                                    <tr>
                                        <td>{{ str_replace(' Check-in', '', $report->milestone_report_label) }}</td>
                                        <td class="whitespace-nowrap">{{ $report->report_date->format('F j, Y') }}</td>
                                        <td>
                                            <span class="badge {{ $report->health_badge_class }}">
                                                {{ $report->health_status }}
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-secondary" onclick="openReportViewModal({{ $report->id }})">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach

                            </tbody>

                        </table>
                    </div>
                @endif

            </div>
        @endif

    </div>

    <!-- Report View Modal -->
    <div class="modal-overlay" id="reportViewModal">
        <div class="pet-modal report-modal" id="reportViewModalContent">
            <!-- Filled dynamically via fetch() -->
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/my-check-ins.js') }}" defer></script>
@endpush
