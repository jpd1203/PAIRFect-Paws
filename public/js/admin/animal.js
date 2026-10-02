/**
 * Animal Records Management
 * - Populates View/Edit modal from inline JSON payload
 * - Multi-step interactive tab switching (mobile-friendly)
 * - Live photo preview & drag-drop handling
 * - Automatic tab-switch on validation errors
 */

const ANIMALS = JSON.parse(document.getElementById('animalData')?.textContent || '[]');

/**
 * Switch active tab in Add or View/Edit modal
 * @param {'add'|'view'} modalType
 * @param {number} tabIndex (1, 2, or 3)
 */
function switchAnimalModalTab(modalType, tabIndex) {
    for (let i = 1; i <= 3; i++) {
        const btn = document.getElementById(`${modalType}TabBtn-${i}`);
        const pane = document.getElementById(`${modalType}TabPane-${i}`);

        if (i === tabIndex) {
            btn?.classList.add('active');
            pane?.classList.remove('hidden');
        } else {
            btn?.classList.remove('active');
            pane?.classList.add('hidden');
        }
    }

    // Scroll modal body smoothly back to top when switching steps on small devices
    const modal = document.getElementById(`${modalType}AnimalModal`);
    const body = modal?.querySelector('.custom-modal-body');
    if (body) {
        body.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

/**
 * Handle image file selection & show live preview
 * @param {HTMLInputElement} input
 * @param {string} previewImgId
 * @param {string} filenameId
 */
function handlePhotoPreview(input, previewImgId, filenameId) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const preview = document.getElementById(previewImgId);
    const filenameEl = document.getElementById(filenameId);
    const dropzone = input.closest('.photo-upload-zone');
    const previewContainer = dropzone?.querySelector('[id$="PreviewContainer"]');
    const placeholder = dropzone?.querySelector('[id$="Placeholder"]');

    const reader = new FileReader();
    reader.onload = function (e) {
        if (preview) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        if (previewContainer) {
            previewContainer.classList.remove('hidden');
        }
        if (placeholder) {
            placeholder.classList.add('hidden');
        }
        if (filenameEl) {
            filenameEl.textContent = file.name;
        }
    };
    reader.readAsDataURL(file);
}

/**
 * Open and populate the View/Edit Animal Modal
 * @param {number} id
 */
function openViewAnimalModal(id) {
    const a = ANIMALS.find((x) => x.id === id);
    if (!a) return;

    // Reset view modal to Tab 1
    switchAnimalModalTab('view', 1);

    // Populate Tab 1: Basic Info
    const titleEl = document.getElementById('viewAnimalTitle');
    if (titleEl) titleEl.textContent = a.name;

    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val ?? '';
    };

    setVal('vName', a.name);
    setVal('vSpecies', a.species);
    setVal('vBreed', a.breed);
    setVal('vAgeYears', a.age_years);
    setVal('vAgeMonths', a.age_months);
    setVal('vSex', a.sex);
    setVal('vIntake', a.intake);

    // Populate Tab 2: Health & Status
    setVal('vHealth', a.health);
    setVal('vVacc', a.vacc);
    setVal('vStatus', a.status);
    setVal('vMedical', a.medical_needs);
    setVal('vSize', a.physical_size);
    setVal('vLifeStage', a.life_stage);
    setVal('vAggression', a.has_aggression_history === null ? '' : Number(a.has_aggression_history));
    setVal('vVocalization', a.high_vocalization === null ? '' : Number(a.high_vocalization));

    // Populate Tab 3: Story & Media
    setVal('vDescription', a.description);
    setVal('vNotes', a.notes);
    setVal('vVersion', a.version);

    // Photo Preview Setup
    const photoPreview = document.getElementById('vPhotoPreview');
    const previewContainer = document.getElementById('viewPhotoPreviewContainer');
    const placeholder = document.getElementById('viewPhotoPlaceholder');
    const filenameEl = document.getElementById('viewPhotoFilename');
    const photoInput = document.getElementById('viewPhotoInput');
    if (photoInput) photoInput.value = ''; // Reset file input

    if (a.image_url && !a.image_url.includes('rcpp-logo.png')) {
        if (photoPreview) {
            photoPreview.src = a.image_url;
            photoPreview.style.display = 'block';
        }
        previewContainer?.classList.remove('hidden');
        placeholder?.classList.add('hidden');
        if (filenameEl) filenameEl.textContent = 'Current animal photo';
    } else {
        if (photoPreview) {
            photoPreview.src = '';
            photoPreview.style.display = 'none';
        }
        previewContainer?.classList.add('hidden');
        placeholder?.classList.remove('hidden');
        if (filenameEl) filenameEl.textContent = '';
    }

    // Assessment Info
    const badge = document.getElementById('vAssessBadge');
    if (badge) {
        badge.textContent = a.assessment_status === 'complete' ? 'Complete' : 'Pending';
        badge.className = `badge ${a.assessment_status === 'complete' ? 'badge-completed' : 'badge-pending'}`;
    }
    const assessCount = document.getElementById('vAssessCount');
    if (assessCount) {
        assessCount.textContent = `${a.assessment_count}/3 assessments completed`;
    }
    const assessBtn = document.getElementById('vAssessBtn');
    const assessDone = document.getElementById('vAssessDone');
    const assessmentUnavailable = a.assessment_status === 'complete' || a.assessed_by_current_user;
    if (assessBtn) {
        assessBtn.href = a.assess_url;
        assessBtn.classList.toggle('hidden', assessmentUnavailable);
    }
    if (assessDone) {
        assessDone.textContent = a.assessment_status === 'complete' ? 'Complete' : 'Your assessment complete';
        assessDone.classList.toggle('hidden', !assessmentUnavailable);
    }

    // Form Action URL
    const form = document.getElementById('viewAnimalForm');
    if (form) form.action = a.update_url;

    openModal('viewAnimalModal');
}

// Initialize interactive listeners on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Switch to relevant tab if a hidden required input fails validation
    document.addEventListener(
        'invalid',
        (e) => {
            const form = e.target.closest('#addAnimalForm, #viewAnimalForm');
            if (!form) return;
            const modalType = form.id === 'addAnimalForm' ? 'add' : 'view';

            for (let i = 1; i <= 3; i++) {
                const pane = document.getElementById(`${modalType}TabPane-${i}`);
                if (pane && pane.contains(e.target)) {
                    switchAnimalModalTab(modalType, i);
                    break;
                }
            }
        },
        true
    );

    // Setup drag-and-drop on upload zones
    setupDropzone('addPhotoDropzone', 'addPhotoInput', 'addPhotoPreview', 'addPhotoFilename');
    setupDropzone('viewPhotoDropzone', 'viewPhotoInput', 'vPhotoPreview', 'viewPhotoFilename');

    // Reset Add Animal modal to Tab 1 on trigger
    const addBtn = document.querySelector('button[onclick*="addAnimalModal"]');
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            switchAnimalModalTab('add', 1);
        });
    }
});

/**
 * Configure drag and drop for photo zones
 */
function setupDropzone(dropzoneId, inputId, previewId, filenameId) {
    const dropzone = document.getElementById(dropzoneId);
    const input = document.getElementById(inputId);
    if (!dropzone || !input) return;

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('border-maroon-500', 'bg-maroon-50');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('border-maroon-500', 'bg-maroon-50');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            handlePhotoPreview(input, previewId, filenameId);
        }
    });
}

// Expose functions globally
window.switchAnimalModalTab = switchAnimalModalTab;
window.handlePhotoPreview = handlePhotoPreview;
window.openViewAnimalModal = openViewAnimalModal;
