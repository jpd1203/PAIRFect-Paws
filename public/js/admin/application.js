/** Applications — drives Review / Schedule Interview / Interview Notes / History / Compatibility modals. */
const APPLICATIONS = JSON.parse(document.getElementById('applicationData')?.textContent || '[]');

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
    badge.textContent = a.status === 'underreview' ? 'Under Review' : (a.status.charAt(0).toUpperCase() + a.status.slice(1));
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
    const approveBtn = document.getElementById('rApproveBtn');
    const rejectBtn = document.getElementById('rRejectBtn');
    const decisionForm = document.getElementById('rDecisionForm');
    decisionForm.action = a.decide_action;

    if (a.status === 'pending') {
        approveBtn.style.display = 'none';
        rejectBtn.style.display = 'inline-flex';
        rejectBtn.textContent = 'Schedule Interview';
        rejectBtn.className = 'btn btn-primary';
        rejectBtn.onclick = () => { closeModal('applicationReviewModal'); openScheduleModal(a); };
    } else if (a.status === 'underreview') {
        approveBtn.style.display = 'inline-flex';
        rejectBtn.style.display = 'inline-flex';
        rejectBtn.textContent = 'Reject';
        rejectBtn.className = 'btn btn-danger';
        approveBtn.onclick = () => submitDecision(decisionForm, 'approved');
        rejectBtn.onclick = () => submitDecision(decisionForm, 'rejected');
    } else {
        approveBtn.style.display = 'none';
        rejectBtn.style.display = 'none';
    }

    openModal('applicationReviewModal');
}

function submitDecision(form, decision) {
    document.getElementById('rDecisionInput').value = decision;
    document.getElementById('rDecisionRemarksInput').value = '';
    form.submit();
}

function openScheduleModalFromTop() {
    document.getElementById('scheduleSubheading').textContent = 'Select an application below to schedule an interview.';
    document.getElementById('scheduleAppId').value = '';
    openModal('scheduleInterviewModal');
}

function openScheduleModal(a) {
    document.getElementById('scheduleSubheading').textContent = `Applicant: ${a.full_name} · Pet: ${a.pet ?? '—'} · Submitted: ${a.submitted}`;
    document.getElementById('scheduleAppId').value = a.id;
    openModal('scheduleInterviewModal');
}

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
