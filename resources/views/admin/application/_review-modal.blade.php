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

            <div class="review-container">
                <div class="review-section">
                    <h6>Personal Information</h6>
                    <div class="review-row"><span>Full Name</span><span id="rFullName"></span></div>
                    <div class="review-row"><span>Contact</span><span id="rContact"></span></div>
                    <div class="review-row"><span>Email</span><span id="rEmail"></span></div>
                    <div class="review-row"><span>Address</span><span id="rAddress"></span></div>
                </div>
            </div>

            <div class="review-container">
                <div class="review-section">
                    <h6>Reservation Queue</h6>
                    <div class="review-row"><span>Queue position</span><span id="rQueuePosition"></span></div>
                    <div class="review-row"><span>Candidate role</span><span id="rCandidateRole"></span></div>
                    <div class="review-row" id="rTimeoutRow"><span>72-hour timeout</span><span class="text-amber-700 font-semibold">Flagged for administrative review</span></div>
                </div>
            </div>

            <div class="review-container">
                <div class="review-section" aria-label="Applicant history screening">
                    <h6>Applicant History</h6>
                    <div class="review-row"><span>Review status</span><span id="rHistoryStatus" class="badge"></span></div>
                    <div class="review-row"><span>Previous applications</span><span id="rHistoryApplications"></span></div>
                    <div class="review-row"><span>Approved placements</span><span id="rHistoryPlacements"></span></div>
                    <div class="review-row"><span>Flagged welfare reports</span><span id="rHistoryFlags"></span></div>
                    <div class="review-row"><span>Missed / late check-ins</span><span id="rHistoryCheckins"></span></div>
                    <ul id="rHistoryReasons" class="text-sm text-[#555] list-disc pl-5"></ul>
                    <p class="text-xs text-[#777] mt-2">Decision support only. Compatibility ranking is unchanged; staff review the underlying records.</p>
                    <button type="button" class="btn btn-secondary btn-sm my-2" id="rFullHistoryBtn"><i class="fa-solid fa-clock-rotate-left mr-1"></i> View Full History</button>
                </div>
            </div>

            <div class="review-container">
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
                        <p id="rMotivation" class="text-sm text-[#444] bg-white border border-gray-400 p-3 rounded-md w-full whitespace-pre-line m-0 font-normal leading-relaxed"></p>
                    </div>
                </div>

                <!-- <div class="review-row" id="rHistoryRow">
                    <span>Adoption Record History</span>
                    <button type="button" class="btn btn-secondary btn-sm" id="rHistoryBtn"><i class="fa-solid fa-clock-rotate-left mr-1"></i> View History</button>
                </div> -->

                <div class="review-row">
                    <span>Government ID upload (name and residential address)</span>
                    <a class="btn btn-secondary btn-sm" id="rDocumentLink" href="#" target="_blank"><i class="fa-solid fa-file-arrow-down"></i>View Document</a>
                </div>
                <div class="review-row">
                    <span>OCR Verification</span>
                    <span>
                        <strong id="rDocumentStatus"></strong>
                        <a class="btn btn-secondary btn-sm ml-2" id="rVerificationLink" href="#" target="_blank"><i class="fa-solid fa-file-lines"></i>Details</a>
                    </span>
                </div>

                <div class="review-row" id="rCompatRow">
                    <span>Compatibility Result</span>
                    <button type="button" class="btn btn-secondary btn-sm" id="rCompatBtn">View</button>
                </div>
            </div>

            <div class="review-container">
                <div class="review-section" id="rInterviewSection">
                    <h6>Interview</h6>
                    <div class="review-row"><span>Staff-assisted Identity Verification</span><a class="btn btn-secondary btn-sm" id="rIdentityLink" href="#">Review / Verify Identity</a></div>
                    <div class="review-row"><span>Interview Date</span><span id="rInterviewDate"></span></div>
                    <div class="review-row"><span>Conducted By</span><span id="rConductedBy"></span></div>
                    <div class="review-row" id="rInterviewModeRow"><span>Interview Type</span><span id="rInterviewMode"></span></div>
                    <div class="review-row" id="rInterviewMeetingRow"><span>Google Meet</span><a id="rInterviewMeetingLink" href="#" target="_blank" rel="noopener noreferrer">Open Google Meet</a></div>
                    <div class="review-row" id="rInterviewLocationRow"><span>Location</span><span id="rInterviewLocation" class="whitespace-pre-line"></span></div>
                    <div class="review-row" id="rInterviewNotesRow">
                        <span>Interview Notes</span>
                    </div>
                    <p id="rInterviewNotesText" class="text-[.88rem] text-[#555] bg-white border border-gray-400 rounded-lg p-3 my-2"></p>
                </div>
            </div>

            <div class="review-section" id="rRescheduleSection" hidden>
                <h6>Interview Reschedule Requested</h6>
                <p class="text-sm">The current interview time remains official until staff confirms a new one.</p>
                <div class="review-row"><span>Current schedule</span><span id="rRescheduleCurrent"></span></div>
                <div class="review-row"><span>Adopter availability</span><div id="rRescheduleOptions"></div></div>
                <div class="review-row"><span>Reason</span><span id="rRescheduleReason"></span></div>
                <form id="rRescheduleAcceptForm" action="{{ route('admin.applications.schedule') }}" method="POST" class="mt-3" data-interview-details>
                    @csrf
                    <input type="hidden" name="application_id" id="rRescheduleAppId">
                    <input type="hidden" name="interview_date" id="rRescheduleDate">
                    <input type="hidden" name="interview_time" id="rRescheduleTime">
                    <label class="form-label" for="rRescheduleStaff">Confirmed interviewer</label>
                    <select class="form-select" name="staff_id" id="rRescheduleStaff" required>
                        <option value="">Select Interviewer</option>
                        @foreach ($volunteers as $volunteer)
                            <option value="{{ $volunteer->id }}" data-staff-name="{{ $volunteer->full_name }}">{{ $volunteer->full_name }} ({{ $volunteer->role->value }})</option>
                        @endforeach
                    </select>
                    <div class="form-group mt-3">
                        <label class="form-label" for="rRescheduleMode">Interview Type *</label>
                        <select class="form-select" name="interview_mode" id="rRescheduleMode" required>
                            <option value="">Select interview type</option>
                            <option value="Online">Online</option>
                            <option value="InPerson">In-person</option>
                        </select>
                    </div>
                    <div class="form-group mt-3" data-interview-field="Online" hidden>
                        <label class="form-label" for="rRescheduleMeet">Google Meet Link *</label>
                        <input class="form-control" type="url" name="interview_meeting_url" id="rRescheduleMeet" placeholder="https://meet.google.com/abc-defg-hij" disabled>
                    </div>
                    <div class="form-group mt-3" data-interview-field="InPerson" hidden>
                        <label class="form-label" for="rRescheduleLocation">Interview Location *</label>
                        <textarea class="form-control" name="interview_location" id="rRescheduleLocation" maxlength="1000" rows="3" disabled></textarea>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-blue" id="rRescheduleAcceptBtn">Accept Selected Time</button>
                        <button type="button" class="btn btn-secondary" id="rRescheduleDifferentBtn">Set Different Time</button>
                        <button type="submit" class="btn btn-danger" id="rRescheduleDeclineBtn" form="rRescheduleDeclineForm">Decline Request</button>
                    </div>
                </form>
                <form id="rRescheduleDeclineForm" method="POST" class="hidden">@csrf</form>
            </div>

            <div class="review-container">
                <div id="rDecisionRemarksSection" class="review-section">
                    <h6>Decision Remarks</h6>
                    <p id="rDecisionRemarksText" class="text-[.88rem] text-[#555] bg-white border border-gray-400 rounded-lg p-3 mb-3"></p>
                </div>
            </div>

            <div id="rCompatBanner" class="mx-6 mb-2 rounded-full py-2.5 text-center text-white font-bold text-[.9rem]"></div>

        </div>

        <div class="custom-modal-footer" id="rActionsRow">
            <button type="button" class="btn btn-secondary" onclick="closeModal('applicationReviewModal')">Close</button>
            @if(auth()->user()->isAdmin())
                <button type="button" class="btn btn-danger" id="rRejectBtn">Reject</button>
            @endif
            <button type="button" class="btn btn-blue" id="rScheduleBtn">Schedule Interview</button>
            @if(auth()->user()->isAdmin())
                <button type="button" class="btn btn-success" id="rApproveBtn">Approve</button>
            @endif
            <button type="button" class="btn btn-yellow" id="rNoShowBtn">Mark No Show</button>
            <button type="button" class="btn btn-secondary" id="rWithdrawBtn">Mark Withdrawn</button>
            @if(auth()->user()->isAdmin())
                <button type="button" class="btn btn-primary" id="rOverrideBtn">Administrative Override</button>
            @endif
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
