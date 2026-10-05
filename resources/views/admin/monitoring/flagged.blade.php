@extends('admin.layouts.app')

@section('title', 'Flagged Cases - PAIRfect Paws Admin')

@section('notification-bell-in-header', true)
@section('content')
    <div class="main-content-header">
        <div class="heading-text">
            <h2>Flagged Post-Adoption Cases</h2>
            <p>Review unresolved welfare concerns and record the action taken before closing each flag.</p>
        </div>

        @include('partials.notification-bell')
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
            $reasons = collect($log->display_flag_reasons)->map(function ($reason) {
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
                    <!-- <div class="flex flex-wrap items-center gap-2">
                        <h3 class="!mb-0">{{ $log->pet?->name ?: 'Unknown pet' }}</h3>
                        <span class="badge badge-flagged">Flagged</span>
                        <span class="badge badge-pending">{{ $log->milestone_display }}</span>
                    </div> -->
                    <div class="flex items-center gap-4">
                    @if ($log->pet)
                        {{-- Pet Profile Image --}}
                        @if ($log->pet->photo_path)
                            <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 border border-gray-200 bg-gray-100">
                                <img
                                    src="{{ $log->pet->image_url }}"
                                    alt="{{ $log->pet->name }}"
                                    class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="w-12 h-12 rounded-xl shrink-0 flex items-center justify-center bg-maroon-50 text-maroon-600 border border-maroon-100 text-sm">
                                <i class="fa-solid fa-{{ strtolower($log->pet->species?->value ?? $log->pet->species) === 'cat' ? 'cat' : 'dog' }}"></i>
                            </div>
                        @endif

                        {{-- Pet Information --}}
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-4">
                                <h3 class="!mb-0 font-bold text-gray-900">
                                    {{ $log->pet->name }}
                                </h3>

                                <span class="badge badge-flagged">{{ $log->resolution_outcome === \App\Enums\ResolutionOutcome::FollowUpRequired ? 'Follow-up Required' : 'Flagged' }}</span>
                                <span class="badge badge-pending">{{ $log->milestone_display }}</span>
                            </div>

                            <p class="mt-0.5 text-xs text-gray-500 capitalize truncate">
                                {{ $log->pet->species_display }}
                                &middot;
                                {{ $log->pet->breed ?? 'Mix' }}
                            </p>
                        </div>

                    @else
                        <div class="min-w-0">
                            <h3 class="!mb-0 font-bold text-gray-900">
                                Unknown pet
                            </h3>

                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="badge badge-flagged">Flagged</span>
                                <span class="badge badge-pending">{{ $log->milestone_display }}</span>
                            </div>
                        </div>
                    @endif
                </div>
                    <p class="mt-3 text-sm text-[#666]">
                        Adopter: <strong>{{ $log->user?->full_name ?: 'Unknown adopter' }}</strong>
                        &middot; Due {{ $log->due_date->format('M j, Y') }}
                        &middot; {{ $log->display_reminders_sent }} {{ $log->has_presentation_demo ? 'demo ' : '' }}reminder{{ $log->display_reminders_sent === 1 ? '' : 's' }} sent
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
                        @if($log->has_presentation_demo)
                            @if(auth()->user()->isAdmin())
                                <a class="btn btn-secondary btn-sm" href="{{ route('admin.post-adoption-demo.index', ['application_id' => $log->application_id]) }}">Open demo controls</a>
                            @endif
                        @else
                            <form method="POST" action="{{ route('admin.monitoring.reminder', $log) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <i class="fa-solid fa-envelope"></i> Send Reminder
                                </button>
                            </form>
                        @endif
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

            <section class="mt-4 rounded-xl border border-[#e5e1da] bg-white p-4 text-sm">
                <h4 class="font-bold">Resolution Status</h4>
                <p class="mt-1">{{ $log->resolution_outcome_label }}{{ $log->resolved_at ? ' · Closed' : ' · Open' }}</p>
                @if ($log->resolution_note)
                    <p class="mt-2 whitespace-pre-wrap">{{ $log->resolution_note }}</p>
                @endif
            </section>

            @if(auth()->user()->isAdmin())
            @php($demoOnly = $log->has_presentation_demo && !($log->is_flagged && !$log->resolved_at))
            <form method="POST" action="{{ $demoOnly ? route('admin.post-adoption-demo.resolve', $log) : route('admin.monitoring.resolve', $log) }}" class="mt-4 rounded-xl border border-[#e5e1da] bg-[#faf9f7] p-4" @unless($demoOnly) data-resolution-form @endunless>
                @csrf
                @if ($demoOnly)
                    <label for="resolution-note-{{ $log->id }}" class="form-label">Demo Resolution Note *</label>
                    <textarea id="resolution-note-{{ $log->id }}" name="resolution_note" class="form-control remarks-textarea" rows="3" required maxlength="2000">{{ old('resolution_note') }}</textarea>
                @else
                    @include('admin.monitoring._resolution-fields', ['fieldPrefix' => 'flagged-'.$log->id])
                @endif
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-circle-check"></i> {{ $demoOnly ? 'Resolve Demo Flag' : 'Record Outcome' }}
                    </button>
                </div>
            </form>
            @endif
        </article>
    @empty
        <div class="dashboard-box py-12 text-center">
            <i class="fa-solid fa-circle-check mb-3 text-4xl text-status-success-text"></i>
            <h3>No unresolved flags</h3>
            <p class="text-sm text-[#777]">There are currently no post-adoption cases waiting for staff review.</p>
        </div>
    @endforelse
    @include('admin.monitoring._return-confirmation')
@endsection
