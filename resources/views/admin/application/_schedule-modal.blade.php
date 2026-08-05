<div class="custom-modal-backdrop" id="scheduleInterviewModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h2>Schedule Interview</h2>
            <small id="scheduleSubheading">Applicant: · Pet: · Submitted:</small>
        </div>
        <form action="{{ route('admin.applications.schedule') }}" method="POST">
            @csrf
            <input type="hidden" name="application_id" id="scheduleAppId">
            <div class="custom-modal-body">
                <div class="form-group">
                    <label class="form-label">Interview Date</label>
                    <input type="date" name="interview_date" class="form-control" min="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Interview Time</label>
                    <input type="time" name="interview_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Conducted By</label>
                    <select name="conducted_by" class="form-select" required>
                        <option value="">Select Interviewer</option>
                        @foreach ($volunteers as $v)
                            <option>{{ $v->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeModal('scheduleInterviewModal')">Cancel</button>
                <button type="submit" class="btn btn-sucess">Confirm</button>
            </div>
        </form>
    </div>
</div>
