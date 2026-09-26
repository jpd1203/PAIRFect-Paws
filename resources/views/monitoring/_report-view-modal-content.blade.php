<div class="modal-header">
    <div>
        <h2>Post-Adoption Report</h2>
        <p>
            Applicant: You &middot; Pet: {{ $report->pet?->name ?? '—' }} &middot; {{ $report->milestone_display }}
        </p>
    </div>
</div>

<div class="profile-table">

    <div class="profile-row">
        <span>Health Status</span>
        <span>{{ $report->pet_current_status?->value ?? '—' }}</span>
    </div>

    <div class="profile-row">
        <span>Eating &amp; Drinking</span>
        <span>{{ $report->eating_habits ?? '—' }}</span>
    </div>

    <div class="profile-row">
        <span>Behavior</span>
        <span>{{ $report->behavioral_observations ?? '—' }}</span>
    </div>

    <div class="profile-row">
        <span>Vet Visit</span>
        <span>{{ $report->vet_visit_details ?? '—' }}</span>
    </div>

    <div class="profile-row">
        <span>Living Conditions</span>
        <span>{{ $report->living_conditions ?? '—' }}</span>
    </div>

</div>

@if (!empty(trim((string) $report->concerns)))
    <span class="notes-label">Additional Notes</span>
    <div class="notes-box">
        {{ $report->concerns }}
    </div>
@endif

<div class="modal-actions">
    <button type="button" class="btn btn-secondary" onclick="closeReportViewModal()">
        Close
    </button>
</div>
