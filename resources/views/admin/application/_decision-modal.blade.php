<div class="custom-modal-backdrop" id="makeDecisionModal">
    <div class="custom-modal decision-modal">
        <div class="custom-modal-header !pb-2">
            <div>
                <h2 id="decisionModalTitle">Make Decision</h2>
                <small id="decisionSubheading">Applicant: · Pet: · Submitted:</small>
            </div>
        </div>
        <form id="makeDecisionForm" method="POST">
            @csrf
            <input type="hidden" name="decision" id="makeDecisionValue">
            <div class="custom-modal-body !pt-2 !pb-3">
                <div class="decision-info-banner" id="decisionInfoBanner">
                    This applicant's profile has been reviewed. Please select a final decision below.
                </div>
                <div class="form-group !mt-3">
                    <label class="form-label !mb-1.5" id="decisionRemarksLabel" for="decisionRemarksInput">Remarks (Optional)</label>
                    <textarea id="decisionRemarksInput" name="decision_remarks" class="form-control resize-none" rows="3" placeholder=""></textarea>
                </div>
            </div>
            <div class="custom-modal-footer flex items-center justify-end gap-3 p-5 pt-2">
                <button type="button" class="btn btn-decision-close" onclick="closeModal('makeDecisionModal')">Cancel</button>
                <button type="button" class="btn btn-decision-reject" id="decisionModalRejectBtn" onclick="handleDecisionSubmit('Rejected')"><i class="fa-solid fa-circle-xmark mr-1.5"></i>Reject</button>
                <button type="button" class="btn btn-decision-approve" id="decisionModalApproveBtn" onclick="handleDecisionSubmit('Approved')"><i class="fa-solid fa-circle-check mr-1.5"></i>Approve</button>
            </div>
        </form>
    </div>
</div>
