@extends('admin.layouts.app')

@section('title', 'Flagged Cases - PAIRfect Paws Admin')

@section('content')
    <div class="heading-text">
        <h2>Flagged Post-Adoption Cases</h2>
        <p>Review unresolved welfare concerns and record the action taken before closing each flag.</p>
    </div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <span class="badge badge-flagged">{{ $flagged->count() }} unresolved</span>
        <a href="{{ route('admin.monitoring.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> All Monitoring
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-status-danger-text bg-status-danger-bg p-4 text-sm text-status-danger-text" role="alert">
            <strong>The case could not be updated.</strong>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @forelse ($flagged as $log)
        @php
            $survey = is_array($log->survey_data) ? $log->survey_data : [];
            $reasons = collect($log->flag_reasons ?? [])->map(function ($reason) {
                if (is_string($reason)) {
                    return ['message' => $reason, 'flagged_at' => null];
                }

                if (is_array($reason)) {
                    return [
                        'message' => $reason['message'] ?? $reason['reason'] ?? 'Staff review requested.',
                        'flagged_at' => \App\Support\ManilaTime::parseAndFormat(
                            $reason['flagged_at'] ?? null,
                            'M j, Y g:i A',
                            '',
                        ),
                    ];
                }

                return null;
            })->filter();
            $petStatus = $log->pet_current_status?->value
                ?? data_get($survey, 'pet_current_status')
                ?? 'Not reported';
        @endphp

        <article class="dashboard-box mb-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-[#eee8df] pb-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="!mb-0">{{ $log->pet?->name ?: 'Unknown pet' }}</h3>
                        <span class="badge badge-flagged">Flagged</span>
                        <span class="badge badge-pending">{{ $log->milestone_display }}</span>
                    </div>
                    <p class="mt-1 text-sm text-[#666]">
                        Adopter: <strong>{{ $log->user?->full_name ?: 'Unknown adopter' }}</strong>
                        &middot; Due {{ $log->due_date->format('M j, Y') }}
                        &middot; {{ $log->reminders_sent }} reminder{{ $log->reminders_sent === 1 ? '' : 's' }} sent
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if (filled($log->video_path))
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.monitoring.video', $log) }}" target="_blank" rel="noopener">
                            <i class="fa-solid fa-video"></i> Open Welfare Video
                        </a>
                    @endif

                    @if (filled($log->photo_path))
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.monitoring.photo', $log) }}" target="_blank" rel="noopener">
                            <i class="fa-solid fa-camera"></i> Legacy Welfare Photo
                        </a>
                    @endif

                    @if (!$log->submitted_date)
                        <form method="POST" action="{{ route('admin.monitoring.reminder', $log) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-envelope"></i> Send Reminder
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if (filled($log->video_path))
                <section class="mb-4 rounded-xl border border-[#e5e1da] bg-black p-3">
                    <h4 class="mb-3 font-bold text-white">Live welfare video</h4>
                    <video class="max-h-[480px] w-full rounded-lg" controls playsinline preload="metadata">
                        <source src="{{ route('admin.monitoring.video', $log) }}">
                        Your browser cannot play this welfare video.
                    </video>
                </section>
            @endif

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="rounded-xl border border-[#e5e1da] bg-white p-4">
                    <h4 class="mb-3 font-bold text-[#39332f]">Report summary</h4>
                    <dl class="space-y-2 text-sm">
                        <div><dt class="font-semibold text-[#666]">Submitted</dt><dd>{{ $log->submitted_date ? \App\Support\ManilaTime::format($log->submitted_date, 'M j, Y g:i A') : 'Not submitted' }}</dd></div>
                        <div><dt class="font-semibold text-[#666]">Pet status</dt><dd>{{ $petStatus }}</dd></div>
                        <div><dt class="font-semibold text-[#666]">Eating habits</dt><dd>{{ $log->eating_habits ?? data_get($survey, 'eating_habits') ?? 'Not reported' }}</dd></div>
                        <div><dt class="font-semibold text-[#666]">Behavior</dt><dd>{{ $log->behavioral_observations ?? data_get($survey, 'behavioral_observations') ?? 'Not reported' }}</dd></div>
                        <div><dt class="font-semibold text-[#666]">Living conditions</dt><dd>{{ $log->living_conditions ?? data_get($survey, 'living_conditions') ?? 'Not reported' }}</dd></div>
                        <div><dt class="font-semibold text-[#666]">Veterinary notes</dt><dd>{{ $log->vet_visit_details ?? data_get($survey, 'vet_visit_details') ?? 'Not reported' }}</dd></div>
                        <div><dt class="font-semibold text-[#666]">Concerns</dt><dd>{{ $log->concerns ?? data_get($survey, 'concerns') ?? 'No concerns reported.' }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <h4 class="mb-3 font-bold text-amber-900">Why this case was flagged</h4>
                    @if ($reasons->isEmpty())
                        <p class="text-sm text-amber-900">No structured reason was recorded. Review the report and reminder history before resolving it.</p>
                    @else
                        <ul class="space-y-3 text-sm text-amber-950">
                            @foreach ($reasons as $reason)
                                <li class="rounded-lg border border-amber-200 bg-white/70 p-3">
                                    <span>{{ $reason['message'] }}</span>
                                    @if ($reason['flagged_at'])
                                        <small class="mt-1 block text-amber-800">Recorded {{ $reason['flagged_at'] }}</small>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <form method="POST" action="{{ route('admin.monitoring.resolve', $log) }}" class="mt-4 rounded-xl border border-[#e5e1da] bg-[#faf9f7] p-4">
                @csrf
                <label for="resolution-note-{{ $log->id }}" class="form-label">Resolution Note</label>
                <textarea
                    id="resolution-note-{{ $log->id }}"
                    name="resolution_note"
                    class="form-control remarks-textarea"
                    rows="3"
                    required
                    maxlength="2000"
                    placeholder="Describe the contact, intervention, or welfare action taken..."
                >{{ old('resolution_note') }}</textarea>
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-circle-check"></i> Mark Resolved
                    </button>
                </div>
            </form>
        </article>
    @empty
        <div class="dashboard-box py-12 text-center">
            <i class="fa-solid fa-circle-check mb-3 text-4xl text-status-success-text"></i>
            <h3>No unresolved flags</h3>
            <p class="text-sm text-[#777]">There are currently no post-adoption cases waiting for staff review.</p>
        </div>
    @endforelse
@endsection
