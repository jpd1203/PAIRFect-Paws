@extends('admin.layouts.app')

@section('title', 'Flagged Cases - PAIRfect Paws Admin')

@section('content')

    <div class="heading-text">
        <h2>Flagged Cases</h2>
        <p>Post-adoption welfare cases that need staff follow-up.</p>
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="flagCasesList">
        <button class="filter-btn filter-all active" data-filter-btn="all">All</button>
        <button class="filter-btn badge-pending" data-filter-btn="open">Open</button>
        <button class="filter-btn badge-flagged" data-filter-btn="intervention">Marked for Intervention</button>
        <button class="filter-btn badge-rejected" data-filter-btn="escalated">Escalated</button>
        <button class="filter-btn badge-approved" data-filter-btn="resolved">Resolved</button>
    </div>

    <div class="flag-cases" id="flagCasesList">
        @forelse ($flaggedCases as $case)
            @php
                $statuses = [];
                if ($case->resolved) {
                    $statuses[] = 'resolved';
                } else {
                    if ($case->marked_for_intervention) {
                        $statuses[] = 'intervention';
                    }
                    if ($case->is_escalated) {
                        $statuses[] = 'escalated';
                    }
                    if (empty($statuses)) {
                        $statuses[] = 'open';
                    }
                }
                $statusString = implode(' ', $statuses);
            @endphp
            <div class="flag-card {{ $case->resolved ? 'is-resolved !bg-white !border-[#e2ddd7]' : '' }}" data-filter-row data-status="{{ $statusString }}">
                <div class="flex justify-between items-start gap-3 mb-2">
                    <div>
                        <strong>{{ $case->checkIn?->pet?->name }}</strong>
                        <span class="{{ $case->resolved ? 'text-[#666]' : 'text-[#7d4b52]' }} text-[.85rem]"> — adopted by {{ $case->checkIn?->user?->full_name }}</span>
                    </div>
                    <span class="badge {{ $case->resolved ? 'badge-approved' : ($case->is_escalated ? 'badge-rejected' : ($case->marked_for_intervention ? 'badge-flagged' : 'badge-pending')) }}">
                        {{ $case->resolved ? 'Resolved' : ($case->is_escalated ? 'Escalated' : ($case->marked_for_intervention ? 'Intervention' : 'Open')) }}
                    </span>
                </div>

                <p class="flag-message">{{ $case->description }}</p>

                @if ($case->intervention_type)
                    <p class="flag-message"><strong>Intervention:</strong> {{ $case->intervention_type }} — {{ $case->intervention_notes }}</p>
                @endif
                @if ($case->escalation_reason)
                    <p class="flag-message"><strong>Escalation reason:</strong> {{ $case->escalation_reason }}</p>
                @endif
                @if ($case->resolved)
                    <p class="flag-message"><strong>Resolved by {{ $case->resolved_by }}</strong> on {{ $case->resolved_at?->format('M j, Y') }} — {{ $case->resolution_notes }}</p>
                @endif

                @unless ($case->resolved)
                    <div class="flag-actions">
                        <button type="button" class="btn btn-yellow btn-sm" onclick="openInterventionModal({{ $case->id }})">Mark for Intervention</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="openEscalateModal({{ $case->id }})">Escalate</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openReminderModal({{ $case->id }}, '{{ addslashes($case->checkIn?->user?->full_name ?? '') }}', '{{ addslashes($case->checkIn?->pet?->name ?? '') }}')">Send Reminder</button>
                        <button type="button" class="btn btn-resolve btn-sm" onclick="openResolveModal({{ $case->id }})">Resolve</button>
                    </div>
                @endunless
            </div>
        @empty
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                <h3>No flagged cases right now.</h3>
                <p>Great news! There are currently no post-adoption cases that require attention.</p>
            </div>
        @endforelse
    </div>

    <!-- Mark for Intervention -->
    <div class="custom-modal-backdrop" id="interventionModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Mark for Intervention</h2></div>
            <form id="interventionForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="form-group">
                        <label class="form-label">Intervention Type</label>
                        <select name="intervention_type" class="form-select" required>
                            <option value="">Select Type</option>
                            @foreach ($options::INTERVENTION_TYPES as $t)
                                <option>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="intervention_notes" class="form-control remarks-textarea" rows="3"></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('interventionModal')">Cancel</button>
                    <button type="submit" class="btn btn-yellow">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Escalate -->
    <div class="custom-modal-backdrop" id="escalateModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Escalate Case</h2></div>
            <form id="escalateForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="modal-note warning">This will flag the case as escalated to a supervisor for urgent review.</div>
                    <div class="form-group">
                        <label class="form-label">Escalation Reason</label>
                        <textarea name="escalation_reason" class="form-control remarks-textarea" rows="3" required></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('escalateModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Escalate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Send Reminder Modal -->
    <div class="custom-modal-backdrop" id="reminderModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2>Send Follow-up Reminder</h2>
                <small id="reminderSubheading" class="text-gray-500 font-medium"></small>
            </div>
            <form id="reminderForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs leading-relaxed text-amber-900 font-medium">
                        This will send an automated SMS & Email reminder notification to the adopter regarding their overdue post-adoption check-in.
                    </div>
                    <div class="form-group">
                        <label class="form-label">Custom Message (Optional)</label>
                        <textarea name="custom_message" class="form-control remarks-textarea" rows="3" placeholder="Add an optional custom message for the adopter..."></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reminderModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reminder</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resolve -->
    <div class="custom-modal-backdrop" id="resolveModal">
        <div class="custom-modal">
            <div class="custom-modal-header"><h2>Resolve Case</h2></div>
            <form id="resolveForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="form-group">
                        <label class="form-label">Resolution Notes</label>
                        <textarea name="resolution_notes" class="form-control remarks-textarea" rows="3" placeholder="How was this resolved?"></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resolveModal')">Cancel</button>
                    <button type="submit" class="btn btn-resolve">Mark Resolved</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function openInterventionModal(id) {
            document.getElementById('interventionForm').action = `/admin/flagged-cases/${id}/intervention`;
            openModal('interventionModal');
        }
        function openEscalateModal(id) {
            document.getElementById('escalateForm').action = `/admin/flagged-cases/${id}/escalate`;
            openModal('escalateModal');
        }
        function openReminderModal(id, adopter, pet) {
            document.getElementById('reminderSubheading').textContent = `Adopter: ${adopter} · Pet: ${pet}`;
            document.getElementById('reminderForm').action = `/admin/flagged-cases/${id}/reminder`;
            openModal('reminderModal');
        }
        function openResolveModal(id) {
            document.getElementById('resolveForm').action = `/admin/flagged-cases/${id}/resolve`;
            openModal('resolveModal');
        }
    </script>
@endpush
