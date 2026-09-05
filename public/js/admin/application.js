/** Applications — drives Review / Schedule Interview / Interview Notes / History / Compatibility modals. */
const APPLICATIONS = JSON.parse(document.getElementById('applicationData')?.textContent || '[]');
const SCHEDULABLE_APPLICATIONS = APPLICATIONS.filter(
    (application) => application.status === 'primarycandidate'
        || (application.status === 'scheduled' && application.is_primary)
        || (application.status === 'underreview'
            && !application.is_primary
            && ['Verified', 'LegacyReview'].includes(application.document_verification_status))
);

let visibleApplicantMatches = [];
let highlightedApplicantIndex = -1;

function findApp(id) {
    return APPLICATIONS.find((a) => a.id === id);
}

function statusBadgeClass(status) {
    return `badge-${status}`;
}

function openReviewModal(id) {
    const a = findApp(id);
    if (!a) return;

    document.getElementById('reviewApplicantPet').textContent = `Application Review — ${a.pet ?? ''}`;
    document.getElementById('reviewSubheading').textContent = `Applicant: ${a.full_name} · Pet: ${a.pet ?? '—'} · Submitted: ${a.submitted}`;

    const badge = document.getElementById('reviewStatusBadge');
    badge.textContent = a.status_display;
    badge.className = `badge ${statusBadgeClass(a.status)}`;

    document.getElementById('rFullName').textContent = a.full_name;
    document.getElementById('rContact').textContent = a.contact;
    document.getElementById('rEmail').textContent = a.email;
    document.getElementById('rAddress').textContent = a.address;
    document.getElementById('rActivity').textContent = a.physical_activity_level ?? '—';
    document.getElementById('rTime').textContent = a.time_availability ?? '—';
    document.getElementById('rExperience').textContent = a.prior_pet_experience ?? '—';
    document.getElementById('rHousing').textContent = a.housing_type ?? '—';
    document.getElementById('rHousehold').textContent = a.household_composition ?? '—';
    document.getElementById('rIncome').textContent = a.monthly_income_range ?? '—';
    document.getElementById('rDocumentLink').href = a.document_url;
    document.getElementById('rDocumentStatus').textContent = a.document_verification_status;
    document.getElementById('rVerificationLink').href = a.verification_url;
    document.getElementById('rQueuePosition').textContent = a.queue_position ? `#${a.queue_position}` : 'Queue closed';
    document.getElementById('rCandidateRole').textContent = a.is_primary ? 'Primary candidate' : (a.status === 'waitlisted' ? 'Waitlisted' : 'Not active');
    document.getElementById('rTimeoutRow').style.display = a.admin_review_flagged ? 'flex' : 'none';

    document.getElementById('rHistoryRow').style.display = a.has_history ? 'flex' : 'none';
    document.getElementById('rHistoryBtn').onclick = () => openAdoptionHistoryModal(a.history_url);

    document.getElementById('rCompatRow').style.display = a.has_compatibility ? 'flex' : 'none';
    document.getElementById('rCompatBtn').onclick = () => openCompatibilityModal(a);

    const compatBanner = document.getElementById('rCompatBanner');
    if (a.has_compatibility) {
        const overall = a.compatibility.overall;
        const label = overall >= 80 ? 'High Match' : overall >= 60 ? 'Good Match' : overall >= 40 ? 'Fair Match' : 'Low Match';
        compatBanner.style.display = 'block';
        compatBanner.style.background = overall >= 60 ? '#295F51' : overall >= 40 ? '#614E34' : '#773E47';
        compatBanner.textContent = `${label} – ${overall}/100`;
    } else {
        compatBanner.style.display = 'none';
    }

    // Interview section only shown once an interview has actually happened
    const interviewSection = document.getElementById('rInterviewSection');
    if (a.interview_date) {
        interviewSection.style.display = 'block';
        document.getElementById('rInterviewDate').textContent = `${a.interview_date} at ${a.interview_time ?? ''}`;
        document.getElementById('rConductedBy').textContent = a.conducted_by ?? '—';
        document.getElementById('rInterviewNotesRow').style.display = a.interview_notes ? 'flex' : 'none';
        document.getElementById('rInterviewNotesText').style.display = a.interview_notes ? 'block' : 'none';
        document.getElementById('rInterviewNotesText').textContent = a.interview_notes ?? '';
    } else {
        interviewSection.style.display = 'none';
    }

    document.getElementById('rDecisionRemarksSection').style.display = a.decision_remarks ? 'block' : 'none';
    document.getElementById('rDecisionRemarksText').textContent = a.decision_remarks ?? '';

    // Footer actions depend on where the application is in the pipeline
    const actionsRow = document.getElementById('rActionsRow');
    const scheduleBtn = document.getElementById('rScheduleBtn');
    const approveBtn = document.getElementById('rApproveBtn');
    const rejectBtn = document.getElementById('rRejectBtn');
    const noShowBtn = document.getElementById('rNoShowBtn');
    const withdrawBtn = document.getElementById('rWithdrawBtn');
    const overrideBtn = document.getElementById('rOverrideBtn');
    const decisionForm = document.getElementById('rDecisionForm');
    const outcomeForm = document.getElementById('rQueueOutcomeForm');
    const overrideForm = document.getElementById('rOverrideForm');
    decisionForm.action = a.decide_action;
    outcomeForm.action = a.queue_outcome_action;
    overrideForm.action = a.override_action;

    [scheduleBtn, approveBtn, rejectBtn, noShowBtn, withdrawBtn, overrideBtn]
        .forEach((button) => {
            button.style.display = 'none';
            button.onclick = null;
        });

    scheduleBtn.textContent = 'Schedule Interview';
    scheduleBtn.className = 'btn btn-blue';
    rejectBtn.textContent = 'Reject';
    rejectBtn.className = 'btn btn-danger';

    if (a.status === 'primarycandidate' || (a.status === 'underreview' && !a.is_primary)) {
        scheduleBtn.style.display = 'inline-flex';
        scheduleBtn.onclick = () => { closeModal('applicationReviewModal'); openScheduleModal(a); };
        rejectBtn.style.display = 'inline-flex';
        rejectBtn.onclick = () => submitDecision(decisionForm, 'Rejected');
    } else if (a.status === 'underreview' && a.is_primary) {
        approveBtn.style.display = 'inline-flex';
        rejectBtn.style.display = 'inline-flex';
        approveBtn.onclick = () => submitDecision(decisionForm, 'Approved');
        rejectBtn.onclick = () => submitDecision(decisionForm, 'Rejected');
    } else if (a.status === 'documentflagged') {
        rejectBtn.style.display = 'inline-flex';
        rejectBtn.onclick = () => submitDecision(decisionForm, 'Rejected');
    } else if (a.status === 'scheduled' && a.is_primary) {
        scheduleBtn.textContent = 'Reschedule Interview';
        scheduleBtn.style.display = 'inline-flex';
        scheduleBtn.onclick = () => { closeModal('applicationReviewModal'); openScheduleModal(a); };
    }

    if (a.is_primary && (a.status === 'scheduled' || a.status === 'underreview')) {
        withdrawBtn.style.display = 'inline-flex';
        withdrawBtn.onclick = () => submitQueueOutcome(outcomeForm, 'Withdrawn');
    }
    if (a.is_primary && a.status === 'scheduled') {
        noShowBtn.style.display = 'inline-flex';
        noShowBtn.onclick = () => submitQueueOutcome(outcomeForm, 'NoShow');
    }
    if (a.can_override && !a.is_primary && a.status === 'waitlisted') {
        overrideBtn.style.display = 'inline-flex';
        overrideBtn.onclick = () => submitOverride(overrideForm);
    }

    openModal('applicationReviewModal');
}

function submitQueueOutcome(form, outcome) {
    const label = outcome === 'NoShow' ? 'no-show' : 'withdrawal';
    const reason = window.prompt(`Reason for ${label} (required):`);
    if (reason === null) return;
    if (!reason.trim()) {
        window.alert('A reason is required so the queue action is recorded in the audit trail.');
        return;
    }
    document.getElementById('rQueueOutcomeInput').value = outcome;
    document.getElementById('rQueueReasonInput').value = reason.trim();
    form.submit();
}

function submitOverride(form) {
    const reason = window.prompt('Administrative override reason (minimum 10 characters):');
    if (reason === null) return;
    if (reason.trim().length < 10) {
        window.alert('Please provide a specific reason of at least 10 characters.');
        return;
    }
    document.getElementById('rOverrideReasonInput').value = reason.trim();
    form.submit();
}

function submitDecision(form, decision) {
    let remarks = '';
    if (decision === 'Rejected') {
        remarks = window.prompt("Reason for rejection (optional):");
        if (remarks === null) return; // Cancelled
    } else if (decision === 'Approved') {
        remarks = window.prompt("Approval remarks (optional):");
        if (remarks === null) return; // Cancelled
    }

    document.getElementById('rDecisionInput').value = decision;
    document.getElementById('rDecisionRemarksInput').value = remarks || '';
    form.submit();
}

function openScheduleModal(a) {
    document.getElementById('scheduleSubheading').textContent = `Applicant: ${a.full_name} · Pet: ${a.pet ?? '—'} · Submitted: ${a.submitted}`;
    document.getElementById('scheduleAppId').value = a.id;
    const modal = document.getElementById('scheduleInterviewModal');
    modal.querySelector('h2').textContent = a.status === 'scheduled' ? 'Reschedule Interview' : 'Schedule Interview';
    modal.querySelector('button[type="submit"]').textContent = a.status === 'scheduled' ? 'Confirm Reschedule' : 'Confirm';
    modal.querySelector('input[name="interview_date"]').value = a.interview_date_input ?? '';
    modal.querySelector('input[name="interview_time"]').value = a.interview_time_input ?? '';
    openModal('scheduleInterviewModal');
}

function openTopScheduleModal() {
    clearSelectedApplicant(false);
    openModal('scheduleNewInterviewTopModal');
    window.setTimeout(() => {
        document.getElementById('applicantSearch')?.focus();
        renderApplicantMatches('');
    }, 0);
}

function renderApplicantMatches(query) {
    const results = document.getElementById('applicantSearchResults');
    const search = document.getElementById('applicantSearch');
    if (!results || !search || search.readOnly) return;

    const term = query.trim().toLowerCase();
    visibleApplicantMatches = SCHEDULABLE_APPLICATIONS
        .filter((application) => {
            const searchable = [application.full_name, application.email, application.pet]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            return searchable.includes(term);
        })
        .slice(0, 8);

    highlightedApplicantIndex = -1;
    results.replaceChildren();

    if (visibleApplicantMatches.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'applicant-search-empty';
        empty.textContent = SCHEDULABLE_APPLICATIONS.length === 0
            ? 'There are no document-verified or promoted applications to schedule.'
            : 'No matching schedulable applicants found.';
        results.appendChild(empty);
    } else {
        visibleApplicantMatches.forEach((application, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'applicant-search-option';
            option.setAttribute('role', 'option');
            option.dataset.index = String(index);

            const name = document.createElement('strong');
            name.textContent = application.full_name;
            const details = document.createElement('small');
            details.textContent = `${application.email || 'No email'} · Applying for ${application.pet || 'Unknown pet'} · ${application.submitted_full}`;

            option.append(name, details);
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => selectApplicant(application));
            results.appendChild(option);
        });
    }

    results.hidden = false;
    search.setAttribute('aria-expanded', 'true');
}

function selectApplicant(application) {
    const search = document.getElementById('applicantSearch');
    const results = document.getElementById('applicantSearchResults');

    document.getElementById('topScheduleAppId').value = application.id;
    document.getElementById('selectedApplicantName').textContent = application.full_name;
    document.getElementById('selectedApplicantDetails').textContent = `${application.email || 'No email'} · Applying for ${application.pet || 'Unknown pet'}`;
    document.getElementById('selectedApplicant').hidden = false;
    document.getElementById('applicantSearchError').hidden = true;
    document.getElementById('topScheduleSubmit').disabled = false;

    search.value = application.full_name;
    search.readOnly = true;
    search.setAttribute('aria-expanded', 'false');
    results.hidden = true;
}

function clearSelectedApplicant(focusSearch = true) {
    const search = document.getElementById('applicantSearch');
    const results = document.getElementById('applicantSearchResults');
    if (!search || !results) return;

    document.getElementById('topScheduleAppId').value = '';
    document.getElementById('selectedApplicant').hidden = true;
    document.getElementById('applicantSearchError').hidden = true;
    document.getElementById('topScheduleSubmit').disabled = true;
    search.value = '';
    search.readOnly = false;
    search.setAttribute('aria-expanded', 'false');
    results.hidden = true;

    if (focusSearch) {
        search.focus();
        renderApplicantMatches('');
    }
}

function highlightApplicant(direction) {
    if (visibleApplicantMatches.length === 0) return;

    highlightedApplicantIndex = (highlightedApplicantIndex + direction + visibleApplicantMatches.length)
        % visibleApplicantMatches.length;

    document.querySelectorAll('#applicantSearchResults .applicant-search-option').forEach((option, index) => {
        const selected = index === highlightedApplicantIndex;
        option.setAttribute('aria-selected', selected ? 'true' : 'false');
        option.classList.toggle('bg-neutral-light', selected);
        if (selected) option.scrollIntoView({ block: 'nearest' });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('applicantSearch');
    const results = document.getElementById('applicantSearchResults');
    const form = document.getElementById('topScheduleForm');

    search?.addEventListener('input', () => renderApplicantMatches(search.value));
    search?.addEventListener('focus', () => renderApplicantMatches(search.value));
    search?.addEventListener('blur', () => {
        window.setTimeout(() => {
            results.hidden = true;
            search.setAttribute('aria-expanded', 'false');
        }, 150);
    });
    search?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlightApplicant(event.key === 'ArrowDown' ? 1 : -1);
        } else if (event.key === 'Enter' && highlightedApplicantIndex >= 0) {
            event.preventDefault();
            selectApplicant(visibleApplicantMatches[highlightedApplicantIndex]);
        } else if (event.key === 'Escape') {
            results.hidden = true;
            search.setAttribute('aria-expanded', 'false');
        }
    });

    form?.addEventListener('submit', (event) => {
        if (!document.getElementById('topScheduleAppId').value) {
            event.preventDefault();
            document.getElementById('applicantSearchError').hidden = false;
            search.focus();
            renderApplicantMatches(search.value);
        }
    });
});

function openAddNoteModal(id) {
    const a = findApp(id);
    if (!a) return;
    document.getElementById('noteSubheading').textContent = `Applicant: ${a.full_name} · Pet: ${a.pet ?? '—'} · Submitted: ${a.submitted}`;
    document.getElementById('noteForm').action = a.notes_action;
    openModal('addNoteModal');
}

async function openAdoptionHistoryModal(url) {
    const content = document.getElementById('adoptionHistoryContent');
    try {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('failed');
        content.innerHTML = await res.text();
        openModal('adoptionHistoryModal');
    } catch (err) {
        window.PAIRfectAdmin?.showToast('Could not load adoption history.', 'error');
    }
}

function openCompatibilityModal(a) {
    document.getElementById('compatSubheading').textContent = `${a.full_name} · ${a.pet ?? ''}`;
    document.getElementById('compatOverall').textContent = `${a.compatibility.overall}/100`;
    const overall = a.compatibility.overall;
    document.getElementById('compatLabel').textContent = overall >= 80 ? 'High Match' : overall >= 60 ? 'Good Match' : overall >= 40 ? 'Fair Match' : 'Low Match';

    const rowsHost = document.getElementById('compatRows');
    rowsHost.innerHTML = '';
    (a.compatibility.rows || []).forEach((row) => {
        const color = row.percent >= 60 ? '#295F51' : row.percent >= 35 ? '#614E34' : '#773E47';
        rowsHost.insertAdjacentHTML('beforeend', `
            <div class="compat-row-grid">
                <span class="compat-label">${row.label}</span>
                <div class="compat-bar-bg"><div class="compat-bar-fill-anim" style="width:${row.percent}%;background:${color}"></div></div>
                <span class="compat-pct">${row.percent}%</span>
            </div>
        `);
    });

    openModal('compatibilityResultModal');
}
