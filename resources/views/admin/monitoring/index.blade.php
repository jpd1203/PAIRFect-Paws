@extends('admin.layouts.app')

@section('title', 'Monitoring - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Post-Adoption Monitoring</h2>
        <p>Track all welfare check-ins for adopted animals</p>
    </div>

    <div class="my-5">
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
                    <tr><th>Adopter</th><th>Pet</th><th>Milestone</th><th>Due Date</th><th>Status</th><th class="text-center">Actions</th></tr>
                </thead>
                <tbody id="monitoringTableBody">
                    @forelse ($checkIns as $c)
                        <tr data-search-row data-search-text="{{ $c->user?->full_name }} {{ $c->pet?->name }}"
                            data-filter-row data-status="{{ $c->status_slug }}">
                            <td class="font-semibold">{{ $c->user?->full_name }}</td>
                            <td>{{ $c->pet?->name }}</td>
                            <td>{{ $c->milestone_display }}</td>
                            <td>{{ $c->due_date->format('M j, Y') }}</td>
                            <td><span class="badge {{ $c->status_badge_class }}">{{ $c->status_display }}</span></td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if ($c->status_slug === 'pending')
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="openMonitoringReminderModal({{ $c->id }}, '{{ addslashes($c->user?->full_name ?? '') }}', '{{ addslashes($c->pet?->name ?? '') }}', '{{ addslashes($c->milestone_display ?? '') }}')">Remind</button>
                                    @elseif ($c->status_slug === 'overdue')
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openMonitoringFlagModal({{ $c->id }}, '{{ addslashes($c->user?->full_name ?? '') }}', '{{ addslashes($c->pet?->name ?? '') }}', '{{ addslashes($c->milestone_display ?? '') }}')">Flag</button>
                                    @elseif ($c->status_slug === 'flagged' || $c->status_slug === 'completed')
                                        <button type="button" class="btn btn-outline-primary btn-sm flex items-center gap-1" onclick="openMonitoringViewModal({{ json_encode([
                                            'id' => $c->id,
                                            'adopter' => $c->user?->full_name ?? 'Adopter',
                                            'pet' => $c->pet?->name ?? 'Pet',
                                            'milestone' => $c->milestone_display,
                                            'status' => $c->status_display,
                                            'status_slug' => $c->status_slug,
                                            'due_date' => $c->due_date->format('M j, Y'),
                                            'health' => $c->report?->health_status ?? 'N/A',
                                            'eating' => $c->report?->eating_and_drinking ?? 'Normal appetite',
                                            'behavior' => $c->report?->behavior ?? 'Good behavior at home',
                                            'living' => $c->report?->living_conditions ?? 'Indoor home environment',
                                            'vet' => $c->report?->vet_visit ? 'Yes' : 'No',
                                            'concerns' => $c->report?->concerns ?? $c->flaggedCase?->description ?? 'No specific concerns reported.'
                                        ]) }})">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                    @else
                                        <span class="text-[#bbb]">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-[#888] py-6">No monitoring cases yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Send Reminder Modal -->
    <div class="custom-modal-backdrop" id="monitoringReminderModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2>Send Welfare Check-in Reminder</h2>
                <small id="monitoringReminderSubheading" class="text-gray-500 font-medium"></small>
            </div>
            <form id="monitoringReminderForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-xs leading-relaxed text-emerald-800 font-medium">
                        Sending a reminder will notify the adopter to submit their post-adoption welfare check-in report.
                    </div>
                    <div class="form-group">
                        <label class="form-label">Custom Message (Optional)</label>
                        <textarea name="custom_message" class="form-control remarks-textarea" rows="3" placeholder="Add an optional custom note to include in the notification..."></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('monitoringReminderModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reminder</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Flag Case Modal -->
    <div class="custom-modal-backdrop" id="monitoringFlagModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2>Flag Post-Adoption Case</h2>
                <small id="monitoringFlagSubheading" class="text-gray-500 font-medium"></small>
            </div>
            <form id="monitoringFlagForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs leading-relaxed text-amber-900 font-medium">
                        Flagging this case will mark it for staff follow-up and move it to the Flagged Cases dashboard.
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason for Flagging (Optional)</label>
                        <textarea name="reason" class="form-control remarks-textarea" rows="3" placeholder="Provide notes or reason for flagging this welfare case..."></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('monitoringFlagModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Flag Case</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Report Modal -->
    <div class="custom-modal-backdrop" id="monitoringViewModal">
        <div class="custom-modal max-w-lg">
            <div class="custom-modal-header">
                <h2>Post-Adoption Monitoring Report</h2>
                <small id="monitoringViewSubheading" class="text-gray-500 font-medium"></small>
            </div>
            <div class="custom-modal-body space-y-3">
                <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-200">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Status</span>
                    <span id="monitoringViewStatus" class="badge"></span>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-cream-50 p-3 rounded-lg border border-cream-200">
                        <span class="text-xs font-bold text-gray-500 block">Health Status</span>
                        <strong id="monitoringViewHealth" class="text-gray-800"></strong>
                    </div>
                    <div class="bg-cream-50 p-3 rounded-lg border border-cream-200">
                        <span class="text-xs font-bold text-gray-500 block">Vet Visit</span>
                        <strong id="monitoringViewVet" class="text-gray-800"></strong>
                    </div>
                </div>
                <div class="text-sm bg-white p-3 rounded-lg border border-gray-200 space-y-2">
                    <div>
                        <span class="text-xs font-bold text-gray-500 block">Eating & Drinking Habits</span>
                        <p id="monitoringViewEating" class="text-gray-700 text-xs mt-0.5"></p>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-gray-500 block">Behavior at Home</span>
                        <p id="monitoringViewBehavior" class="text-gray-700 text-xs mt-0.5"></p>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-gray-500 block">Living Conditions</span>
                        <p id="monitoringViewLiving" class="text-gray-700 text-xs mt-0.5"></p>
                    </div>
                </div>
                <div class="text-sm bg-amber-50 p-3 rounded-lg border border-amber-200">
                    <span class="text-xs font-bold text-amber-800 block">Concerns & Flag Notes</span>
                    <p id="monitoringViewConcerns" class="text-amber-900 text-xs mt-0.5 leading-relaxed"></p>
                </div>
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeModal('monitoringViewModal')">Close</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function openMonitoringReminderModal(id, adopter, pet, milestone) {
            document.getElementById('monitoringReminderSubheading').textContent = `Adopter: ${adopter} · Pet: ${pet} · ${milestone}`;
            document.getElementById('monitoringReminderForm').action = `/admin/monitoring/${id}/reminder`;
            openModal('monitoringReminderModal');
        }

        function openMonitoringFlagModal(id, adopter, pet, milestone) {
            document.getElementById('monitoringFlagSubheading').textContent = `Adopter: ${adopter} · Pet: ${pet} · ${milestone}`;
            document.getElementById('monitoringFlagForm').action = `/admin/monitoring/${id}/flag`;
            openModal('monitoringFlagModal');
        }

        function openMonitoringViewModal(data) {
            document.getElementById('monitoringViewSubheading').textContent = `Adopter: ${data.adopter} · Pet: ${data.pet} · ${data.milestone}`;
            const statusEl = document.getElementById('monitoringViewStatus');
            statusEl.textContent = data.status;
            statusEl.className = `badge ${data.status_slug === 'flagged' ? 'badge-flagged' : 'badge-completed'}`;

            document.getElementById('monitoringViewHealth').textContent = data.health;
            document.getElementById('monitoringViewVet').textContent = data.vet;
            document.getElementById('monitoringViewEating').textContent = data.eating;
            document.getElementById('monitoringViewBehavior').textContent = data.behavior;
            document.getElementById('monitoringViewLiving').textContent = data.living;
            document.getElementById('monitoringViewConcerns').textContent = data.concerns;

            openModal('monitoringViewModal');
        }
    </script>
@endpush
