<div class="custom-modal-backdrop" id="addNoteModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h2>Interview Notes</h2>
            <small id="noteSubheading">Applicant: · Pet: · Submitted:</small>
        </div>
        <form id="noteForm" method="POST">
            @csrf
            <div class="custom-modal-body">
                <div class="form-group">
                    <label class="form-label">Notes from the interview</label>
                    <textarea name="interview_notes" class="form-control remarks-textarea" rows="6" required placeholder="Summarize how the interview went, any concerns raised, and the applicant's readiness…"></textarea>
                </div>
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addNoteModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-note-sticky"></i>Save Notes</button>
            </div>
        </form>
    </div>
</div>
