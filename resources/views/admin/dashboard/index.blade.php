@extends('admin.layouts.app')

@section('title', 'Dashboard - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Dashboard</h2>
        <p>Overview of shelter activity and pending work.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-paw stat-icon text-primary"></i>
                <h6>Total Pets</h6>
            </div>
            <h1>{{ $totalPets }}</h1>
        </div>

        <div class="stat-card">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check stat-icon text-status-success-text"></i>
                <h6>Available for Adoption</h6>
            </div>
            <h1>{{ $availablePets }}</h1>
        </div>

        <div class="stat-card">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-heart-circle-check stat-icon text-[#EC4899]"></i>
                <h6>Adopted this month</h6>
            </div>
            <h1>0</h1>
        </div>

        <div class="stat-card">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-file stat-icon text-status-processing-text"></i>
                <h6>Pending Applications</h6>
            </div>
            <h1>0</h1>
        </div>

        <div class="stat-card">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-clipboard-list stat-icon text-blue-600"></i>
                <h6>Active monitoring cases</h6>
            </div>
            <h1>0</h1>
        </div>

        <div class="stat-card">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation stat-icon text-primary"></i>
                <h6>Flagged cases</h6>
            </div>
            <h1>0</h1>
        </div>
        
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-5 items-start">

        <div>

            <div class="dashboard-box">
                <h3>Application Pipeline</h3>
                <div class="pipeline-container">
                    <div class="pipeline-item">
                        <span class="pipeline scheduled" style="width: {{ max(40, $pipeline['scheduled'] * 12) }}px"></span>
                        Scheduled ({{ $pipeline['scheduled'] }})
                    </div>
                    <div class="pipeline-item">
                        <span class="pipeline underreview" style="width: {{ max(40, $pipeline['underreview'] * 12) }}px"></span>
                        Under Review ({{ $pipeline['underreview'] }})
                    </div>
                    <div class="pipeline-item">
                        <span class="pipeline approved" style="width: {{ max(40, $pipeline['approved'] * 12) }}px"></span>
                        Approved ({{ $pipeline['approved'] }})
                    </div>
                    <div class="pipeline-item">
                        <span class="pipeline rejected" style="width: {{ max(40, $pipeline['rejected'] * 12) }}px"></span>
                        Rejected ({{ $pipeline['rejected'] }})
                    </div>
                </div>
            </div>

            <div class="dashboard-box">
                <h3>Recent Applications</h3>
                <div class="records-container records-container-full">
                    <table class="w-full dashboard-table">
                        <thead>
                            <tr><th>Applicant</th><th>Pet</th><th>Status</th><th>Submitted</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($recentApplications as $app)
                                <tr>
                                    <td>{{ $app->first_name }} {{ $app->last_name }}</td>
                                    <td>{{ $app->pet?->name }}</td>
                                    <td><span class="badge {{ $app->status_badge_class }}">{{ $app->status_display }}</span></td>
                                    <td>{{ $app->created_at->format('M j, Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-[#888] py-6">No applications yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div>

            <div class="dashboard-box">
                <h3>Needs Attention</h3>
                <div class="alert-list">

                    @foreach ($overdueCheckIns as $checkIn)
                        <div class="alert-item alert-overdue">
                            <i class="fa-solid fa-circle-exclamation alert-icon text-status-danger-text"></i>
                            <span>
                                <strong>{{ $checkIn->pet?->name }}</strong>
                                — {{ $checkIn->milestone_display }} overdue since
                                {{ $checkIn->due_date->format('M j') }}
                            </span>
                        </div>
                    @endforeach

                    @foreach ($unresolvedFlags as $flag)
                        <div class="alert-item alert-flag">
                            <i class="fa-solid fa-flag alert-icon text-status-flagged-text"></i>
                            <span>
                                <strong>{{ $flag->checkIn?->pet?->name }}</strong>
                                — unresolved flagged case
                            </span>
                        </div>
                    @endforeach

                    @if ($overdueCheckIns->isEmpty() && $unresolvedFlags->isEmpty())
                        <p class="text-[#888] text-sm py-2">
                            Nothing needs attention right now.
                        </p>
                    @endif

                </div>
            </div>

            <div class="dashboard-box">
                <h3>Recent Activity</h3>
                <div class="flex flex-col gap-2.5">
                    @forelse ($recentActivity as $log)
                        <div class="text-[.85rem] border-b border-[#f0ece5] pb-2 last:border-b-0">
                            <strong>{{ $log->user_name }}</strong> {{ $log->action }}
                            <div class="text-[#999] text-[.75rem] mt-0.5">{{ $log->timestamp->diffForHumans() }}</div>
                        </div>
                    @empty
                        <p class="text-[#888] text-sm py-2">No activity recorded yet.</p>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

@endsection
