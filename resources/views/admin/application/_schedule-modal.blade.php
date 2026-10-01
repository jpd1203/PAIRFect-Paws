{{-- ORIGINAL PENDING SCHEDULE MODAL --}}
<div class="custom-modal-backdrop" id="scheduleInterviewModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <div>
                <h2>Schedule Interview</h2>
                <small id="scheduleSubheading">Applicant: · Pet: · Submitted:</small>
            </div>
        </div>
        <form action="{{ route('admin.applications.schedule') }}" method="POST">
            @csrf
            <input type="hidden" name="application_id" id="scheduleAppId">
            <div class="custom-modal-body">
                <div class="grid grid-cols-1 gap-4 max-[768px]:grid-cols-1">
                    <div class="form-group">
                        <label class="form-label">Interview Date</label>
                        <input type="date" name="interview_date" class="form-control" min="{{ \App\Support\ManilaTime::now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Interview Time</label>
                        <input type="time" name="interview_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conducted By</label>
                        <select name="staff_id" class="form-select" required>
                            <option value="">Select Interviewer</option>
                            @foreach ($volunteers as $v)
                                <option value="{{ $v->id }}">{{ $v->full_name }} ({{ $v->role->value }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeModal('scheduleInterviewModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

{{-- NEW TOP BUTTON SCHEDULE INTERVIEW MODAL (NEAR SEARCH BAR) --}}
<div class="custom-modal-backdrop" id="scheduleNewInterviewTopModal">
    <div class="custom-modal max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <div class="mb-4">
            <h2 class="text-xl font-bold text-gray-900">Schedule New Interview</h2>
            <div class="mt-3 rounded-xl bg-emerald-50 border border-emerald-800 p-3 text-xs leading-relaxed text-emerald-800">
                Scheduling an interview will move this application to &ldquo;Interview Scheduled&rdquo; status. An email notification will be sent to the applicant.
            </div>
        </div>
        <form action="{{ route('admin.applications.schedule') }}" method="POST" id="topScheduleForm">
            @csrf
            <input type="hidden" name="application_id" id="topScheduleAppId" required>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Applicant</label>
                    <div class="relative">
                        <input type="text" id="applicantSearch"
                               class="w-full rounded-xl border border-gray-800 pl-3.5 pr-9 py-2 text-sm font-medium focus:border-emerald-600 focus:outline-none"
                               placeholder="Search applicant name..."
                               autocomplete="off" role="combobox" aria-autocomplete="list"
                               aria-controls="applicantSearchResults" aria-expanded="false">
                        <button type="button" id="clearApplicantSearchBtn"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 flex h-6 w-6 items-center justify-center rounded-full text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition cursor-pointer"
                                style="display: none;"
                                aria-label="Clear applicant search">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        <div id="applicantSearchResults" class="applicant-search-results" role="listbox" hidden style="display: none;"></div>
                    </div>
                    <p id="applicantSearchError" class="mt-1 text-xs font-semibold text-red-700" hidden>
                        Select an applicant from the results before continuing.
                    </p>

                    <div id="selectedApplicant" class="selected-applicant" hidden>
                        <div>
                            <strong id="selectedApplicantName"></strong>
                            <small id="selectedApplicantDetails"></small>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelectedApplicant()">Change</button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Interview Date</label>
                        <input type="date" name="interview_date" class="w-full rounded-xl border border-gray-800 px-3.5 py-2 text-sm font-medium focus:border-emerald-600 focus:outline-none" min="{{ \App\Support\ManilaTime::now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Interview Time</label>
                        <input type="time" name="interview_time" class="w-full rounded-xl border border-gray-800 px-3.5 py-2 text-sm font-medium focus:border-emerald-600 focus:outline-none" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Conducted By</label>
                    <select name="staff_id" class="w-full rounded-xl border border-gray-800 px-3.5 py-2.5 text-sm font-medium focus:border-emerald-600 focus:outline-none" required>
                        <option value="">Select Staff / Volunteer...</option>
                        @foreach ($volunteers as $v)
                            <option value="{{ $v->id }}">{{ $v->full_name }} ({{ $v->role->value }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="btn-secondary rounded-xl px-6 py-2 text-[.9rem] font-semibold" onclick="closeModal('scheduleNewInterviewTopModal')">
                    Cancel
                </button>
                <button type="submit" id="topScheduleSubmit" disabled
                        class="rounded-xl border border-teal-600 bg-teal-100 px-6 py-2 text-sm font-bold text-teal-900 hover:bg-teal-200 transition disabled:cursor-not-allowed disabled:opacity-50">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>
