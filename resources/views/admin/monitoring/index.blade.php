@extends('admin.layouts.app')

@section('title', 'Monitoring - PAIRfect Paws Admin')

@section('content')
    @php
        $counts = array_merge([
            'upcoming' => 0,
            'pending' => 0,
            'completed' => 0,
            'overdue' => 0,
            'flagged' => 0,
        ], $statusCounts ?? []);
    @endphp

    <div class="heading-text">
        <h2>Post-Adoption Monitoring</h2>
        <p>Track scheduled welfare check-ins, submitted reports, reminders, and cases requiring review.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-status-danger-text bg-status-danger-bg p-4 text-sm text-status-danger-text" role="alert">
            <strong>The action could not be completed.</strong>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="my-5">
        <input
            type="search"
            data-search-input
            data-search-scope="monitoringTableBody"
            class="search-input w-full"
            placeholder="Search by adopter, pet, or milestone..."
            aria-label="Search monitoring records"
        >
    </div>

    <div class="filter-bar" data-filter-bar data-filter-scope="monitoringTableBody" aria-label="Monitoring status filters">
        <button class="filter-btn filter-all active" data-filter-btn="all" type="button">All ({{ $checkIns->count() }})</button>
        <button class="filter-btn badge-upcoming" data-filter-btn="upcoming" type="button">Upcoming ({{ $counts['upcoming'] }})</button>
        <button class="filter-btn badge-pending" data-filter-btn="pending" type="button">Due Soon ({{ $counts['pending'] }})</button>
        <button class="filter-btn badge-completed" data-filter-btn="completed" type="button">Completed ({{ $counts['completed'] }})</button>
        <button class="filter-btn badge-overdue" data-filter-btn="overdue" type="button">Overdue ({{ $counts['overdue'] }})</button>
        <button class="filter-btn badge-flagged" data-filter-btn="flagged" type="button">Flagged ({{ $counts['flagged'] }})</button>
    </div>

    <div class="records-container custom-scrollbar">
        <div class="table-responsive">
            <table class="w-full">
                <thead>
                    <tr>
                        <th>Adopter</th>
                        <th>Pet</th>
                        <th>Milestone</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Reminders</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="monitoringTableBody">
                    @forelse ($checkIns as $checkIn)
                        @php
                            $survey = is_array($checkIn->survey_data) ? $checkIn->survey_data : [];
                            $verification = is_array(data_get($survey, '_verification'))
                                ? data_get($survey, '_verification')
                                : [];
                            $flagReasonText = collect($checkIn->flag_reasons ?? [])
                                ->map(function ($reason) {
                                    if (is_string($reason)) {
                                        return $reason;
                                    }

                                    if (is_array($reason)) {
                                        return $reason['message'] ?? $reason['reason'] ?? null;
                                    }

                                    return null;
                                })
                                ->filter()
                                ->values()
                                ->implode(' | ');
                            $healthStatus = $checkIn->pet_current_status?->value
                                ?? data_get($survey, 'pet_current_status')
                                ?? 'Not reported';
                            $concerns = $checkIn->concerns
                                ?? data_get($survey, 'concerns')
                                ?? 'No concerns reported.';
                            $reportPayload = [
                                'adopter' => $checkIn->user?->full_name ?: 'Unknown adopter',
                                'pet' => $checkIn->pet?->name ?: 'Unknown pet',
                                'milestone' => $checkIn->milestone_display,
                                'due_date' => $checkIn->due_date->format('M j, Y'),
                                'submitted_date' => $checkIn->submitted_date
                                    ? \App\Support\ManilaTime::format($checkIn->submitted_date, 'M j, Y g:i A')
                                    : 'Not submitted',
                                'status' => $checkIn->status_display,
                                'status_slug' => $checkIn->status_slug,
                                'health' => $healthStatus,
                                'eating' => $checkIn->eating_habits ?? data_get($survey, 'eating_habits') ?? 'Not reported',
                                'behavior' => $checkIn->behavioral_observations ?? data_get($survey, 'behavioral_observations') ?? 'Not reported',
                                'living' => $checkIn->living_conditions ?? data_get($survey, 'living_conditions') ?? 'Not reported',
                                'vet' => $checkIn->vet_visit_details ?? data_get($survey, 'vet_visit_details') ?? 'Not reported',
                                'concerns' => $concerns,
                                'flag_reasons' => $flagReasonText ?: 'No flag reasons recorded.',
                                'verification_method' => data_get($verification, 'method') ?: 'Not available',
                                'challenge_id' => data_get($verification, 'capture_challenge_id') ?: 'Not available',
                                'challenge_issued_at' => \App\Support\ManilaTime::parseAndFormat(
                                    data_get($verification, 'challenge_issued_at'),
                                    'M j, Y g:i A',
                                ),
                                'video_sha256' => data_get($verification, 'video_sha256') ?: 'Not available',
                                'video_mime_type' => data_get($verification, 'video_mime_type') ?: 'Not available',
                                'declared_duration_ms' => data_get($verification, 'declared_duration_ms'),
                                'verified_duration_ms' => data_get($verification, 'verified_duration_ms'),
                                'duration_verification_status' => data_get($verification, 'duration_verification_status') ?: 'Not available',
                                'legacy_c2pa_status' => data_get($verification, 'c2pa_status'),
                                'legacy_c2pa_reason' => data_get($verification, 'c2pa_reason_code'),
                                'legacy_manifest_id' => data_get($verification, 'manifest_id'),
                                'legacy_signed_at' => \App\Support\ManilaTime::parseAndFormat(
                                    data_get($verification, 'signed_at'),
                                    'M j, Y g:i A',
                                    '',
                                ),
                                'video_url' => filled($checkIn->video_path) ? route('admin.monitoring.video', $checkIn) : null,
                                'photo_url' => filled($checkIn->photo_path) ? route('admin.monitoring.photo', $checkIn) : null,
                            ];
                            $summary = ($checkIn->user?->full_name ?: 'Unknown adopter')
                                .' - '.($checkIn->pet?->name ?: 'Unknown pet')
                                .' - '.$checkIn->milestone_display;
                        @endphp
                        <tr
                            data-search-row
                            data-search-text="{{ $checkIn->user?->full_name }} {{ $checkIn->pet?->name }} {{ $checkIn->milestone_display }}"
                            data-filter-row
                            data-status="{{ $checkIn->status_slug }}"
                        >
                            <td class="font-semibold">{{ $checkIn->user?->full_name ?: 'Unknown adopter' }}</td>
                            <td>{{ $checkIn->pet?->name ?: 'Unknown pet' }}</td>
                            <td>{{ $checkIn->milestone_display }}</td>
                            <td>{{ $checkIn->due_date->format('M j, Y') }}</td>
                            <td><span class="badge {{ $checkIn->status_badge_class }}">{{ $checkIn->status_display }}</span></td>
                            <td>{{ $checkIn->reminders_sent }}</td>
                            <td class="text-center">
                                <div class="flex flex-wrap items-center justify-center gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"
                                        data-report="{{ json_encode($reportPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                                        onclick="openMonitoringViewModal(this)"
                                    >
                                        View
                                    </button>

                                    @if (!$checkIn->submitted_date && $checkIn->status_slug !== 'upcoming')
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-action="{{ route('admin.monitoring.reminder', $checkIn) }}"
                                            data-summary="{{ $summary }}"
                                            onclick="openMonitoringReminderModal(this)"
                                        >
                                            Remind
                                        </button>
                                    @endif

                                    @if (!$checkIn->is_flagged || $checkIn->resolved_at)
                                        <button
                                            type="button"
                                            class="btn btn-danger btn-sm"
                                            data-action="{{ route('admin.monitoring.flag', $checkIn) }}"
                                            data-summary="{{ $summary }}"
                                            onclick="openMonitoringFlagModal(this)"
                                        >
                                            Flag
                                        </button>
                                    @else
                                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.monitoring.flagged') }}">Review Flag</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-[#888]">No post-adoption monitoring records exist yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="custom-modal-backdrop" id="monitoringReminderModal" role="dialog" aria-modal="true" aria-labelledby="monitoringReminderTitle">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2 id="monitoringReminderTitle">Send Welfare Check-in Reminder</h2>
                <small id="monitoringReminderSubheading" class="font-medium text-gray-500"></small>
            </div>
            <form id="monitoringReminderForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium leading-relaxed text-emerald-800">
                        The counter is updated only after the email is delivered. A check-in is automatically flagged after its second successful reminder.
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="monitoringCustomMessage">Custom Message (Optional)</label>
                        <textarea id="monitoringCustomMessage" name="custom_message" class="form-control remarks-textarea" rows="3" maxlength="1000" placeholder="Add an optional shelter note..."></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('monitoringReminderModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reminder</button>
                </div>
            </form>
        </div>
    </div>

    <div class="custom-modal-backdrop" id="monitoringFlagModal" role="dialog" aria-modal="true" aria-labelledby="monitoringFlagTitle">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h2 id="monitoringFlagTitle">Flag Post-Adoption Case</h2>
                <small id="monitoringFlagSubheading" class="font-medium text-gray-500"></small>
            </div>
            <form id="monitoringFlagForm" method="POST">
                @csrf
                <div class="custom-modal-body">
                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium leading-relaxed text-amber-900">
                        The reason is retained with the case and recorded in the administrative audit trail.
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="monitoringFlagReason">Reason for Flagging</label>
                        <textarea id="monitoringFlagReason" name="reason" class="form-control remarks-textarea" rows="3" required maxlength="2000" placeholder="Describe the welfare or follow-up concern..."></textarea>
                    </div>
                </div>
                <div class="custom-modal-footer-1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('monitoringFlagModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Flag Case</button>
                </div>
            </form>
        </div>
    </div>

    <div class="custom-modal-backdrop" id="monitoringViewModal" role="dialog" aria-modal="true" aria-labelledby="monitoringViewTitle">
        <div class="custom-modal custom-modal-wide">
            <div class="custom-modal-header">
                <h2 id="monitoringViewTitle">Post-Adoption Monitoring Report</h2>
                <small id="monitoringViewSubheading" class="font-medium text-gray-500"></small>
            </div>
            <div class="custom-modal-body space-y-3">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div>
                        <span class="block text-xs font-bold uppercase tracking-wide text-gray-500">Status</span>
                        <span id="monitoringViewStatus" class="badge mt-1"></span>
                    </div>
                    <div class="text-right text-xs text-gray-600">
                        <div>Due: <strong id="monitoringViewDue"></strong></div>
                        <div>Submitted: <strong id="monitoringViewSubmitted"></strong></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-lg border border-cream-200 bg-cream-50 p-3">
                        <span class="block text-xs font-bold text-gray-500">Pet Status</span>
                        <strong id="monitoringViewHealth" class="text-gray-800"></strong>
                    </div>
                    <div class="rounded-lg border border-cream-200 bg-cream-50 p-3">
                        <span class="block text-xs font-bold text-gray-500">Veterinary Notes</span>
                        <strong id="monitoringViewVet" class="text-gray-800"></strong>
                    </div>
                </div>

                <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-3 text-sm">
                    <div><span class="block text-xs font-bold text-gray-500">Eating Habits</span><p id="monitoringViewEating" class="mt-0.5 text-xs text-gray-700"></p></div>
                    <div><span class="block text-xs font-bold text-gray-500">Behavioral Observations</span><p id="monitoringViewBehavior" class="mt-0.5 text-xs text-gray-700"></p></div>
                    <div><span class="block text-xs font-bold text-gray-500">Living Conditions</span><p id="monitoringViewLiving" class="mt-0.5 text-xs text-gray-700"></p></div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                    <span class="block text-xs font-bold text-amber-800">Reported Concerns</span>
                    <p id="monitoringViewConcerns" class="mt-0.5 text-xs leading-relaxed text-amber-900"></p>
                    <span class="mt-3 block text-xs font-bold text-amber-800">Flag Reasons</span>
                    <p id="monitoringViewFlagReasons" class="mt-0.5 text-xs leading-relaxed text-amber-900"></p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700">
                    <strong class="block text-gray-800">Capture verification</strong>
                    <span class="block">Method: <span id="monitoringViewVerificationMethod"></span></span>
                    <span class="block break-all">Challenge: <span id="monitoringViewChallenge"></span></span>
                    <span class="block">Challenge issued: <span id="monitoringViewChallengeIssued"></span></span>
                    <span class="mt-2 block font-semibold text-gray-800">Video evidence</span>
                    <span class="block">Type: <span id="monitoringViewVideoMime"></span></span>
                    <span class="block">Duration check: <span id="monitoringViewDurationStatus"></span></span>
                    <span class="block">Browser duration: <span id="monitoringViewDeclaredDuration"></span></span>
                    <span class="block">Server duration: <span id="monitoringViewVerifiedDuration"></span></span>
                    <span class="block break-all">SHA-256: <span id="monitoringViewVideoHash"></span></span>
                    <div id="monitoringViewLegacyC2pa" class="mt-2 hidden border-t border-gray-200 pt-2">
                        <span class="block font-semibold text-gray-800">Legacy photo C2PA evidence</span>
                        <span class="block">Status: <span id="monitoringViewC2paStatus"></span></span>
                        <span class="block">Reason: <span id="monitoringViewC2paReason"></span></span>
                        <span class="block break-all">Manifest: <span id="monitoringViewManifest"></span></span>
                        <span class="block">Signed: <span id="monitoringViewSigned"></span></span>
                    </div>
                </div>

                <div id="monitoringViewVideoContainer" class="hidden rounded-lg border border-gray-200 bg-black p-2">
                    <video id="monitoringViewVideo" class="max-h-[420px] w-full rounded" controls playsinline preload="metadata"></video>
                    <a id="monitoringViewVideoLink" class="btn btn-secondary mt-2 flex w-full justify-center" href="#" target="_blank" rel="noopener">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Welfare Video
                    </a>
                </div>

                <a id="monitoringViewPhoto" class="btn btn-secondary hidden w-full justify-center" href="#" target="_blank" rel="noopener">
                    <i class="fa-solid fa-camera"></i> View Legacy Welfare Photo
                </a>
            </div>
            <div class="custom-modal-footer-1">
                <button type="button" class="btn btn-secondary" onclick="closeMonitoringViewModal()">Close</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function openMonitoringReminderModal(button) {
            document.getElementById('monitoringReminderSubheading').textContent = button.dataset.summary || '';
            document.getElementById('monitoringReminderForm').action = button.dataset.action;
            document.getElementById('monitoringCustomMessage').value = '';
            openModal('monitoringReminderModal');
        }

        function openMonitoringFlagModal(button) {
            document.getElementById('monitoringFlagSubheading').textContent = button.dataset.summary || '';
            document.getElementById('monitoringFlagForm').action = button.dataset.action;
            document.getElementById('monitoringFlagReason').value = '';
            openModal('monitoringFlagModal');
        }

        function setMonitoringText(id, value) {
            document.getElementById(id).textContent = value || 'Not reported';
        }

        function openMonitoringViewModal(button) {
            let data = {};

            try {
                data = JSON.parse(button.dataset.report || '{}');
            } catch (error) {
                window.PAIRfectAdmin?.showToast('The monitoring report could not be displayed.', 'error');
                return;
            }

            setMonitoringText('monitoringViewSubheading', `${data.adopter} \u00b7 ${data.pet} \u00b7 ${data.milestone}`);
            setMonitoringText('monitoringViewDue', data.due_date);
            setMonitoringText('monitoringViewSubmitted', data.submitted_date);
            setMonitoringText('monitoringViewHealth', data.health);
            setMonitoringText('monitoringViewVet', data.vet);
            setMonitoringText('monitoringViewEating', data.eating);
            setMonitoringText('monitoringViewBehavior', data.behavior);
            setMonitoringText('monitoringViewLiving', data.living);
            setMonitoringText('monitoringViewConcerns', data.concerns);
            setMonitoringText('monitoringViewFlagReasons', data.flag_reasons);
            setMonitoringText('monitoringViewVerificationMethod', data.verification_method);
            setMonitoringText('monitoringViewChallenge', data.challenge_id);
            setMonitoringText('monitoringViewChallengeIssued', data.challenge_issued_at);
            setMonitoringText('monitoringViewVideoMime', data.video_mime_type);
            setMonitoringText('monitoringViewDurationStatus', formatMonitoringVerificationStatus(data.duration_verification_status));
            setMonitoringText('monitoringViewDeclaredDuration', formatMonitoringDuration(data.declared_duration_ms));
            setMonitoringText('monitoringViewVerifiedDuration', formatMonitoringDuration(data.verified_duration_ms));
            setMonitoringText('monitoringViewVideoHash', data.video_sha256);
            setMonitoringText('monitoringViewC2paStatus', data.legacy_c2pa_status);
            setMonitoringText('monitoringViewC2paReason', data.legacy_c2pa_reason);
            setMonitoringText('monitoringViewManifest', data.legacy_manifest_id);
            setMonitoringText('monitoringViewSigned', data.legacy_signed_at);

            const legacyC2pa = document.getElementById('monitoringViewLegacyC2pa');
            const hasLegacyC2pa = Boolean(data.legacy_c2pa_status || data.legacy_c2pa_reason
                || data.legacy_manifest_id || data.legacy_signed_at);
            legacyC2pa.classList.toggle('hidden', !hasLegacyC2pa);

            const status = document.getElementById('monitoringViewStatus');
            status.textContent = data.status || 'Unknown';
            status.className = `badge badge-${data.status_slug || 'upcoming'} mt-1`;

            const videoContainer = document.getElementById('monitoringViewVideoContainer');
            const video = document.getElementById('monitoringViewVideo');
            const videoLink = document.getElementById('monitoringViewVideoLink');
            video.pause();
            video.removeAttribute('src');
            video.load();
            if (data.video_url) {
                video.src = data.video_url;
                video.load();
                videoLink.href = data.video_url;
                videoContainer.classList.remove('hidden');
            } else {
                videoLink.href = '#';
                videoContainer.classList.add('hidden');
            }

            const photo = document.getElementById('monitoringViewPhoto');
            if (data.photo_url) {
                photo.href = data.photo_url;
                photo.classList.remove('hidden');
                photo.classList.add('flex');
            } else {
                photo.href = '#';
                photo.classList.add('hidden');
                photo.classList.remove('flex');
            }

            openModal('monitoringViewModal');
        }

        function formatMonitoringDuration(milliseconds) {
            const duration = Number(milliseconds);

            return Number.isFinite(duration) && duration > 0
                ? `${(duration / 1000).toFixed(2)} seconds`
                : 'Not available';
        }

        function formatMonitoringVerificationStatus(value) {
            if (!value || value === 'Not available') return 'Not available';

            const label = String(value).replaceAll('_', ' ');

            return label.charAt(0).toUpperCase() + label.slice(1);
        }

        function closeMonitoringViewModal() {
            const video = document.getElementById('monitoringViewVideo');
            video.pause();
            closeModal('monitoringViewModal');
        }

        document.getElementById('monitoringViewModal')?.addEventListener('click', (event) => {
            if (event.target === event.currentTarget) {
                document.getElementById('monitoringViewVideo').pause();
            }
        });
    </script>
@endpush
