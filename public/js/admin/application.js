/** Applications — drives Review / Schedule Interview / Interview Notes / History / Compatibility modals. */
const APPLICATIONS = JSON.parse(document.getElementById('applicationData')?.textContent || '[]');

function syncInterviewFields(form) {
    const mode = form.querySelector('[name="interview_mode"]')?.value;
    form.querySelectorAll('[data-interview-field]').forEach((field) => {
        const active = field.dataset.interviewField === mode;
        field.hidden = !active;
        field.style.display = active ? '' : 'none';
        const input = field.querySelector('input, textarea');
        if (!input) return;
        input.disabled = !active;
        input.required = active;
        if (!active) input.value = '';
    });
}

function setInterviewFields(form, application) {
    form.querySelector('[name="interview_mode"]').value = application?.interview_mode ?? '';
    form.querySelector('[name="interview_meeting_url"]').value = application?.interview_meeting_url ?? '';
    form.querySelector('[name="interview_location"]').value = application?.interview_location ?? '';
    syncInterviewFields(form);
}

function safeMeetUrl(value) {
    try {
        const url = new URL(value);
        return url.protocol === 'https:' && url.hostname.toLowerCase() === 'meet.google.com'
            && !url.username && !url.password && !url.port && url.pathname !== '/'
            ? url.href : null;
    } catch {
        return null;
    }
}

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

    const hasMotivation = Boolean(a.motivation_statement && a.motivation_statement.trim());
    const motivationRow = document.getElementById('rMotivationRow');
    const motivationText = document.getElementById('rMotivation');
    if (motivationRow) {
        motivationRow.style.display = hasMotivation ? 'flex' : 'none';
    }
    if (motivationText && hasMotivation) {
        motivationText.textContent = a.motivation_statement;
    }

    const docRow = document.getElementById('rDocumentRow');
    if (docRow) {
        docRow.style.display = a.document_url ? 'flex' : 'none';
    }
    document.getElementById('rDocumentLink').href = a.document_url || '#';

    const docStatusDisplay = {
        'NeedsResubmission': 'Needs Resubmission',
        'ManualReview': 'Manual Review',
        'LegacyReview': 'Legacy Review',
        'Verified': 'Verified',
        'Pending': 'Pending',
    }[a.document_verification_status] || a.document_verification_status || '—';
    document.getElementById('rDocumentStatus').textContent = docStatusDisplay;
    document.getElementById('rVerificationLink').href = a.verification_url || '#';
    const verifRow = document.getElementById('rVerificationRow');
    if (verifRow) {
        verifRow.style.display = (a.verification_url || a.document_verification_status) ? 'flex' : 'none';
    }
    document.getElementById('rIdentityLink').href = a.identity_url;
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

    // Compatibility: Only show row and banner if a valid numeric score is present
    const hasValidCompat = Boolean(
        a.has_compatibility &&
        a.compatibility &&
        a.compatibility.overall !== null &&
        a.compatibility.overall !== undefined &&
        !isNaN(Number(a.compatibility.overall))
    );

    const compatRow = document.getElementById('rCompatRow');
    if (compatRow) {
        compatRow.style.display = hasValidCompat ? 'flex' : 'none';
    }
    const compatBtn = document.getElementById('rCompatBtn');
    if (compatBtn) {
        compatBtn.onclick = () => openCompatibilityModal(a);
    }

    const compatBanner = document.getElementById('rCompatBanner');
    if (compatBanner) {
        if (hasValidCompat) {
            const overall = Number(a.compatibility.overall);
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

            compatBanner.style.color = overall >= 80 ? '#E5F0EC' : overall >= 60 ? '#E8EDF5' : overall >= 40 ? '#FFFFFF' : '#773E47';
            compatBanner.textContent = `${label} – ${overall}/100`;
        } else {
            compatBanner.style.display = 'none';
            compatBanner.textContent = '';
            compatBanner.style.background = 'none';
        }
    }

    // Interview section: only show container and content if interview date is present
    const interviewContainer = document.getElementById('rInterviewContainer');
    const interviewSection = document.getElementById('rInterviewSection');
    const hasInterview = Boolean(a.interview_date);
    if (interviewContainer) {
        interviewContainer.style.display = hasInterview ? 'block' : 'none';
    }
    if (interviewSection) {
        interviewSection.style.display = hasInterview ? 'block' : 'none';
    }
    if (hasInterview) {
        document.getElementById('rInterviewDate').textContent = `${a.interview_date} at ${a.interview_time ?? ''}`;
        document.getElementById('rConductedBy').textContent = a.conducted_by ?? '—';
        document.getElementById('rInterviewMode').textContent = a.interview_mode === 'Online' ? 'Online'
            : (a.interview_mode === 'InPerson' ? 'In-person' : 'Not specified');
        const meetUrl = a.interview_mode === 'Online' ? safeMeetUrl(a.interview_meeting_url) : null;
        document.getElementById('rInterviewMeetingRow').style.display = meetUrl ? 'flex' : 'none';
        document.getElementById('rInterviewMeetingLink').href = meetUrl ?? '#';
        const location = a.interview_mode === 'InPerson' ? a.interview_location : null;
        document.getElementById('rInterviewLocationRow').style.display = location ? 'flex' : 'none';
        document.getElementById('rInterviewLocation').textContent = location ?? '';
        const hasNotes = Boolean(a.interview_notes && a.interview_notes.trim());
        const notesRow = document.getElementById('rInterviewNotesRow');
        const notesText = document.getElementById('rInterviewNotesText');
        if (notesRow) notesRow.style.display = hasNotes ? 'flex' : 'none';
        if (notesText) {
            notesText.style.display = hasNotes ? 'block' : 'none';
            notesText.textContent = a.interview_notes ?? '';
        }
    }

    // Decision remarks: only show container and text if remarks exist
    const decisionRemarksContainer = document.getElementById('rDecisionRemarksContainer');
    const decisionRemarksSection = document.getElementById('rDecisionRemarksSection');
    const hasRemarks = Boolean(a.decision_remarks && a.decision_remarks.trim());
    if (decisionRemarksContainer) {
        decisionRemarksContainer.style.display = hasRemarks ? 'block' : 'none';
    }
    if (decisionRemarksSection) {
        decisionRemarksSection.style.display = hasRemarks ? 'block' : 'none';
    }
    if (hasRemarks) {
        document.getElementById('rDecisionRemarksText').textContent = a.decision_remarks;
    }

    // Reschedule section: only show container if reschedule request exists
    const rescheduleContainer = document.getElementById('rRescheduleContainer');
    const rescheduleSection = document.getElementById('rRescheduleSection');
    const hasRescheduleRequest = a.reschedule_status === 'pending' && a.status === 'scheduled' && a.is_primary;
    if (rescheduleContainer) {
        rescheduleContainer.style.display = hasRescheduleRequest ? 'block' : 'none';
    }
    if (rescheduleSection) {
        rescheduleSection.style.display = hasRescheduleRequest ? 'block' : 'none';
        rescheduleSection.hidden = !hasRescheduleRequest;
    }
    if (hasRescheduleRequest) {
        document.getElementById('rRescheduleCurrent').textContent = `${a.interview_date} at ${a.interview_time}`;
        document.getElementById('rRescheduleReason').textContent = a.reschedule_reason || 'No reason provided';
        document.getElementById('rRescheduleAppId').value = a.id;
        document.getElementById('rRescheduleDeclineForm').action = a.reschedule_decline_action;
        const choices = document.getElementById('rRescheduleOptions');
        choices.replaceChildren();

        (a.reschedule_options || []).forEach((option, index) => {
            const label = document.createElement('label');

            label.className = 'flex cursor-pointer items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm transition';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'preferred_reschedule_option';
            radio.value = index;
            radio.checked = index === 0;
            radio.className = 'hidden';

            const text = document.createElement('span');
            text.className = 'w-full';
            const formattedDate = new Date(option.date + 'T00:00:00')
            .toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
            const [hours, minutes] = option.time.split(':');
            const timeDate = new Date();
            timeDate.setHours(Number(hours), Number(minutes));

            const formattedTime = timeDate.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            text.textContent =
                `Option ${index + 1}: ${formattedDate} at ${formattedTime}`;

            const updateSelectedStyle = () => {
                document
                    .querySelectorAll('#rRescheduleOptions label')
                    .forEach((item) => {
                        item.classList.remove(
                            'bg-primary-muted',
                            'border-[#A61D24]',
                            'text-[#A61D24]'
                        );

                        item.classList.add(
                            'bg-white',
                            'border-gray-200',
                            'text-gray-700'
                        );
                    });

                if (radio.checked) {
                    label.classList.remove(
                        'bg-white',
                        'border-gray-200',
                        'text-gray-700'
                    );

                    label.classList.add(
                        'bg-primary-muted',
                        'border-[#A61D24]',
                        'text-[#A61D24]'
                    );
                }
            };

            radio.addEventListener('change', updateSelectedStyle);

            label.append(radio, text);
            choices.append(label);

            // Apply selected style to first/default option
            if (radio.checked) {
                updateSelectedStyle();
            }
        });
                const interviewer = document.getElementById('rRescheduleStaff');
        interviewer.value = '';
        const matchingStaff = Array.from(interviewer.options).find((option) => option.dataset.staffName === a.conducted_by);
        if (matchingStaff) interviewer.value = matchingStaff.value;
        setInterviewFields(document.getElementById('rRescheduleAcceptForm'), a);
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

    // Safety fallback: ensure NO empty .review-container renders with a background
    document.querySelectorAll('#applicationReviewModal .review-container').forEach((container) => {
        if (container.style.display === 'none') return;
        const visibleElements = Array.from(container.querySelectorAll('.review-section, .review-row, p, div')).filter((el) => {
            return el.style.display !== 'none' && !el.hidden && (el.innerText ? el.innerText.trim().length > 0 : false);
        });
        if (visibleElements.length === 0 || !container.innerText.trim()) {
            container.style.display = 'none';
        }
    });

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
    setInterviewFields(modal.querySelector('form[data-interview-details]'), a);
    openModal('scheduleInterviewModal');
}

function openTopScheduleModal() {
    clearSelectedApplicant(false);
    setInterviewFields(document.getElementById('topScheduleForm'), null);
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
    const form = document.getElementById('topScheduleForm');

    document.getElementById('topScheduleAppId').value = application.id;
    form.querySelector('[name="interview_date"]').value = application.interview_date_input ?? '';
    form.querySelector('[name="interview_time"]').value = application.interview_time_input ?? '';
    setInterviewFields(form, application);
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
    document.querySelectorAll('form[data-interview-details]').forEach((form) => {
        form.querySelector('[name="interview_mode"]')?.addEventListener('change', () => syncInterviewFields(form));
        syncInterviewFields(form);
    });
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
    if (!a.compatibility || a.compatibility.overall === null || a.compatibility.overall === undefined) {
        return;
    }
    const overall = Number(a.compatibility.overall);
    document.getElementById('compatSubheading').textContent = `${a.full_name} · ${a.pet ?? ''}`;
    document.getElementById('compatOverall').textContent = `${overall}/100`;
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

/** Interactive application live-filter controller */
function initInteractiveApplicationFilters() {
    const scope = document.getElementById('applicationTableBody');
    if (!scope) return;

    const form = document.getElementById('applicationFilterForm');
    const searchInput = document.getElementById('appSearchInput');
    const fromInput = document.getElementById('appFromDate');
    const toInput = document.getElementById('appToDate');
    const statusSelect = document.getElementById('appStatusSelect');
    const clearBtn = document.getElementById('clearFiltersBtn');
    const filterBar = document.querySelector('[data-filter-bar][data-filter-scope="applicationTableBody"]');
    const countDisplay = document.getElementById('visibleAppCount');
    const noResultsRow = document.getElementById('noFilterResultsRow');

    function runFilters() {
        const term = (searchInput?.value || '').trim().toLowerCase();
        const fromDate = fromInput?.value || '';
        const toDate = toInput?.value || '';
        const selectedStatus = (statusSelect?.value || '').trim();
        const activeTabBtn = filterBar?.querySelector('[data-filter-btn].active');
        const tabStatus = activeTabBtn?.dataset.filterBtn || 'all';

        const rows = scope.querySelectorAll('tr[data-filter-row]');
        let visibleCount = 0;

        rows.forEach((row) => {
            const text = (row.dataset.searchText || row.textContent).toLowerCase();
            const rowStatusRaw = row.dataset.statusRaw || '';
            const rowStatusSlug = (row.dataset.status || '').toLowerCase();
            const rowDate = row.dataset.date || '';

            // Search term check
            const matchesSearch = !term || text.includes(term);

            // Status check
            let matchesStatus = true;
            if (selectedStatus) {
                matchesStatus = rowStatusRaw.toLowerCase() === selectedStatus.toLowerCase()
                    || rowStatusSlug.includes(selectedStatus.toLowerCase())
                    || (selectedStatus === 'InterviewScheduled' && rowStatusSlug.includes('scheduled'));
            } else if (tabStatus !== 'all') {
                matchesStatus = rowStatusSlug.split(' ').includes(tabStatus);
            }

            // Date range check
            let matchesDate = true;
            if (fromDate && rowDate) {
                matchesDate = matchesDate && (rowDate >= fromDate);
            }
            if (toDate && rowDate) {
                matchesDate = matchesDate && (rowDate <= toDate);
            }

            const isVisible = matchesSearch && matchesStatus && matchesDate;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        if (countDisplay) {
            countDisplay.textContent = visibleCount;
        }

        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    }

    // Attach real-time input and change listeners
    searchInput?.addEventListener('input', runFilters);
    fromInput?.addEventListener('input', runFilters);
    fromInput?.addEventListener('change', runFilters);
    toInput?.addEventListener('input', runFilters);
    toInput?.addEventListener('change', runFilters);

    statusSelect?.addEventListener('change', () => {
        const val = statusSelect.value;
        if (filterBar) {
            filterBar.querySelectorAll('[data-filter-btn]').forEach((b) => b.classList.remove('active'));
            if (!val) {
                filterBar.querySelector('[data-filter-btn="all"]')?.classList.add('active');
            } else {
                const slug = val === 'InterviewScheduled' ? 'scheduled' : val.toLowerCase().replace(/[\s_]/g, '');
                const matchingTab = filterBar.querySelector(`[data-filter-btn="${slug}"]`);
                if (matchingTab) {
                    matchingTab.classList.add('active');
                }
            }
        }
        runFilters();
    });

    // When clicking a tab on filter-bar, sync the Status dropdown
    if (filterBar) {
        filterBar.querySelectorAll('[data-filter-btn]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                filterBar.querySelectorAll('[data-filter-btn]').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');

                const btnFilter = btn.dataset.filterBtn;
                if (statusSelect) {
                    if (btnFilter === 'all') {
                        statusSelect.value = '';
                    } else {
                        const opt = [...statusSelect.options].find((o) => {
                            const s = o.value === 'InterviewScheduled' ? 'scheduled' : o.value.toLowerCase().replace(/[\s_]/g, '');
                            return s === btnFilter;
                        });
                        statusSelect.value = opt ? opt.value : '';
                    }
                }
                runFilters();
            });
        });
    }

    // Reset / Clear function
    window.resetApplicationFilters = function () {
        if (searchInput) searchInput.value = '';
        if (fromInput) fromInput.value = '';
        if (toInput) toInput.value = '';
        if (statusSelect) statusSelect.value = '';
        if (filterBar) {
            filterBar.querySelectorAll('[data-filter-btn]').forEach((b) => b.classList.remove('active'));
            filterBar.querySelector('[data-filter-btn="all"]')?.classList.add('active');
        }
        if (window.location.search) {
            window.history.replaceState({}, '', window.location.pathname);
        }
        runFilters();
    };

    clearBtn?.addEventListener('click', window.resetApplicationFilters);

    // Form submit:
    form?.addEventListener('submit', (e) => {
        // If clicking Export, let it submit naturally to download CSV
        if (e.submitter && e.submitter.getAttribute('formaction')) {
            return;
        }
        e.preventDefault();
        runFilters();

        const params = new URLSearchParams();
        if (fromInput?.value) params.set('from', fromInput.value);
        if (toInput?.value) params.set('to', toInput.value);
        if (statusSelect?.value) params.set('status', statusSelect.value);
        if (searchInput?.value) params.set('search', searchInput.value);

        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newUrl);
    });

    // Check if initial query parameters were in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('from') && fromInput) fromInput.value = urlParams.get('from');
    if (urlParams.get('to') && toInput) toInput.value = urlParams.get('to');
    if (urlParams.get('status') && statusSelect) {
        statusSelect.value = urlParams.get('status');
        const slug = statusSelect.value === 'InterviewScheduled' ? 'scheduled' : statusSelect.value.toLowerCase().replace(/[\s_]/g, '');
        if (filterBar) {
            filterBar.querySelectorAll('[data-filter-btn]').forEach((b) => b.classList.remove('active'));
            filterBar.querySelector(`[data-filter-btn="${slug}"]`)?.classList.add('active');
        }
    }
    if (urlParams.get('search') && searchInput) searchInput.value = urlParams.get('search');

    runFilters();
    initFilterBarScrollArrows();
}

/** Overflow scroll arrow controls for filter bar */
function initFilterBarScrollArrows() {
    const containers = document.querySelectorAll('.filter-bar-container');
    containers.forEach((container) => {
        const bar = container.querySelector('.filter-bar');
        const leftBtn = container.querySelector('.tab-scroll-left');
        const rightBtn = container.querySelector('.tab-scroll-right');
        if (!bar || !leftBtn || !rightBtn) return;

        function updateArrows() {
            // Show only when container is smaller and tabs overflow
            const hasOverflow = bar.scrollWidth > bar.clientWidth + 2;
            if (!hasOverflow) {
                leftBtn.style.display = 'none';
                rightBtn.style.display = 'none';
                return;
            }

            const atStart = bar.scrollLeft <= 5;
            leftBtn.style.display = atStart ? 'none' : 'flex';

            const atEnd = bar.scrollLeft >= bar.scrollWidth - bar.clientWidth - 5;
            rightBtn.style.display = atEnd ? 'none' : 'flex';
        }

        leftBtn.addEventListener('click', (e) => {
            e.preventDefault();
            bar.scrollBy({ left: -180, behavior: 'smooth' });
            setTimeout(updateArrows, 250);
        });

        rightBtn.addEventListener('click', (e) => {
            e.preventDefault();
            bar.scrollBy({ left: 180, behavior: 'smooth' });
            setTimeout(updateArrows, 250);
        });

        bar.addEventListener('scroll', updateArrows, { passive: true });
        window.addEventListener('resize', updateArrows);

        if (window.ResizeObserver) {
            new ResizeObserver(updateArrows).observe(container);
            new ResizeObserver(updateArrows).observe(bar);
        }

        updateArrows();
        setTimeout(updateArrows, 150);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initInteractiveApplicationFilters();
        initFilterBarScrollArrows();
    });
} else {
    initInteractiveApplicationFilters();
    initFilterBarScrollArrows();
}
