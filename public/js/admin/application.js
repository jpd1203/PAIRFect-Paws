/** Applications — drives Review / Schedule Interview / Interview Notes / History / Compatibility modals. */
const APPLICATIONS = JSON.parse(document.getElementById('applicationData')?.textContent || '[]');

function getSchedulableApplications() {
    const raw = document.getElementById('applicationData')?.textContent;
    const apps = raw ? JSON.parse(raw) : (Array.isArray(APPLICATIONS) ? APPLICATIONS : []);
    return apps.filter((application) => {
        if (application.status === 'primarycandidate' && application.is_primary) return true;
        if (application.status === 'scheduled' && application.is_primary) return true;

        const hasActivePrimary = apps.some((other) =>
            other.pet_id === application.pet_id && other.id !== application.id && other.is_primary
        );

        return ['pending', 'underreview'].includes(application.status)
            && !application.is_primary
            && !hasActivePrimary
            && application.queue_position === 1
            && ['Verified', 'LegacyReview'].includes(application.document_verification_status);
    });
}

const SCHEDULABLE_APPLICATIONS = getSchedulableApplications();

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
    document.getElementById('rMotivation').textContent = a.motivation_statement || 'No statement provided.';
    document.getElementById('rDocumentLink').href = a.document_url;
    const docStatusDisplay = {
        'NeedsResubmission': 'Needs Resubmission',
        'ManualReview': 'Manual Review',
        'LegacyReview': 'Legacy Review',
        'Verified': 'Verified',
        'Pending': 'Pending',
    }[a.document_verification_status] || a.document_verification_status || '—';
    document.getElementById('rDocumentStatus').textContent = docStatusDisplay;
    document.getElementById('rVerificationLink').href = a.verification_url;
    document.getElementById('rQueuePosition').textContent = a.queue_position
        ? `#${a.queue_position}`
        : ['approved', 'rejected', 'withdrawn', 'noshow', 'closed'].includes(a.status)
            ? 'Queue closed'
            : 'Not ranked';
    document.getElementById('rCandidateRole').textContent = a.is_primary ? 'Primary candidate' : (a.status === 'waitlisted' ? 'Waitlisted' : 'Not active');
    document.getElementById('rTimeoutRow').style.display = a.admin_review_flagged ? 'flex' : 'none';

    const history = a.history_summary || {};
    const historyBadge = document.getElementById('rHistoryStatus');
    historyBadge.textContent = history.review_label || 'No Recorded Concerns';
    historyBadge.className = `badge ${history.badge_class || 'badge-completed'}`;
    document.getElementById('rHistoryApplications').textContent = history.previous_applications ?? 0;
    document.getElementById('rHistoryPlacements').textContent = history.approved_placements ?? 0;
    document.getElementById('rHistoryFlags').textContent = history.flagged_welfare_reports ?? 0;
    document.getElementById('rHistoryCheckins').textContent = `${history.missed_checkins ?? 0} / ${history.late_checkins ?? 0}`;
    const historyReasons = document.getElementById('rHistoryReasons');
    historyReasons.replaceChildren();
    (history.explanations || []).forEach((reason) => {
        const item = document.createElement('li');
        item.textContent = reason;
        historyReasons.append(item);
    });
    document.getElementById('rFullHistoryBtn').onclick = () => openAdoptionHistoryModal(a.history_url);

    // document.getElementById('rHistoryRow').style.display = (a.has_history || a.history_url) ? 'flex' : 'none';
    // document.getElementById('rHistoryBtn').onclick = () => openAdoptionHistoryModal(a.history_url);

    document.getElementById('rCompatRow').style.display = a.has_compatibility ? 'flex' : 'none';
    document.getElementById('rCompatBtn').onclick = () => openCompatibilityModal(a);

    const compatBanner = document.getElementById('rCompatBanner');

    if (a.has_compatibility) {
        const overall = a.compatibility.overall;
        const label = overall >= 80 ? 'High Match' : overall >= 60 ? 'Good Match' : overall >= 40 ? 'Fair Match' : 'Low Match';
        compatBanner.style.display = 'block';
        const filledColor =
        overall >= 80 ? '#295F51' :
        overall >= 60 ? '#2A4877' :
        overall >= 40 ? '#614E34' :
        '#773E47';

    const lightColor =
        overall >= 80 ? '#E5F0EC' :
        overall >= 60 ? '#E8EDF5' :
        overall >= 40 ? '#FAEEDA' :
        '#FCEBEB';

    compatBanner.style.background =
        `linear-gradient(to right, ${filledColor} ${overall}%, ${lightColor} ${overall}%)`;

    compatBanner.style.color = overall >= 60 ? '#FFFFFF' : '#614E34';
            compatBanner.style.color = overall >= 80 ? '#E5F0EC' : overall >= 60 ? '#E8EDF5' : overall >= 40 ? '#FFFFFF' : '#773E47';
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

    const rescheduleSection = document.getElementById('rRescheduleSection');
    const hasRescheduleRequest = a.reschedule_status === 'pending' && a.status === 'scheduled' && a.is_primary;
    rescheduleSection.style.display = hasRescheduleRequest ? 'block' : 'none';
    if (hasRescheduleRequest) {
        document.getElementById('rRescheduleCurrent').textContent = `${a.interview_date} at ${a.interview_time}`;
        document.getElementById('rRescheduleReason').textContent = a.reschedule_reason || 'No reason provided';
        document.getElementById('rRescheduleAppId').value = a.id;
        document.getElementById('rRescheduleDeclineForm').action = a.reschedule_decline_action;
        const choices = document.getElementById('rRescheduleOptions');
        choices.replaceChildren();
        (a.reschedule_options || []).forEach((option, index) => {
            const label = document.createElement('label');
            label.className = 'block cursor-pointer py-1';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'preferred_reschedule_option';
            radio.value = index;
            radio.checked = index === 0;
            label.append(radio, document.createTextNode(` Option ${index + 1}: ${option.date} at ${option.time} (Asia/Manila)`));
            choices.append(label);
        });
        const interviewer = document.getElementById('rRescheduleStaff');
        interviewer.value = '';
        const matchingStaff = Array.from(interviewer.options).find((option) => option.dataset.staffName === a.conducted_by);
        if (matchingStaff) interviewer.value = matchingStaff.value;
        document.getElementById('rRescheduleAcceptBtn').onclick = () => {
            const selected = choices.querySelector('input:checked');
            if (!selected || !interviewer.value) {
                window.alert('Select a preferred time and an active interviewer before confirming.');
                return;
            }
            const option = a.reschedule_options[Number(selected.value)];
            document.getElementById('rRescheduleDate').value = option.date;
            document.getElementById('rRescheduleTime').value = option.time;
            document.getElementById('rRescheduleAcceptForm').requestSubmit();
        };
        document.getElementById('rRescheduleDifferentBtn').onclick = () => {
            closeModal('applicationReviewModal');
            openScheduleModal(a);
        };
    }

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
            if (!button) return;
            button.style.display = 'none';
            button.onclick = null;
        });

    scheduleBtn.textContent = 'Schedule Interview';
    scheduleBtn.className = 'btn btn-blue';
    const showDecisionButton = (button, decision) => {
        if (!button) return;
        button.innerHTML = decision === 'Approved'
            ? '<i class="fa-solid fa-circle-check"></i> Approve'
            : '<i class="fa-solid fa-circle-xmark"></i> Reject';
        button.style.display = 'inline-flex';
        button.onclick = () => openDecisionModal(a, decision);
    };
    if (a.status === 'pending') {
    // Pending application
    scheduleBtn.innerHTML = '<i class="fa-solid fa-calendar-check"></i> Schedule Interview';
    scheduleBtn.style.display = 'inline-flex';
    scheduleBtn.onclick = () => {
        closeModal('applicationReviewModal');
        openScheduleModal(a);
    };

    showDecisionButton(rejectBtn, 'Rejected');

    } else if (a.status === 'underreview' && a.is_primary) {
        // Under Review — primary candidate (post-interview): can approve or reject
        showDecisionButton(approveBtn, 'Approved');
        showDecisionButton(rejectBtn, 'Rejected');

    } else if (a.status === 'underreview' && !a.is_primary) {
        // Under Review — not primary: can schedule interview or reject
        scheduleBtn.innerHTML = '<i class="fa-solid fa-calendar-check"></i> Schedule Interview';
        scheduleBtn.style.display = 'inline-flex';
        scheduleBtn.onclick = () => {
            closeModal('applicationReviewModal');
            openScheduleModal(a);
        };

        showDecisionButton(rejectBtn, 'Rejected');

    } else if (a.status === 'primarycandidate') {
        // Promoted primary candidate: can schedule interview
        scheduleBtn.innerHTML = '<i class="fa-solid fa-calendar-check"></i> Schedule Interview';
        scheduleBtn.style.display = 'inline-flex';
        scheduleBtn.onclick = () => {
            closeModal('applicationReviewModal');
            openScheduleModal(a);
        };

    } else if (a.status === 'documentflagged') {

        showDecisionButton(rejectBtn, 'Rejected');

    } else if (a.status === 'scheduled' && a.is_primary) {

        scheduleBtn.innerHTML = '<i class="fa-solid fa-calendar-check"></i> Reschedule Interview';
        scheduleBtn.style.display = 'inline-flex';
        scheduleBtn.onclick = () => {
            closeModal('applicationReviewModal');
            openScheduleModal(a);
        };
    }

    if (a.is_primary && (a.status === 'scheduled' || a.status === 'underreview')) {
        withdrawBtn.style.display = 'inline-flex';
        withdrawBtn.onclick = () => submitQueueOutcome(outcomeForm, 'Withdrawn');
    }
    if (a.is_primary && a.status === 'scheduled' && !hasRescheduleRequest) {
        noShowBtn.style.display = 'inline-flex';
        noShowBtn.onclick = () => submitQueueOutcome(outcomeForm, 'NoShow');
    }
    if (a.can_override && !a.is_primary && a.status === 'waitlisted') {
        overrideBtn.style.display = 'inline-flex';
        overrideBtn.onclick = () => submitOverride(overrideForm);
    }

    if (!SCHEDULABLE_APPLICATIONS.some((candidate) => candidate.id === a.id)) {
        scheduleBtn.style.display = 'none';
        scheduleBtn.onclick = null;
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

let currentDecisionApp = null;
let currentDecisionType = null;

function openDecisionModal(appOrId, decisionType = 'Approved') {
    const a = typeof appOrId === 'object' ? appOrId : findApp(appOrId);
    if (!a) return;

    currentDecisionApp = a;
    currentDecisionType = decisionType;

    const subheading = document.getElementById('decisionSubheading');
    if (subheading) {
        subheading.textContent = `Applicant: ${a.full_name} · Pet: ${a.pet ?? '—'} · Submitted: ${a.submitted}`;
    }

    const title = document.getElementById('decisionModalTitle');
    const banner = document.getElementById('decisionInfoBanner');
    const remarksLabel = document.getElementById('decisionRemarksLabel');
    const remarksInput = document.getElementById('decisionRemarksInput');
    const approveBtn = document.getElementById('decisionModalApproveBtn');
    const rejectBtn = document.getElementById('decisionModalRejectBtn');
    const form = document.getElementById('makeDecisionForm');

    if (form) {
        form.action = a.decide_action;
    }
    if (remarksInput) {
        remarksInput.value = '';
    }

    if (decisionType === 'Approved') {
        if (title) title.textContent = 'Approve Application';
        if (banner) banner.textContent = "This applicant's profile has been reviewed. Please confirm approval below.";
        if (remarksLabel) remarksLabel.textContent = 'Approval Remarks (Optional)';
        if (approveBtn) approveBtn.style.display = 'inline-block';
        if (rejectBtn) rejectBtn.style.display = 'none';
        setTimeout(() => approveBtn?.focus(), 100);
    } else {
        if (title) title.textContent = 'Reject Application';
        if (banner) banner.textContent = 'Please provide any remarks or reasons for rejecting this application below.';
        if (remarksLabel) remarksLabel.textContent = 'Reason for Rejection (Optional)';
        if (rejectBtn) rejectBtn.style.display = 'inline-block';
        if (approveBtn) approveBtn.style.display = 'none';
        setTimeout(() => rejectBtn?.focus(), 100);
    }

    openModal('makeDecisionModal');
}

function handleDecisionSubmit(decision) {
    if (!currentDecisionApp) return;

    const form = document.getElementById('makeDecisionForm');
    const valueInput = document.getElementById('makeDecisionValue');
    if (form && valueInput) {
        valueInput.value = decision || currentDecisionType || 'Approved';
        form.submit();
    }
}

window.openDecisionModal = openDecisionModal;
window.handleDecisionSubmit = handleDecisionSubmit;

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
        const search = document.getElementById('applicantSearch');
        const results = document.getElementById('applicantSearchResults');
        const clearBtn = document.getElementById('clearApplicantSearchBtn');
        if (search) {
            search.value = '';
            search.readOnly = false;
            search.focus();
        }
        if (results) {
            results.hidden = true;
            results.style.display = 'none';
            results.replaceChildren();
        }
        if (clearBtn) {
            clearBtn.style.display = 'none';
        }
    }, 0);
}

function updateClearButtonState() {
    const search = document.getElementById('applicantSearch');
    const clearBtn = document.getElementById('clearApplicantSearchBtn');
    if (!search || !clearBtn) return;
    if (search.readOnly || !search.value.trim()) {
        clearBtn.style.display = 'none';
    } else {
        clearBtn.style.display = 'flex';
    }
}

function renderApplicantMatches(query) {
    const results = document.getElementById('applicantSearchResults');
    const search = document.getElementById('applicantSearch');
    if (!results || !search || search.readOnly) return;

    updateClearButtonState();

    const term = (query || '').trim().toLowerCase();

    // The dropdown will ONLY appear if the first letter of the name is typed!
    if (!term) {
        visibleApplicantMatches = [];
        highlightedApplicantIndex = -1;
        results.replaceChildren();
        results.hidden = true;
        results.style.display = 'none';
        search.setAttribute('aria-expanded', 'false');
        return;
    }

    const schedulable = getSchedulableApplications();
    visibleApplicantMatches = schedulable
        .filter((application) => {
            const fullName = (application.full_name || '').toLowerCase();
            const pet = (application.pet || '').toLowerCase();
            const email = (application.email || '').toLowerCase();
            const nameParts = fullName.split(/\s+/);

            return fullName.includes(term)
                || nameParts.some((part) => part.startsWith(term))
                || pet.includes(term)
                || email.includes(term);
        })
        .slice(0, 8);

    highlightedApplicantIndex = -1;
    results.replaceChildren();

    if (visibleApplicantMatches.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'applicant-search-empty';
        empty.textContent = 'No matching schedulable applicants found.';
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

    results.removeAttribute('hidden');
    results.hidden = false;
    results.style.display = 'block';
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
    results.style.display = 'none';
    updateClearButtonState();
}

function clearSelectedApplicant(focusSearch = true) {
    const search = document.getElementById('applicantSearch');
    const results = document.getElementById('applicantSearchResults');
    const clearBtn = document.getElementById('clearApplicantSearchBtn');
    if (!search || !results) return;

    document.getElementById('topScheduleAppId').value = '';
    document.getElementById('selectedApplicant').hidden = true;
    document.getElementById('applicantSearchError').hidden = true;
    document.getElementById('topScheduleSubmit').disabled = true;
    search.value = '';
    search.readOnly = false;
    search.setAttribute('aria-expanded', 'false');
    results.hidden = true;
    results.style.display = 'none';
    results.replaceChildren();
    visibleApplicantMatches = [];
    highlightedApplicantIndex = -1;
    if (clearBtn) clearBtn.style.display = 'none';

    if (focusSearch) {
        search.focus();
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
    const clearBtn = document.getElementById('clearApplicantSearchBtn');

    clearBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        clearSelectedApplicant(true);
    });

    ['input', 'keyup', 'change'].forEach((evt) => {
        search?.addEventListener(evt, () => {
            updateClearButtonState();
            renderApplicantMatches(search.value);
        });
    });

    search?.addEventListener('focus', () => {
        updateClearButtonState();
        if (search.value.trim().length > 0) {
            renderApplicantMatches(search.value);
        } else {
            results.hidden = true;
            results.style.display = 'none';
            search.setAttribute('aria-expanded', 'false');
        }
    });

    search?.addEventListener('blur', () => {
        window.setTimeout(() => {
            results.hidden = true;
            results.style.display = 'none';
            search.setAttribute('aria-expanded', 'false');
        }, 200);
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
            results.style.display = 'none';
            search.setAttribute('aria-expanded', 'false');
        }
    });

    form?.addEventListener('submit', (event) => {
        if (!document.getElementById('topScheduleAppId').value) {
            event.preventDefault();
            document.getElementById('applicantSearchError').hidden = false;
            search.focus();
            if (search.value.trim().length > 0) {
                renderApplicantMatches(search.value);
            }
        }
    });

    // Auto-scroll to highlighted application if arrived from compatibility or email notification
    const urlParams = new URLSearchParams(window.location.search);
    let highlightId = urlParams.get('highlight') || urlParams.get('application_id') || urlParams.get('app');
    if (!highlightId && window.location.hash) {
        const match = window.location.hash.match(/\d+/);
        if (match) {
            highlightId = match[0];
        }
    }
    if (highlightId) {
        const row = document.getElementById('application-row-' + highlightId);
        if (row) {
            row.classList.add('highlighted-application-row');
            setTimeout(() => {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 200);
            setTimeout(() => {
                row.classList.remove('highlighted-application-row');
            }, 4000);
        }
    }
});

function openAddNoteModal(id) {
    const a = findApp(id);
    if (!a) return;
    document.getElementById('noteSubheading').textContent = `Applicant: ${a.full_name} · Pet: ${a.pet ?? '—'} · Submitted: ${a.submitted}`;
    document.getElementById('noteForm').action = a.notes_action;
    openModal('addNoteModal');
}

let adoptionHistoryTrigger = null;
let adoptionHistoryRequest = null;

function openAdoptionHistoryModal(url, trigger = null) {
    if (!url) {
        window.PAIRfectAdmin?.showToast?.('No adoption record history available for this applicant.', 'info');
        return;
    }
    adoptionHistoryTrigger = trigger || document.activeElement;
    openModal('adoptionHistoryModal');
    loadAdoptionHistory(url, true);
}

function switchProfileHistory(url) {
    loadAdoptionHistory(url, false);
}

async function loadAdoptionHistory(url, focusCloseButton = false) {
    const content = document.getElementById('adoptionHistoryContent');
    if (!content) return;

    adoptionHistoryRequest?.abort();
    adoptionHistoryRequest = new AbortController();

    content.innerHTML = `
        <div class="custom-modal-header">
            <h2 id="profileHistoryTitle">Adoption History</h2>
        </div>
        <div class="custom-modal-body py-10 text-center text-[#777]" role="status">
            <i class="fa-solid fa-spinner fa-spin text-2xl mb-3"></i>
            <p>Loading post-adoption monitoring history...</p>
        </div>
    `;

    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            signal: adoptionHistoryRequest.signal,
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('The adoption history request failed.');
        }

        content.innerHTML = await response.text();
        if (focusCloseButton) {
            content.querySelector('[data-history-close]')?.focus();
        } else {
            content.querySelector('[data-placement-switcher]')?.focus();
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        content.innerHTML = `
            <div class="custom-modal-header"><h2 id="profileHistoryTitle">Adoption History</h2></div>
            <div class="custom-modal-body">
                <div class="modal-note warning">The post-adoption monitoring history could not be loaded. Please try again.</div>
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeProfileHistory()">Close</button>
            </div>
        `;
        window.PAIRfectAdmin?.showToast?.('Could not load post-adoption monitoring history.', 'error');
    }
}

function closeProfileHistory() {
    adoptionHistoryRequest?.abort();
    adoptionHistoryRequest = null;
    closeModal('adoptionHistoryModal');
    adoptionHistoryTrigger?.focus();
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

document.addEventListener('keydown', (event) => {
    const modal = document.getElementById('adoptionHistoryModal');
    if (!modal?.classList.contains('active')) {
        return;
    }

    if (event.key === 'Escape') {
        event.stopPropagation();
        closeProfileHistory();
        return;
    }

    if (event.key === 'Tab') {
        const focusable = [...modal.querySelectorAll(
            'button:not([disabled]), select:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )].filter((element) => element.offsetParent !== null);

        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
});

document.getElementById('adoptionHistoryModal')?.addEventListener('click', (event) => {
    if (event.target.id === 'adoptionHistoryModal') {
        closeProfileHistory();
    }
});

window.openTopScheduleModal = openTopScheduleModal;
window.clearSelectedApplicant = clearSelectedApplicant;
window.renderApplicantMatches = renderApplicantMatches;
window.selectApplicant = selectApplicant;
window.openAdoptionHistoryModal = openAdoptionHistoryModal;
window.switchProfileHistory = switchProfileHistory;
window.closeProfileHistory = closeProfileHistory;
