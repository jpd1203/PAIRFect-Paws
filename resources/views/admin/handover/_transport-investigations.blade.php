@php
    $incidentService = app(\App\Services\HandoverTransportIncidentService::class);
    $isAdministrator = auth()->user()->role === \App\Enums\Role::Administrator;
    $pendingHours = $incidentService->maximumPendingHours();
@endphp
<section class="rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card space-y-4" aria-labelledby="transportInvestigationsHeading">
    <h3 id="transportInvestigationsHeading" class="font-primary font-bold text-base">Investigation / Follow-Up</h3>
    <p class="text-sm">Current handover: {{ $record->status_badge['label'] }}. Current release attempt: {{ $record->reopen_count }}.</p>
    @if ($incidentService->isOverdue($record))
        <p class="modal-note caution">Overdue delivery receipt - needs staff investigation. This does not establish delivery failure or a missing animal.</p>
    @endif
    <p class="text-xs text-text-muted">Receipt reminder: window end +1 hour. Staff follow-up: +3 hours. Missed pickup: +1 hour. All displayed times are Philippine time (Asia/Manila).</p>
    <p class="text-xs text-text-muted">{{ $pendingHours ? 'Shelter-configured maximum pending investigation review: '.$pendingHours.' hours from the recorded release. Staff review only.' : 'No additional maximum pending deadline is configured. Shelter approval is required before enabling that policy.' }}</p>
    @forelse ($record->transportIncidents as $case)
        <article id="transport-case-{{ $case->id }}" class="border border-[#e2ddd7] rounded-xl p-4 space-y-3 break-words">
            <h4 class="font-bold text-sm">Investigation #{{ $case->id }} - {{ \App\Models\HandoverTransportIncident::CATEGORIES[$case->category] ?? $case->category }}</h4>
            <span class="badge {{ $case->status === 'resolved' ? 'badge-completed' : 'badge-pending' }}">{{ \App\Models\HandoverTransportIncident::STATUSES[$case->status] ?? $case->status }}</span>
            <dl class="grid gap-2 sm:grid-cols-2 text-sm">
                <div><dt class="font-semibold">Reported (attempt {{ $case->release_attempt }})</dt><dd>{{ \App\Support\ManilaTime::format($case->reported_at, 'M j, Y g:i A') }} by {{ $case->reportedBy?->full_name ?? 'System / historical report' }}</dd></div>
                <div><dt class="font-semibold">Responsible staff</dt><dd>{{ $case->assignedTo?->full_name ?? 'Needs administrator assignment' }}@if($case->assignedTo && (!$case->assignedTo->is_active || !$case->assignedTo->hasVerifiedEmail())) - no longer eligible; reassign @endif</dd></div>
                <div><dt class="font-semibold">Next required follow-up</dt><dd>{{ \App\Support\ManilaTime::parseAndFormat($case->next_follow_up_at, 'M j, Y g:i A', $case->status === 'resolved' ? 'Resolved' : 'Needs follow-up scheduling') }}@if($case->status !== 'resolved' && $case->next_follow_up_at?->lte(now())) - overdue @endif</dd></div>
            </dl>
            @if($case->max_pending_notified_at)<p class="text-sm font-bold">Maximum pending policy review was escalated to staff.</p>@endif
            <p class="text-sm whitespace-pre-line">{{ $case->description }}</p>
            @if($case->release_context['adopter_report_note'] ?? null)<p class="text-sm whitespace-pre-line">Original adopter report: {{ $case->release_context['adopter_report_note'] }}</p>@endif
            @if($case->findings)<p class="text-sm whitespace-pre-line">Staff findings: {{ $case->findings }}</p>@endif
            @if($case->evidence_path)<a class="text-sm underline" href="{{ route('admin.handover.incidents.evidence', [$record, $case]) }}">Download private supporting evidence</a>@endif
            @if($case->release_context['release_proof_path'] ?? null)<a class="text-sm underline" href="{{ route('admin.handover.incidents.evidence', [$record, $case]) }}?kind=release">Download original release evidence</a>@endif
            @if ($case->status === 'resolved')
                <p class="text-sm font-semibold">{{ \App\Models\HandoverTransportIncident::OUTCOMES[$case->resolution_outcome] ?? $case->resolution_outcome }}</p>
                <p class="text-sm whitespace-pre-line">{{ $case->resolution_notes }}</p>
                <p class="text-xs">Resolved by {{ $case->resolvedBy?->full_name ?? 'Former staff' }} on {{ \App\Support\ManilaTime::parseAndFormat($case->resolved_at, 'M j, Y g:i A') }}. Case closure does not confirm receipt or activate monitoring.</p>
            @elseif ($incidentService->canManage(auth()->user(), $case))
                <form method="POST" action="{{ route('admin.handover.incidents.update', [$record, $case]) }}" enctype="multipart/form-data" class="space-y-3 border-t pt-3">
                    @csrf
                    <input type="hidden" name="version" value="{{ $case->version }}">
                    @if($isAdministrator)
                        <label class="form-label" for="assignedStaff{{ $case->id }}">Assign responsible staff</label>
                        <select class="form-select" name="assigned_to_user_id" id="assignedStaff{{ $case->id }}">
                            <option value="">Unassigned</option>
                            @foreach($investigationStaff as $person)<option value="{{ $person->id }}" @selected($case->assigned_to_user_id === $person->id)>{{ $person->full_name }}</option>@endforeach
                        </select>
                    @endif
                    <label class="form-label" for="incidentStatus{{ $case->id }}">Investigation status</label>
                    <select class="form-select" name="status" id="incidentStatus{{ $case->id }}" required>
                        @foreach(\App\Models\HandoverTransportIncident::STATUSES as $value => $label)
                            @if($value !== 'resolved' || $isAdministrator)<option value="{{ $value }}" @selected($case->status === $value)>{{ $label }}</option>@endif
                        @endforeach
                    </select>
                    <label class="form-label" for="incidentFollowUp{{ $case->id }}">Next follow-up (Philippine time; required while open)</label>
                    <input class="form-control" id="incidentFollowUp{{ $case->id }}" type="datetime-local" name="next_follow_up_at" value="{{ $case->next_follow_up_at ? \App\Support\ManilaTime::format($case->next_follow_up_at, 'Y-m-d\TH:i') : '' }}">
                    <label class="form-label" for="incidentFindings{{ $case->id }}">Staff findings / progress notes</label>
                    <textarea class="form-control" id="incidentFindings{{ $case->id }}" name="findings" rows="3" maxlength="3000" required>{{ $case->findings }}</textarea>
                    @if(!$case->evidence_path)
                        <label class="form-label" for="incidentEvidence{{ $case->id }}">Optional private image (up to 5 MB; cannot be replaced)</label>
                        <input class="form-control" id="incidentEvidence{{ $case->id }}" type="file" name="evidence" accept="image/jpeg,image/png,image/webp">
                    @endif
                    @if($isAdministrator)
                        <label class="form-label" for="incidentOutcome{{ $case->id }}">Resolution outcome (only when resolving)</label>
                        <select class="form-select" name="resolution_outcome" id="incidentOutcome{{ $case->id }}">
                            <option value="">Select documented outcome</option>
                            @foreach(\App\Models\HandoverTransportIncident::OUTCOMES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        <label class="form-label" for="incidentResolution{{ $case->id }}">Resolution notes (required when resolving)</label>
                        <textarea class="form-control" id="incidentResolution{{ $case->id }}" name="resolution_notes" rows="2" maxlength="3000"></textarea>
                        <p class="text-xs text-text-muted">Findings only: no automatic receipt, return processing, availability change, adoption cancellation, or monitoring activation.</p>
                    @endif
                    <button type="submit" class="btn btn-primary btn-sm">Save investigation update</button>
                </form>
            @else
                <p class="text-sm">Only an administrator or the assigned volunteer can update this investigation. Only administrators can assign or resolve it.</p>
            @endif
            <details class="text-sm">
                <summary class="cursor-pointer font-semibold">Investigation and resolution history</summary>
                <ol class="space-y-2 mt-2">
                    @foreach($case->history ?? [] as $entry)
                        <li class="border-t pt-2">{{ $entry['event'] }} - {{ $entry['status'] }} - {{ \App\Support\ManilaTime::parseAndFormat($entry['at'] ?? null, 'M j, Y g:i A') }} - actor #{{ $entry['actor_id'] ?? 'System' }} - revision {{ $entry['version'] }}
                            @if($entry['findings'] ?? null)<p class="whitespace-pre-line">{{ $entry['findings'] }}</p>@endif
                            @if($entry['resolution_notes'] ?? null)<p class="whitespace-pre-line">{{ $entry['resolution_notes'] }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </details>
        </article>
    @empty
        <p class="text-sm text-text-muted">No transport investigations recorded.</p>
    @endforelse
    @if($record->released_at && $record->release_method === 'delivery')
        <details class="text-sm border-t pt-3">
            <summary class="cursor-pointer font-semibold">Report a transportation concern</summary>
            <form method="POST" action="{{ route('admin.handover.incidents.store', $record) }}" enctype="multipart/form-data" class="space-y-3 mt-3">
                @csrf
                <input type="hidden" name="release_attempt" value="{{ $record->reopen_count }}">
                <label class="form-label" for="transportCategory">Reported category (not a verified finding)</label>
                <select class="form-select" id="transportCategory" name="category" required>
                    <option value="">Select concern</option>
                    @foreach(\App\Models\HandoverTransportIncident::CATEGORIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <label class="form-label" for="transportDescription">Description</label>
                <textarea class="form-control" id="transportDescription" name="description" rows="3" maxlength="3000" required></textarea>
                <label class="form-label" for="transportEvidence">Optional private evidence image (JPEG, PNG, WebP; up to 5 MB)</label>
                <input class="form-control" id="transportEvidence" type="file" name="evidence" accept="image/jpeg,image/png,image/webp">
                <button type="submit" class="btn btn-secondary btn-sm">Record reported concern</button>
            </form>
        </details>
    @endif
</section>
