{{-- ORIGINAL PENDING SCHEDULE MODAL --}}
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
                <div class="grid grid-cols-1 gap-4 max-[768px]:grid-cols-1">
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
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeModal('scheduleInterviewModal')">Cancel</button>
                <button type="submit" class="btn btn-sucess">Confirm</button>
            </div>
        </form>
    </div>
</div>

{{-- NEW TOP BUTTON SCHEDULE INTERVIEW MODAL (NEAR SEARCH BAR) --}}
<div class="custom-modal-backdrop" id="scheduleNewInterviewTopModal">
    <div class="custom-modal max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <div class="mb-4">
            <h2 class="text-xl font-bold text-gray-900">Schedule New Interview</h2>
            <div class="mt-3 rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-xs leading-relaxed text-emerald-800">
                Scheduling an interview will move this application to &ldquo;Interview Scheduled&rdquo; status. An email notification will be sent to the applicant.
            </div>
        </div>
        <form action="{{ route('admin.applications.schedule') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Applicant</label>
                    <input type="text" name="applicant_name" class="w-full rounded-xl border border-gray-800 px-3.5 py-2 text-sm font-medium focus:border-emerald-600 focus:outline-none" placeholder="Enter applicant name..." required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Interview Date</label>
                        <input type="date" name="interview_date" class="w-full rounded-xl border border-gray-800 px-3.5 py-2 text-sm font-medium focus:border-emerald-600 focus:outline-none" min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Interview Time</label>
                        <input type="time" name="interview_time" class="w-full rounded-xl border border-gray-800 px-3.5 py-2 text-sm font-medium focus:border-emerald-600 focus:outline-none" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Conducted By</label>
                    <select name="conducted_by" class="w-full rounded-xl border border-gray-800 px-3.5 py-2.5 text-sm font-medium focus:border-emerald-600 focus:outline-none" required>
                        <option value="">Select Staff / Volunteer...</option>
                        @foreach ($volunteers as $v)
                            <option value="{{ $v->full_name }}">{{ $v->full_name }} ({{ $v->role }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="rounded-xl border border-red-300 bg-white px-6 py-2 text-sm font-bold text-red-800 hover:bg-red-50 transition" onclick="closeModal('scheduleNewInterviewTopModal')">Cancel</button>
                <button type="submit" class="rounded-xl border border-teal-600 bg-teal-100 px-6 py-2 text-sm font-bold text-teal-900 hover:bg-teal-200 transition">Confirm</button>
            </div>
        </form>
    </div>
</div>
