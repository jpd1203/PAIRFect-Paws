<div class="custom-modal-backdrop" id="applicationReviewModal">
    <div class="custom-modal review-modal ">

        <div class="review-header">
            <div>
                <h4 id="reviewApplicantPet">Application Review</h4>
                <small id="reviewSubheading">Applicant: · Pet: · Submitted:</small>
            </div>
            <span id="reviewStatusBadge" class="badge"></span>
        </div>

        <div class="custom-modal-body custom-scrollbar">

            <div class="review-section">
                <h6>Personal Information</h6>
                <div class="review-row"><span>Full Name</span><span id="rFullName"></span></div>
                <div class="review-row"><span>Contact</span><span id="rContact"></span></div>
                <div class="review-row"><span>Email</span><span id="rEmail"></span></div>
                <div class="review-row"><span>Address</span><span id="rAddress"></span></div>
            </div>

            <div class="review-section">
                <h6>Reservation Queue</h6>
                <div class="review-row"><span>Queue position</span><span id="rQueuePosition"></span></div>
                <div class="review-row"><span>Candidate role</span><span id="rCandidateRole"></span></div>
                <div class="review-row" id="rTimeoutRow"><span>72-hour timeout</span><span class="text-amber-700 font-semibold">Flagged for administrative review</span></div>
            </div>

            <div class="review-section">
                <h6>Adopter Profile</h6>
                <div class="review-row"><span>Physical Activity Level</span><span id="rActivity"></span></div>
                <div class="review-row"><span>Time Availability</span><span id="rTime"></span></div>
                <div class="review-row"><span>Prior Pet Experience</span><span id="rExperience"></span></div>
                <div class="review-row"><span>Housing Type</span><span id="rHousing"></span></div>
                <div class="review-row"><span>Household Composition</span><span id="rHousehold"></span></div>
                <div class="review-row"><span>Monthly Income Range</span><span id="rIncome"></span></div>
                <div class="review-row flex-col items-start gap-1 py-2">
                    <span class="font-semibold text-text-dark">Motivation Statement</span>
                    <p id="rMotivation" class="text-sm text-[#444] bg-[#f8f6f2] border border-[#e8e3dc] p-3 rounded-md w-full whitespace-pre-line m-0 font-normal leading-relaxed"></p>
                </div>

                <div class="review-row" id="rHistoryRow">
                    <span>Adoption Record History</span>
                    <button type="button" class="btn btn-secondary btn-sm" id="rHistoryBtn">View</button>
                </div>

                <div class="review-row">
                    <span>Document upload (valid ID, proof of residence)</span>
                    <a class="btn btn-secondary btn-sm" id="rDocumentLink" href="#" target="_blank">View</a>
                </div>
                <div class="review-row">
                    <span>OCR Verification</span>
                    <span>
                        <strong id="rDocumentStatus"></strong>
                        <a class="btn btn-secondary btn-sm ml-2" id="rVerificationLink" href="#" target="_blank">Details</a>
                    </span>
                </div>

                <div class="review-row" id="rCompatRow">
                    <span>Compatibility Result</span>
                    <button type="button" class="btn btn-secondary btn-sm" id="rCompatBtn">View</button>
                </div>
            </div>

            <div class="review-section" id="rInterviewSection">
                <h6>Interview</h6>
                <div class="review-row"><span>Interview Date</span><span id="rInterviewDate"></span></div>
                <div class="review-row"><span>Conducted By</span><span id="rConductedBy"></span></div>
                <div class="review-row" id="rInterviewNotesRow">
                    <span>Interview Notes</span>
                </div>
                <p id="rInterviewNotesText" class="text-[.88rem] text-[#555] bg-neutral-light rounded-lg p-3 mt-2"></p>
            </div>

            <div id="rDecisionRemarksSection" class="review-section">
                <h6>Decision Remarks</h6>
                <p id="rDecisionRemarksText" class="text-[.88rem] text-[#555] bg-neutral-light rounded-lg p-3"></p>
            </div>

            <div id="rCompatBanner" class="mx-6 mb-2 rounded-full py-2.5 text-center text-white font-bold text-[.9rem]"></div>

        </div>

        <div class="custom-modal-footer" id="rActionsRow">
            <button type="button" class="btn btn-secondary" onclick="closeModal('applicationReviewModal')">Close</button>
            <button type="button" class="btn btn-danger" id="rRejectBtn">Reject</button>
            <button type="button" class="btn btn-blue" id="rScheduleBtn">Schedule Interview</button>
            <button type="button" class="btn btn-sucess" id="rApproveBtn">Approve</button>
            <button type="button" class="btn btn-yellow" id="rNoShowBtn">Mark No Show</button>
            <button type="button" class="btn btn-secondary" id="rWithdrawBtn">Mark Withdrawn</button>
            <button type="button" class="btn btn-primary" id="rOverrideBtn">Administrative Override</button>
        </div>

        <form id="rDecisionForm" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="decision" id="rDecisionInput">
            <input type="hidden" name="decision_remarks" id="rDecisionRemarksInput">
        </form>

        <form id="rQueueOutcomeForm" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="outcome" id="rQueueOutcomeInput">
            <input type="hidden" name="reason" id="rQueueReasonInput">
        </form>

        <form id="rOverrideForm" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="override_reason" id="rOverrideReasonInput">
        </form>

    </div>
</div>
