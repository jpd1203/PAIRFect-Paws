@extends('admin.layouts.app')

@section('title', 'Monitoring - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Monitoring</h2>
        <p>Post-adoption 3-3-3 check-in schedule across all adopters.</p>
    </div>

    <div class="my-3">
        <input type="text" data-search-input data-search-scope="monitoringTableBody" class="search-input w-full" placeholder="Search by adopter or pet name…">
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="monitoringTableBody">
        <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
        <button class="filter-btn badge-upcoming" data-filter-btn="upcoming">Upcoming</button>
        <button class="filter-btn badge-pending" data-filter-btn="pending">Pending</button>
        <button class="filter-btn badge-completed" data-filter-btn="completed">Completed</button>
        <button class="filter-btn badge-overdue" data-filter-btn="overdue">Overdue</button>
        <button class="filter-btn badge-flagged" data-filter-btn="flagged">Flagged</button>
    </div>

    <div class="records-container">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr><th>Adopter</th><th>Pet</th><th>Milestone</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="monitoringTableBody">
                    @forelse ($checkIns as $c)
                        <tr data-search-row data-search-text="{{ $c->user?->full_name }} {{ $c->pet?->name }}"
                            data-filter-row data-status="{{ $c->status_slug }}">
                            <td class="!text-left font-semibold">{{ $c->user?->full_name }}</td>
                            <td>{{ $c->pet?->name }}</td>
                            <td>{{ $c->milestone_display }}</td>
                            <td>{{ $c->due_date->format('M j, Y') }}</td>
                            <td><span class="badge {{ $c->status_badge_class }}">{{ $c->status_display }}</span></td>
                            <td>
                                @if (in_array($c->status_slug, ['pending', 'overdue']))
                                    <form action="{{ route('admin.monitoring.reminder', $c) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Remind</button>
                                    </form>
                                    <form action="{{ route('admin.monitoring.flag', $c) }}" method="POST" class="inline-block"
                                          onsubmit="return confirm('Flag this case for follow-up?')">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">Flag</button>
                                    </form>
                                @else
                                    <span class="text-[#bbb]">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-[#888] py-6">No monitoring cases yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
