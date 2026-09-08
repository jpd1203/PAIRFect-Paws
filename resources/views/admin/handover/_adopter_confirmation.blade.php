@php
    $outcome = $record->adopter_outcome;
    $isReceived = ($outcome === 'received');
    $isFailed = ($outcome === 'not_received');
    $days = $record->days_waiting ?? 0;
    $reminders = $record->reminders ?? [];
    $lastReminder = count($reminders) > 0 ? end($reminders) : null;
@endphp

<section aria-label="Adopter confirmation" class="bg-white border border-[#e2ddd7] rounded-card shadow-card overflow-hidden">
    <div class="border-b border-[#e2ddd7] px-6 py-4 bg-secondary-bg">
        <h3 class="text-base font-bold text-text-dark font-primary m-0">Adopter Confirmation</h3>
        <p class="mt-0.5 text-xs text-[#777] m-0">
            The second half of the handover &mdash; {{ $record->adopter_name }} must confirm the pet arrived.
        </p>
    </div>

    <div class="space-y-4 p-6">
        <!-- Status Box -->
        <div class="flex gap-3.5 rounded-xl border p-4 {{ $isReceived ? 'border-status-success-text/30 bg-status-success-bg' : ($isFailed ? 'border-status-danger-text/30 bg-status-danger-bg' : 'border-status-processing-text/30 bg-status-processing-bg') }}">
            <div class="mt-0.5 shrink-0 text-base">
                @if ($isReceived)
                    <i class="fa-solid fa-circle-check text-status-success-text"></i>
                @elseif ($isFailed)
                    <i class="fa-solid fa-triangle-exclamation text-status-danger-text"></i>
                @else
                    <i class="fa-solid fa-clock text-status-processing-text"></i>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-text-dark m-0">
                    @if ($isReceived)
                        Confirmed received
                    @elseif ($isFailed)
                        Adopter says the pet never arrived
                    @else
                        Waiting on adopter &middot; {{ $days }} {{ $days === 1 ? 'day' : 'days' }}
                    @endif
                </p>

                @if ($outcome)
                    <p class="mt-1 text-xs text-text-muted m-0">
                        {{ $record->adopter_confirmed_at ? $record->adopter_confirmed_at->format('M j, Y · g:i A') : 'Recorded' }}
                    </p>
                    @if ($record->adopter_note)
                        <p class="mt-2 rounded-lg bg-white/90 border border-[#e2ddd7] px-3 py-2 text-xs italic text-text-dark m-0">
                            &ldquo;{{ $record->adopter_note }}&rdquo;
                        </p>
                    @endif
                @else
                    <p class="mt-1 text-xs text-text-muted m-0">
                        Confirmation request sent to {{ $record->adopter_phone }} and {{ $record->adopter_email }}.
                        @if ($days >= 2)
                            <span class="font-bold text-status-processing-text">A reminder is due now.</span>
                        @else
                            Automatic reminder after 2 days of no response.
                        @endif
                    </p>
                @endif
            </div>
        </div>

        <!-- Non-Response Follow-up (When awaiting confirmation) -->
        @if (!$outcome)
            <div class="rounded-xl border border-[#e2ddd7] bg-secondary-bg p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-text-dark m-0">Non-response Follow-up</p>
                        <p class="mt-0.5 text-xs text-[#777] m-0">
                            @if (count($reminders) === 0)
                                No reminders sent yet.
                            @else
                                {{ count($reminders) }} sent &middot; last {{ date('M j, g:i A', strtotime($lastReminder['at'])) }} ({{ $lastReminder['channel'] }})
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('admin.handover.reminder', $record) }}">
                            @csrf
                            <input type="hidden" name="channel" value="SMS">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-comment-sms mr-1"></i> Send SMS Reminder
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.handover.reminder', $record) }}">
                            @csrf
                            <input type="hidden" name="channel" value="Email">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-envelope mr-1"></i> Email
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- Reopen Handover Section -->
        @if ($isFailed || !$isReceived)
            <div class="rounded-xl border border-[#e2ddd7] bg-secondary-bg p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-text-dark m-0">Redo the Handover</p>
                        <p class="mt-0.5 text-xs text-[#777] m-0">
                            Clears the release record so a new pickup or delivery can be arranged.
                            @if ($record->reopen_count > 0)
                                Reopened {{ $record->reopen_count }} {{ $record->reopen_count === 1 ? 'time' : 'times' }} already.
                            @endif
                        </p>
                    </div>

                    <button type="button" onclick="toggleReopenDrawer()" 
                            class="btn {{ $isFailed ? 'btn-danger' : 'btn-secondary' }} btn-sm">
                        <i class="fa-solid fa-rotate-left mr-1"></i> Reopen handover
                    </button>
                </div>

                <!-- Reopen Drawer / Form -->
                <div id="reopenDrawer" class="hidden mt-3 rounded-xl border border-[#e2ddd7] bg-white p-4">
                    <form method="POST" action="{{ route('admin.handover.reopen', $record) }}">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="reopen-reason" class="form-label">
                                Reason for reopening
                            </label>
                            <textarea id="reopen-reason" name="reason" rows="2" required
                                      class="form-control"
                                      placeholder="e.g. Delivery failed — courier could not complete handover">{{ $isFailed ? 'Delivery failed — adopter reported pet not received' : '' }}</textarea>
                        </div>
                        
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="toggleReopenDrawer()" class="btn btn-secondary btn-sm">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                Confirm Reopen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</section>

<script>
function toggleReopenDrawer() {
    const drawer = document.getElementById('reopenDrawer');
    drawer.classList.toggle('hidden');
}
</script>
