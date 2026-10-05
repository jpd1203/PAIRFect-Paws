<div class="modal-header">
    <div>
        <h2>Post-Adoption Report</h2>
        <p>
            Applicant: You &middot; Pet: {{ $report->pet?->name ?? '—' }} &middot; {{ $report->milestone_display }}
        </p>
    </div>
</div>

<div class="profile-table">

    <div class="profile-row">
        <span>Health Status</span>
        <span>{{ $report->pet_current_status?->value ?? '—' }}</span>
    </div>

    <div class="profile-row">
        <span>Eating &amp; Drinking</span>
        <span>{{ $report->eating_habits ?? '—' }}</span>
    </div>

    <div class="profile-row">
        <span>Behavior</span>
        <span>{{ $report->behavioral_observations ?? '—' }}</span>
    </div>

    <div class="profile-row">
        <span>Vet Visit</span>
        <span>{{ $report->vet_visit_details ?? '—' }}</span>
    </div>

    <div class="profile-row">
        <span>Living Conditions</span>
        <span>{{ $report->living_conditions ?? '—' }}</span>
    </div>

</div>

@if (!empty(trim((string) $report->concerns)))
    <span class="notes-label">Additional Notes</span>
    <div class="notes-box">
        {{ $report->concerns }}
    </div>
@endif

@if ($report->resolution_outcome || $report->resolved_at || $report->is_flagged)
    <span class="notes-label">Shelter Review &amp; Resolution</span>
    <div class="profile-table">
        <div class="profile-row">
            <span>Resolution Status</span>
            <span>
                @if ($report->resolved_at)
                    <span class="badge badge-completed"><i class="fa-solid fa-circle-check mr-1"></i> {{ $report->resolution_outcome_label }} &middot; Closed</span>
                @elseif ($report->resolution_outcome === \App\Enums\ResolutionOutcome::FollowUpRequired)
                    <span class="badge badge-flagged"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Follow-up Required &middot; Open</span>
                @elseif ($report->is_flagged)
                    <span class="badge badge-flagged"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Under Staff Review &middot; Open</span>
                @else
                    <span>{{ $report->resolution_outcome_label }}</span>
                @endif
            </span>
        </div>

        @if ($report->resolution_outcome)
            <div class="profile-row">
                <span>Resolution Outcome</span>
                <span class="font-semibold text-text-dark">{{ $report->resolution_outcome->label() }}</span>
            </div>
        @endif

        @if ($report->resolved_at)
            <div class="profile-row">
                <span>Resolved Date</span>
                <span>{{ \App\Support\ManilaTime::format($report->resolved_at, 'F j, Y g:i A') }}</span>
            </div>
        @endif

        @if ($report->resolution_outcome === \App\Enums\ResolutionOutcome::PetReturned && $report->return_date)
            <div class="profile-row">
                <span>Return Date</span>
                <span>{{ \App\Support\ManilaTime::format($report->return_date, 'F j, Y') }}</span>
            </div>
        @endif
    </div>

    @if (!empty(trim((string) $report->resolution_note)))
        <span class="notes-label">Staff Resolution Note &amp; Guidance</span>
        <div class="notes-box">
            {{ $report->resolution_note }}
        </div>
    @endif

    {{-- Follow-up section for Adopter --}}
    @if ($report->resolution_outcome === \App\Enums\ResolutionOutcome::FollowUpRequired || filled($report->follow_up_notes))
        <div class="mt-4 rounded-xl border border-purple-200 bg-[#faf6fc] p-4 text-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-purple-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-comment-dots text-purple-700"></i>
                    <span>Follow-up Update</span>
                </span>
                @if ($report->follow_up_submitted_at)
                    <span class="text-xs text-purple-700">Submitted {{ \App\Support\ManilaTime::format($report->follow_up_submitted_at, 'M j, Y g:i A') }}</span>
                @endif
            </div>

            @if (filled($report->follow_up_notes))
                <div class="rounded-lg border border-purple-200 bg-white p-3 text-xs text-text-dark whitespace-pre-wrap leading-relaxed mb-3">
                    {{ $report->follow_up_notes }}
                </div>
            @endif

            @if ($report->resolution_outcome === \App\Enums\ResolutionOutcome::FollowUpRequired)
                <form action="{{ route('monitoring.follow-up', $report) }}" method="POST" class="mt-2" id="adopterFollowUpForm">
                    @csrf
                    <label for="follow_up_notes" class="block text-xs font-semibold text-purple-950 mb-1">
                        {{ filled($report->follow_up_notes) ? 'Update your follow-up response:' : 'Provide follow-up response for shelter staff:' }}
                    </label>
                    <textarea id="follow_up_notes" name="follow_up_notes" rows="3" required maxlength="2000" class="remarks-textarea w-full rounded-lg border border-purple-300 bg-white p-2.5 text-xs text-text-dark" placeholder="Share your update on feeding, vet visit results, or behavior progress...">{{ old('follow_up_notes', $report->follow_up_notes) }}</textarea>
                    <div class="mt-2 flex justify-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-paper-plane mr-1"></i> {{ filled($report->follow_up_notes) ? 'Update Follow-up' : 'Submit Follow-up' }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif
@endif

<div class="modal-actions">
    <button type="button" class="btn btn-secondary" onclick="closeReportViewModal()">
        Close
    </button>
</div>
