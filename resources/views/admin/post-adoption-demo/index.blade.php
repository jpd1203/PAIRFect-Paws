@extends('admin.layouts.app')

@section('title', 'Post-Adoption Presentation Demo | PAIRfect Paws')

@section('content')
    <div class="heading-text">
        <h2>Post-Adoption Presentation Demo</h2>
        <p>Show due, overdue, reminder, and flagged scenarios for one completed adoption at a time.</p>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-400 bg-red-50 p-4 text-sm text-red-900" role="alert">
            <strong>Demo action was not completed.</strong>
            <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="dashboard-box mb-5">
        <h3>Choose an adopter with a completed handover</h3>
        @if($applications->isEmpty())
            <p>No approved adoptions have a confirmed received handover yet.</p>
        @else
            <div class="form-group mt-3 w-full max-w-sm">
                <label for="demo_adopter_search">Search adopter, pet, or application number</label>
                <input id="demo_adopter_search" class="form-control" type="search" autocomplete="off" placeholder="Start typing a name or pet">
            </div>
            <form method="GET" action="{{ route('admin.post-adoption-demo.index') }}" class="mt-3 flex flex-wrap items-end gap-3">
                <div class="form-group min-w-[280px] flex-1">
                    <label for="demo_application_id">Adopter and adopted pet</label>
                    <select id="demo_application_id" name="application_id" class="form-select" required>
                        <option value="">Select an adoption</option>
                        @foreach($applications as $application)
                            <option value="{{ $application->id }}" @selected($selected?->id === $application->id)>
                                {{ $application->user?->full_name ?: 'Unknown adopter' }} — {{ $application->pet?->name ?: 'Unknown pet' }} (Application #{{ $application->id }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Open demo controls</button>
            </form>
        @endif
    </section>

    @if($selected)
        <section class="dashboard-box mb-5">
            <h3>{{ $selected->user?->full_name }} and {{ $selected->pet?->name }}</h3>
            <p class="mt-2 text-sm">Adopter email: {{ $selected->user?->email ?: 'Not available' }}</p>
            <p class="mt-1 text-sm">Official adoption date: {{ app(\App\Services\PostAdoptionScheduleService::class)->adoptionDate($selected)->format('F j, Y') }}</p>
            @if($state)
                <p class="mt-2 text-sm font-semibold text-amber-900">Demo date: {{ \Carbon\CarbonImmutable::parse($state['date'])->format('F j, Y') }}. Expires {{ \App\Support\ManilaTime::parseAndFormat($state['expires_at'], 'F j, Y g:i A') }}.</p>
            @else
                <p class="mt-2 text-sm">Demo inactive. This adopter uses the real date.</p>
            @endif

            <form method="POST" action="{{ route('admin.post-adoption-demo.activate') }}" class="mt-5 flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="application_id" value="{{ $selected->id }}">
                <input type="hidden" name="mode" value="date">
                <div class="form-group">
                    <label for="demo_target_date">Choose a presentation date</label>
                    <input id="demo_target_date" class="form-control" type="date" name="target_date" value="{{ $state['date'] ?? '' }}" required>
                </div>
                <button class="btn btn-primary" type="submit">Apply date</button>
            </form>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach(['due' => 'Show due', 'overdue' => 'Show overdue', 'next' => 'Next milestone', 'all' => 'All overdue'] as $mode => $label)
                    <form method="POST" action="{{ route('admin.post-adoption-demo.activate') }}">
                        @csrf
                        <input type="hidden" name="application_id" value="{{ $selected->id }}">
                        <input type="hidden" name="mode" value="{{ $mode }}">
                        <button type="submit" class="btn btn-secondary">{{ $label }}</button>
                    </form>
                @endforeach
                @if($state)
                    <form method="POST" action="{{ route('admin.post-adoption-demo.reset', $selected) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger">Reset this adoption's demo</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="dashboard-box">
            <h3>Milestone scenarios</h3>
            <p class="mb-4 text-sm">Advance to a due date, send one demo reminder, advance one presentation day, then send the second. The second successful send creates a demo-only staff flag.</p>
            <div class="space-y-4">
                @foreach($logs as $log)
                    @php
                        $demo = app(\App\Services\PostAdoptionWebDemoService::class);
                        $count = $demo->reminderCount($log);
                        $flagged = $demo->isDemoFlagged($log);
                    @endphp
                    <div class="rounded-xl border border-gray-300 bg-white p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <strong>{{ $log->milestone_display }}</strong>
                                <span class="ml-2 text-sm">Official due {{ $log->scheduled_date->format('F j, Y') }}</span>
                                <span class="ml-2 badge badge-{{ $log->status_slug }}">{{ $log->status_display }}</span>
                                @if($state)<span class="ml-2 text-xs font-semibold text-amber-900">DEMO</span>@endif
                            </div>
                            <span class="text-sm">{{ $count }} demo reminder{{ $count === 1 ? '' : 's' }} sent</span>
                        </div>
                        @if($state && !$log->submitted_date)
                            <form method="POST" action="{{ route('admin.post-adoption-demo.reminder', $log) }}" class="mt-3 flex flex-wrap items-center gap-3">
                                @csrf
                                <label class="text-sm"><input type="checkbox" name="confirm_email" value="1" required> Send a real, demo-labeled email to {{ $selected->user?->email }}.</label>
                                <button class="btn btn-yellow" type="submit" @disabled(!$demo->canSendReminder($log))>Send demo reminder</button>
                            </form>
                        @endif
                        @if($flagged)
                            <form method="POST" action="{{ route('admin.post-adoption-demo.resolve', $log) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                @csrf
                                <div class="form-group flex-1"><label for="demo_resolution_{{ $log->id }}">Demo resolution note</label><input id="demo_resolution_{{ $log->id }}" class="form-control" name="resolution_note" maxlength="2000" required></div>
                                <button class="btn btn-secondary" type="submit">Resolve demo flag</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                <a class="btn btn-secondary" href="{{ route('admin.monitoring.index') }}">Open staff monitoring</a>
                <a class="btn btn-secondary" href="{{ route('admin.monitoring.flagged') }}">Open flagged cases</a>
            </div>
        </section>
    @endif
@endsection

@push('scripts')
<script>
document.getElementById('demo_adopter_search')?.addEventListener('input', function () {
    const needle = this.value.trim().toLocaleLowerCase();
    const select = document.getElementById('demo_application_id');
    if (!select) return;
    Array.from(select.options).forEach((option, index) => {
        if (index === 0) return;
        const matches = option.textContent.toLocaleLowerCase().includes(needle);
        option.hidden = !matches;
        option.disabled = !matches;
    });
    if (select.selectedOptions[0]?.disabled) select.value = '';
});
</script>
@endpush
