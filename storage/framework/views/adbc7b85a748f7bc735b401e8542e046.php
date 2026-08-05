<div class="custom-modal-backdrop" id="applicationReviewModal">
    <div class="custom-modal review-modal">

        <div class="review-header">
            <div>
                <h4 id="reviewApplicantPet">Application Review</h4>
                <small id="reviewSubheading">Applicant: · Pet: · Submitted:</small>
            </div>
            <span id="reviewStatusBadge" class="badge"></span>
        </div>

        <div class="custom-modal-body !pt-0">

            <div class="review-section">
                <h6>Personal Information</h6>
                <div class="review-row"><span>Full Name</span><span id="rFullName"></span></div>
                <div class="review-row"><span>Contact</span><span id="rContact"></span></div>
                <div class="review-row"><span>Email</span><span id="rEmail"></span></div>
                <div class="review-row"><span>Address</span><span id="rAddress"></span></div>
            </div>

            <div class="review-section">
                <h6>Adopter Profile</h6>
                <div class="review-row"><span>Physical Activity Level</span><span id="rActivity"></span></div>
                <div class="review-row"><span>Time Availability</span><span id="rTime"></span></div>
                <div class="review-row"><span>Prior Pet Experience</span><span id="rExperience"></span></div>
                <div class="review-row"><span>Housing Type</span><span id="rHousing"></span></div>
                <div class="review-row"><span>Household Composition</span><span id="rHousehold"></span></div>
                <div class="review-row"><span>Monthly Income Range</span><span id="rIncome"></span></div>

                <div class="review-row" id="rHistoryRow">
                    <span>Adoption Record History</span>
                    <button type="button" class="btn btn-secondary btn-sm" id="rHistoryBtn">View</button>
                </div>

                <div class="review-row">
                    <span>Document upload (valid ID, proof of residence)</span>
                    <a class="btn btn-secondary btn-sm" id="rDocumentLink" href="#" target="_blank">View</a>
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
                <p id="rInterviewNotesText" class="text-[.88rem] text-[#555] bg-neutral-light rounded-lg p-3 mt-1"></p>
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
            <button type="button" class="btn btn-sucess" id="rApproveBtn">Approve</button>
        </div>

        <form id="rDecisionForm" method="POST" class="hidden">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="decision" id="rDecisionInput">
            <input type="hidden" name="decision_remarks" id="rDecisionRemarksInput">
        </form>

    </div>
</div>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/application/_review-modal.blade.php ENDPATH**/ ?>