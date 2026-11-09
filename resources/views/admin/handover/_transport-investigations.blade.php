@php
    $incidentService = app(\App\Services\HandoverTransportIncidentService::class);
    $isAdministrator = auth()->user()->role === \App\Enums\Role::Administrator;
    $pendingHours = $incidentService->maximumPendingHours();
    $hasIncidents = $record->transportIncidents->isNotEmpty();
    $isOverdue = $incidentService->isOverdue($record);
@endphp

<section class="rounded-card border border-[#e2ddd7] bg-white p-6 shadow-card space-y-5" aria-labelledby="transportInvestigationsHeading">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e2ddd7] pb-4">
        <div>
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-primary text-base"></i>
                <h3 id="transportInvestigationsHeading" class="font-primary font-bold text-base text-text-dark m-0">Investigation / Follow-Up</h3>
            </div>
            <p class="text-xs text-[#777] m-0 mt-1">Track transport concerns, follow-up deadlines, and delivery receipt investigations.</p>
        </div>
        <div>
            @if ($isOverdue)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-800 border border-red-300">
                    <i class="fa-solid fa-triangle-exclamation text-xs text-red-600"></i> Overdue Delivery Receipt
                </span>
            @elseif ($hasIncidents)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 border border-amber-300">
                    <i class="fa-solid fa-clock text-xs text-amber-600"></i> Active Investigations
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-completed-bg px-3 py-1 text-xs font-semibold text-completed-fg border border-completed-fg">
                    <i class="fa-solid fa-circle-check text-xs"></i> No Active Issues
                </span>
            @endif
        </div>
    </div>

    <!-- Handover Status & Attempt Context Bar -->
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3.5 flex flex-col justify-between min-w-0">
            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777]">
                <i class="fa-solid fa-traffic-light text-[#9e9e9e]"></i>
                <span>Handover Status</span>
            </div>
            <div class="mt-2 min-w-0">
                <span class="badge {{ $record->status_badge['class'] }}">
                    <i class="{{ $record->status_badge['icon'] }} mr-1.5 text-xs"></i>
                    {{ $record->status_badge['label'] }}
                </span>
            </div>
        </div>

        <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3.5 flex flex-col justify-between min-w-0">
            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#777]">
                <i class="fa-solid fa-arrows-rotate text-[#9e9e9e]"></i>
                <span>Release Context</span>
            </div>
            <div class="mt-2 min-w-0">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white border border-[#e2ddd7] px-2.5 py-1 text-xs font-semibold text-text-dark">
                    <i class="fa-solid fa-clock-rotate-left text-primary text-xs"></i>
                    <span>Attempt #{{ $record->reopen_count }}</span>
                </span>
            </div>
        </div>
    </div>

    @if ($isOverdue)
        <div class="modal-note caution flex items-start gap-3 m-0">
            <i class="fa-solid fa-triangle-exclamation text-amber-700 text-base mt-0.5 shrink-0"></i>
            <div>
                <strong class="text-amber-900 block font-semibold">Overdue delivery receipt - needs staff investigation.</strong>
                <span class="text-xs text-amber-800">This does not establish delivery failure or a missing animal. Please contact the adopter and courier immediately.</span>
            </div>
        </div>
    @endif

    <!-- Escalation & Follow-Up Policy Box -->
    <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-4 text-xs space-y-2">
        <div class="flex items-center gap-2 text-text-dark font-bold">
            <i class="fa-regular fa-clock text-primary"></i>
            <span>Standard Escalation & Follow-Up Timelines (Asia/Manila)</span>
        </div>
        <div class="flex flex-wrap gap-2 text-[11px]">
            <span class="inline-flex items-center gap-1 rounded-md bg-white border border-[#e2ddd7] px-2 py-1 text-text-dark font-medium">
                <i class="fa-solid fa-bell text-amber-600 text-[10px]"></i> Receipt reminder: window end +1 hour
            </span>
            <span class="inline-flex items-center gap-1 rounded-md bg-white border border-[#e2ddd7] px-2 py-1 text-text-dark font-medium">
                <i class="fa-solid fa-user-clock text-primary text-[10px]"></i> Staff follow-up: +3 hours
            </span>
            <span class="inline-flex items-center gap-1 rounded-md bg-white border border-[#e2ddd7] px-2 py-1 text-text-dark font-medium">
                <i class="fa-solid fa-calendar-xmark text-red-600 text-[10px]"></i> Missed pickup: +1 hour
            </span>
        </div>
        <p class="text-[11px] text-text-muted m-0 pt-0.5">
            {{ $pendingHours ? 'Shelter-configured maximum pending investigation review: '.$pendingHours.' hours from the recorded release. Staff review only.' : 'No additional maximum pending deadline is configured. Shelter approval is required before enabling that policy.' }}
        </p>
    </div>

    <!-- Investigations Case List -->
    <div class="space-y-4">
        @forelse ($record->transportIncidents as $case)
            <article id="transport-case-{{ $case->id }}" class="border border-[#e2ddd7] rounded-xl bg-white p-4.5 space-y-3.5 shadow-xs break-words">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#e2ddd7] pb-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-neutral-light border border-[#e2ddd7] text-primary">
                            <i class="fa-solid fa-clipboard-question text-xs"></i>
                        </span>
                        <h4 class="font-primary font-bold text-sm text-text-dark m-0">
                            Investigation #{{ $case->id }} - {{ \App\Models\HandoverTransportIncident::CATEGORIES[$case->category] ?? $case->category }}
                        </h4>
                    </div>
                    <div>
                        <span class="badge {{ $case->status === 'resolved' ? 'badge-completed' : 'badge-pending' }}">
                            <i class="{{ $case->status === 'resolved' ? 'fa-solid fa-circle-check' : 'fa-solid fa-clock' }} mr-1 text-xs"></i>
                            {{ \App\Models\HandoverTransportIncident::STATUSES[$case->status] ?? $case->status }}
                        </span>
                    </div>
                </div>

                <!-- Case Metadata Grid -->
                <dl class="grid gap-2.5 sm:grid-cols-3 text-sm m-0 p-0">
                    <div class="rounded-lg bg-[#FAF8F5] border border-[#e2ddd7] p-2.5">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777]">Reported (attempt {{ $case->release_attempt }})</dt>
                        <dd class="text-xs font-semibold text-text-dark mt-1 m-0 flex items-center gap-1.5">
                            <i class="fa-regular fa-calendar-check text-[#9e9e9e]"></i>
                            <span>{{ \App\Support\ManilaTime::format($case->reported_at, 'M j, Y g:i A') }}</span>
                        </dd>
                        <span class="text-[11px] text-[#777] block mt-0.5">by {{ $case->reportedBy?->full_name ?? 'System / historical report' }}</span>
                    </div>

                    <div class="rounded-lg bg-[#FAF8F5] border border-[#e2ddd7] p-2.5">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777]">Responsible staff</dt>
                        <dd class="text-xs font-semibold text-text-dark mt-1 m-0 flex items-center gap-1.5">
                            <i class="fa-solid fa-user-shield text-[#9e9e9e]"></i>
                            <span>{{ $case->assignedTo?->full_name ?? 'Needs administrator assignment' }}</span>
                        </dd>
                        @if($case->assignedTo && (!$case->assignedTo->is_active || !$case->assignedTo->hasVerifiedEmail()))
                            <span class="text-[11px] text-red-700 block mt-0.5 font-semibold">No longer eligible; reassign</span>
                        @endif
                    </div>

                    <div class="rounded-lg bg-[#FAF8F5] border border-[#e2ddd7] p-2.5">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-[#777]">Next required follow-up</dt>
                        <dd class="text-xs font-semibold text-text-dark mt-1 m-0 flex items-center gap-1.5">
                            <i class="fa-regular fa-clock text-[#9e9e9e]"></i>
                            <span>{{ \App\Support\ManilaTime::parseAndFormat($case->next_follow_up_at, 'M j, Y g:i A', $case->status === 'resolved' ? 'Resolved' : 'Needs follow-up scheduling') }}</span>
                        </dd>
                        @if($case->status !== 'resolved' && $case->next_follow_up_at?->lte(now()))
                            <span class="text-[11px] text-red-700 block mt-0.5 font-bold">Overdue follow-up</span>
                        @endif
                    </div>
                </dl>

                @if($case->max_pending_notified_at)
                    <div class="rounded-lg bg-red-50 border border-red-200 p-2.5 text-xs text-red-800 flex items-center gap-2">
                        <i class="fa-solid fa-bell text-red-600"></i>
                        <span class="font-bold">Maximum pending policy review was escalated to staff.</span>
                    </div>
                @endif

                <!-- Description & Reports -->
                <div class="space-y-2 text-xs">
                    <div class="rounded-lg bg-white border border-[#e2ddd7] p-3">
                        <span class="font-bold uppercase tracking-wider text-[#777] block text-[11px] mb-1">Case Description</span>
                        <p class="text-text-dark whitespace-pre-line m-0">{{ $case->description }}</p>
                    </div>

                    @if($case->release_context['adopter_report_note'] ?? null)
                        <div class="rounded-lg bg-amber-50/70 border border-amber-200/80 p-3">
                            <span class="font-bold uppercase tracking-wider text-amber-900 block text-[11px] mb-1">Original adopter report</span>
                            <p class="text-text-dark whitespace-pre-line m-0">{{ $case->release_context['adopter_report_note'] }}</p>
                        </div>
                    @endif

                    @if($case->findings)
                        <div class="rounded-lg bg-blue-50/60 border border-blue-200/80 p-3">
                            <span class="font-bold uppercase tracking-wider text-blue-900 block text-[11px] mb-1">Staff findings</span>
                            <p class="text-text-dark whitespace-pre-line m-0">{{ $case->findings }}</p>
                        </div>
                    @endif
                </div>

                <!-- Evidence Links -->
                @if($case->evidence_path || ($case->release_context['release_proof_path'] ?? null))
                    <div class="flex flex-wrap gap-2 text-xs pt-1">
                        @if($case->evidence_path)
                            <a class="btn btn-secondary btn-sm flex items-center gap-1.5 no-underline" href="{{ route('admin.handover.incidents.evidence', [$record, $case]) }}">
                                <i class="fa-solid fa-paperclip"></i> Download private supporting evidence
                            </a>
                        @endif
                        @if($case->release_context['release_proof_path'] ?? null)
                            <a class="btn btn-secondary btn-sm flex items-center gap-1.5 no-underline" href="{{ route('admin.handover.incidents.evidence', [$record, $case]) }}?kind=release">
                                <i class="fa-solid fa-file-image"></i> Download original release evidence
                            </a>
                        @endif
                    </div>
                @endif

                <!-- Resolution or Management Form -->
                @if ($case->status === 'resolved')
                    <div class="rounded-xl border border-completed-fg/30 bg-completed-bg/25 p-3.5 space-y-1.5 text-xs">
                        <div class="flex items-center gap-1.5 font-bold text-completed-fg">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ \App\Models\HandoverTransportIncident::OUTCOMES[$case->resolution_outcome] ?? $case->resolution_outcome }}</span>
                        </div>
                        <p class="text-text-dark whitespace-pre-line m-0">{{ $case->resolution_notes }}</p>
                        <p class="text-[11px] text-[#777] m-0 pt-1">
                            Resolved by {{ $case->resolvedBy?->full_name ?? 'Former staff' }} on {{ \App\Support\ManilaTime::parseAndFormat($case->resolved_at, 'M j, Y g:i A') }}. Case closure does not confirm receipt or activate monitoring.
                        </p>
                    </div>
                @elseif ($incidentService->canManage(auth()->user(), $case))
                    <form method="POST" action="{{ route('admin.handover.incidents.update', [$record, $case]) }}" enctype="multipart/form-data" class="space-y-3 border-t border-[#e2ddd7] pt-3.5">
                        @csrf
                        <input type="hidden" name="version" value="{{ $case->version }}">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @if($isAdministrator)
                                <div>
                                    <label class="form-label text-xs font-bold text-text-dark" for="assignedStaff{{ $case->id }}">Assign responsible staff</label>
                                    <select class="form-select text-xs py-2 rounded-lg" name="assigned_to_user_id" id="assignedStaff{{ $case->id }}">
                                        <option value="">Unassigned</option>
                                        @foreach($investigationStaff as $person)
                                            <option value="{{ $person->id }}" @selected($case->assigned_to_user_id === $person->id)>{{ $person->full_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div>
                                <label class="form-label text-xs font-bold text-text-dark" for="incidentStatus{{ $case->id }}">Investigation status</label>
                                <select class="form-select text-xs py-2 rounded-lg" name="status" id="incidentStatus{{ $case->id }}" required>
                                    @foreach(\App\Models\HandoverTransportIncident::STATUSES as $value => $label)
                                        @if($value !== 'resolved' || $isAdministrator)
                                            <option value="{{ $value }}" @selected($case->status === $value)>{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs font-bold text-text-dark" for="incidentFollowUp{{ $case->id }}">Next follow-up (Philippine time; required while open)</label>
                            <input class="form-control text-xs py-2 rounded-lg" id="incidentFollowUp{{ $case->id }}" type="datetime-local" name="next_follow_up_at" value="{{ $case->next_follow_up_at ? \App\Support\ManilaTime::format($case->next_follow_up_at, 'Y-m-d\TH:i') : '' }}">
                        </div>

                        <div>
                            <label class="form-label text-xs font-bold text-text-dark" for="incidentFindings{{ $case->id }}">Staff findings / progress notes</label>
                            <textarea class="form-control text-xs rounded-lg" id="incidentFindings{{ $case->id }}" name="findings" rows="3" maxlength="3000" required placeholder="Record factual investigation findings...">{{ $case->findings }}</textarea>
                        </div>

                        @if(!$case->evidence_path)
                            <div>
                                <label class="form-label text-xs font-bold text-text-dark" for="incidentEvidence{{ $case->id }}">Optional private image (up to 5 MB; cannot be replaced)</label>
                                <input class="form-control text-xs rounded-lg" id="incidentEvidence{{ $case->id }}" type="file" name="evidence" accept="image/jpeg,image/png,image/webp">
                            </div>
                        @endif

                        @if($isAdministrator)
                            <div class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-3.5 space-y-2.5">
                                <span class="font-bold text-xs text-text-dark block">Resolution Actions (Admin Only)</span>
                                <div>
                                    <label class="form-label text-xs font-medium text-text-dark" for="incidentOutcome{{ $case->id }}">Resolution outcome (only when resolving)</label>
                                    <select class="form-select text-xs py-2 rounded-lg" name="resolution_outcome" id="incidentOutcome{{ $case->id }}">
                                        <option value="">Select documented outcome</option>
                                        @foreach(\App\Models\HandoverTransportIncident::OUTCOMES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label text-xs font-medium text-text-dark" for="incidentResolution{{ $case->id }}">Resolution notes (required when resolving)</label>
                                    <textarea class="form-control text-xs rounded-lg" id="incidentResolution{{ $case->id }}" name="resolution_notes" rows="2" maxlength="3000" placeholder="Detail the final resolution rationale..."></textarea>
                                </div>
                                <p class="text-[11px] text-text-muted m-0">Findings only: no automatic receipt, return processing, availability change, adoption cancellation, or monitoring activation.</p>
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1.5">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save investigation update</span>
                        </button>
                    </form>
                @else
                    <p class="text-xs text-[#777] italic m-0">Only an administrator or the assigned volunteer can update this investigation. Only administrators can assign or resolve it.</p>
                @endif

                <!-- History Dropdown -->
                <details class="text-xs border-t border-[#e2ddd7] pt-2.5">
                    <summary class="cursor-pointer font-semibold text-text-dark flex items-center gap-1.5">
                        <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                        <span>Investigation and resolution history ({{ count($case->history ?? []) }})</span>
                    </summary>
                    <ol class="space-y-2 mt-2 pl-0 list-none">
                        @foreach($case->history ?? [] as $entry)
                            <li class="rounded-lg border border-[#e2ddd7] bg-[#FAF8F5] p-2.5 space-y-1">
                                <div class="flex flex-wrap items-center justify-between text-[11px] font-bold text-text-dark">
                                    <span>{{ $entry['event'] }} · {{ $entry['status'] }}</span>
                                    <span class="text-[#777] font-normal">{{ \App\Support\ManilaTime::parseAndFormat($entry['at'] ?? null, 'M j, Y g:i A') }} (actor #{{ $entry['actor_id'] ?? 'System' }}, rev {{ $entry['version'] }})</span>
                                </div>
                                @if($entry['findings'] ?? null)<p class="whitespace-pre-line text-text-dark m-0">{{ $entry['findings'] }}</p>@endif
                                @if($entry['resolution_notes'] ?? null)<p class="whitespace-pre-line text-text-dark m-0">{{ $entry['resolution_notes'] }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </details>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-[#e2ddd7] bg-neutral-light/30 p-5 text-center">
                <i class="fa-solid fa-shield-heart text-[#9e9e9e] text-2xl mb-1.5 block"></i>
                <p class="text-sm font-semibold text-text-dark m-0">No transport investigations recorded.</p>
                <p class="text-xs text-text-muted mt-0.5 m-0">No transportation concerns or delivery issues have been reported for this animal.</p>
            </div>
        @endforelse
    </div>

    <!-- Report Concern Section -->
    @if($record->released_at && $record->release_method === 'delivery')
        <details class="rounded-xl border border-[#e2ddd7] bg-[#FAF8F5] p-4 text-sm group">
            <summary class="cursor-pointer font-bold text-text-dark flex items-center justify-between list-none">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                    <span>Report a transportation concern</span>
                </span>
                <span class="text-xs text-[#777] group-open:rotate-180 transition"><i class="fa-solid fa-chevron-down"></i></span>
            </summary>
            <form method="POST" action="{{ route('admin.handover.incidents.store', $record) }}" enctype="multipart/form-data" class="space-y-3 mt-3.5 border-t border-[#e2ddd7] pt-3.5">
                @csrf
                <input type="hidden" name="release_attempt" value="{{ $record->reopen_count }}">
                <div>
                    <label class="form-label text-xs font-bold text-text-dark" for="transportCategory">Reported category (not a verified finding)</label>
                    <select class="form-select text-xs py-2 rounded-lg" id="transportCategory" name="category" required>
                        <option value="">Select concern...</option>
                        @foreach(\App\Models\HandoverTransportIncident::CATEGORIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs font-bold text-text-dark" for="transportDescription">Description</label>
                    <textarea class="form-control text-xs rounded-lg" id="transportDescription" name="description" rows="3" maxlength="3000" required placeholder="Describe the transportation irregularity or issue observed..."></textarea>
                </div>
                <div>
                    <label class="form-label text-xs font-bold text-text-dark" for="transportEvidence">Optional private evidence image (JPEG, PNG, WebP; up to 5 MB)</label>
                    <input class="form-control text-xs rounded-lg" id="transportEvidence" type="file" name="evidence" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit" class="btn btn-secondary btn-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-flag"></i>
                        <span>Record reported concern</span>
                    </button>
                </div>
            </form>
        </details>
    @endif
</section>
