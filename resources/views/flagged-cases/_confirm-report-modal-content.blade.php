<div class="modal-header">
    <div>
        <h2>Confirm Report</h2>
        <p>{{ str_replace(' Check-in', ' Report', $report->milestone_report_label) }} &mdash; {{ $report->pet?->name ?? '' }}</p>
    </div>
</div>

<div class="report-banner">
    Your report will be submitted to the shelter for review. Thank you!
</div>

<div class="profile-table">

    <div class="profile-row">
        <span>Health Status</span>
        <span>{{ $report->health_status }}</span>
    </div>

    <div class="profile-row">
        <span>Eating &amp; Drinking</span>
        <span>{{ $report->eating_and_drinking }}</span>
    </div>

    <div class="profile-row">
        <span>Behavior</span>
        <span>{{ $report->behavior }}</span>
    </div>

    <div class="profile-row">
        <span>Vet Visit</span>
        <span>{{ $report->vet_visit_display }}</span>
    </div>

    <div class="profile-row">
        <span>Living Conditions</span>
        <span>{{ $report->living_conditions }}</span>
    </div>

</div>

<div class="modal-actions">

    <button type="button" class="btn btn-secondary" onclick="closeConfirmReportModal()">
        Close
    </button>

    <form action="{{ route('flagged.confirmSubmit') }}" method="POST" enctype="multipart/form-data" id="confirmReportForm" class="inline-block">
        @csrf
        <input type="hidden" name="check_in_id" value="{{ $report->check_in_id }}">
        <input type="hidden" name="pet_id" value="{{ $report->pet_id }}">
        <input type="hidden" name="milestone" value="{{ $report->milestone }}">
        <input type="hidden" name="health_status" value="{{ $report->health_status }}">
        <input type="hidden" name="eating_and_drinking" value="{{ $report->eating_and_drinking }}">
        <input type="hidden" name="behavior" value="{{ $report->behavior }}">
        <input type="hidden" name="living_conditions" value="{{ $report->living_conditions }}">
        <input type="hidden" name="vet_visit" value="{{ $report->vet_visit ? 1 : 0 }}">
        <input type="hidden" name="concerns" value="{{ $report->concerns }}">
        <button type="submit" class="btn btn-primary">
            Submit
        </button>
    </form>

</div>
