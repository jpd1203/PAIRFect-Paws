<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Mail\WelfareReportReceiptMail;
use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;
use App\Services\EmailNotificationService;
use App\Services\InAppNotificationService;
use App\Services\FlagEvaluationService;
use App\Services\PostAdoptionCaptureChallengeService;
use App\Services\PostAdoptionClock;
use App\Services\PostAdoptionScheduleService;
use App\Services\VideoDurationProbe;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class MonitoringController extends Controller
{
    /**
     * Fields that are safe and useful on adopter-facing monitoring pages.
     * Internal review reasons, audit metadata, media hashes, and capture
     * verification details are deliberately excluded from these projections.
     *
     * @var list<string>
     */
    private const ADOPTER_LOG_COLUMNS = [
        'id',
        'application_id',
        'milestone',
        'scheduled_date',
        'submitted_date',
        'pet_current_status',
        'behavioral_observations',
        'living_conditions',
        'eating_habits',
        'vet_visit_details',
        'concerns',
        'is_flagged',
        'reminders_sent',
        'last_reminder_sent_at',
        'resolved_at',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private readonly FlagEvaluationService $flagService,
        private readonly PostAdoptionClock $clock,
        private readonly PostAdoptionCaptureChallengeService $captureChallenges,
        private readonly EmailNotificationService $emailNotifications,
        private readonly VideoDurationProbe $videoDurationProbe,
        private readonly InAppNotificationService $inApp,
    ) {}

    public function myCheckins(Request $request): View
    {
        $logs = PostAdoptionLog::query()
            ->afterCompletedHandover()
            ->with(['adoptionApplication.pet'])
            ->whereHas('adoptionApplication', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->where('status', ApplicationStatus::Approved->value);
            })
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get()
            ->each(function (PostAdoptionLog $log): void {
                $log->setAttribute('display_status', $this->computeDisplayStatus($log));
            });

        $groupedLogs = $logs->groupBy('application_id');

        return view('monitoring.my-checkins', compact('groupedLogs', 'logs'));
    }

    /**
     * Show every currently open report for the adopter. A report becomes
     * available on its scheduled date and remains here until submitted.
     */
    public function reportDue(Request $request): View
    {
        $today = $this->clock->today()->toDateString();
        $logs = $this->ownedApprovedLogs($request)
            ->whereNull('submitted_date')
            ->whereDate('scheduled_date', '<=', $today)
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get()
            ->each(fn (PostAdoptionLog $log) => $this->setDisplayStatus($log));

        return view('monitoring.report-due', compact('logs'));
    }

    /** Show incomplete check-ins whose scheduled date has already passed. */
    public function overdueNotice(Request $request): View
    {
        $today = $this->clock->today()->toDateString();
        $overdueLogs = $this->ownedApprovedLogs($request)
            ->whereNull('submitted_date')
            ->whereDate('scheduled_date', '<', $today)
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get()
            ->each(fn (PostAdoptionLog $log) => $this->setDisplayStatus($log));

        return view('monitoring.overdue-notice', compact('overdueLogs'));
    }

    /**
     * Show only the adopter's unresolved welfare-review notices. The generic
     * notice state is exposed, but staff-only flag reasons are not selected.
     */
    public function flaggedNotice(Request $request): View
    {
        $flaggedLogs = $this->ownedApprovedLogs($request)
            ->where('is_flagged', true)
            ->whereNull('resolved_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->each(fn (PostAdoptionLog $log) => $this->setDisplayStatus($log));

        return view('monitoring.flagged-notice', compact('flaggedLogs'));
    }

    public function showModal(Request $request, PostAdoptionLog $log): View
    {
        $this->authorizeOwnedApprovedLog($request, $log);

        abort_if(
            $log->submitted_date === null,
            422,
            'This report has not been submitted yet.'
        );

        return view('monitoring._report-view-modal-content', ['report' => $log]);
    }

    public function createReport(Request $request, PostAdoptionLog $log): View
    {
        $this->authorizeOwnedApprovedLog($request, $log);
        $this->ensureLogIsDue($log);

        abort_if(
            $log->submitted_date !== null,
            422,
            'This check-in has already been submitted.'
        );

        return view('monitoring.create', compact('log'));
    }

    public function issueCaptureChallenge(Request $request, PostAdoptionLog $log): JsonResponse
    {
        $this->authorizeOwnedApprovedLog($request, $log);
        $this->ensureLogIsDue($log);

        abort_if(
            $log->submitted_date !== null,
            422,
            'This check-in has already been submitted.',
        );

        $issued = $this->captureChallenges->issue(
            $log,
            $request->user(),
            $this->captureSessionBinding($request),
        );

        return response()->json([
            'challenge_id' => $issued['record']->id,
            'challenge_token' => $issued['token'],
            'expires_at' => $issued['record']->expires_at->toIso8601String(),
            'expires_in_seconds' => $issued['expires_in_seconds'],
            'required_duration_seconds' => (float) config(
                'post_adoption.capture.required_video_duration_seconds',
                3,
            ),
            'duration_tolerance_seconds' => (float) config(
                'post_adoption.capture.video_duration_tolerance_seconds',
                0.75,
            ),
        ], 201)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    public function submitReport(
        Request $request,
        PostAdoptionLog $log,
    ): JsonResponse|RedirectResponse {
        $this->authorizeOwnedApprovedLog($request, $log);
        $this->ensureLogIsDue($log);

        if ($log->submitted_date !== null) {
            throw ValidationException::withMessages([
                'report' => 'This check-in has already been submitted.',
            ]);
        }

        $maximumVideoKilobytes = max(
            1,
            (int) config('post_adoption.maximum_video_kilobytes', 12_288)
        );
        $requiredDurationMs = max(
            1,
            (int) round((float) config('post_adoption.capture.required_video_duration_seconds', 3) * 1000),
        );
        $durationToleranceMs = max(
            0,
            (int) round((float) config('post_adoption.capture.video_duration_tolerance_seconds', 0.75) * 1000),
        );
        $minimumDurationMs = max(1, $requiredDurationMs - $durationToleranceMs);
        $maximumDurationMs = $requiredDurationMs + $durationToleranceMs;

        $validated = $request->validate([
            // Keep these as bounded free-text answers. The form offers clear
            // choices, but the stored survey must also support a descriptive
            // welfare response so the keyword flagging rules can evaluate it.
            'pet_current_status' => ['required', 'string', 'max:2000'],
            'behavioral_observations' => ['required', 'string', 'max:2000'],
            'living_conditions' => ['required', 'string', 'max:2000'],
            'eating_habits' => ['required', 'string', 'max:2000'],
            'vet_visit_details' => ['nullable', 'string', 'max:2000'],
            'concerns' => ['nullable', 'string', 'max:2000'],
            'camera_captured_at' => ['required', 'date'],
            'recording_duration_ms' => [
                'required',
                'integer',
                "min:{$minimumDurationMs}",
                "max:{$maximumDurationMs}",
            ],
            'capture_challenge_id' => ['required', 'uuid'],
            'capture_challenge_token' => ['required', 'string', 'size:64'],
            'video' => [
                'required',
                'file',
                'mimetypes:video/webm,video/mp4',
                "max:{$maximumVideoKilobytes}",
            ],
        ]);

        $capturedAt = CarbonImmutable::parse($validated['camera_captured_at'])->utc();
        $now = CarbonImmutable::now('UTC');
        $maximumAgeSeconds = max(
            1,
            (int) config('post_adoption.capture.maximum_capture_age_seconds', 300),
        );
        $futureSkew = max(
            0,
            (int) config('post_adoption.capture.client_clock_skew_seconds', 120),
        );

        if (
            $capturedAt->lt($now->subSeconds($maximumAgeSeconds))
            || $capturedAt->gt($now->addSeconds($futureSkew))
        ) {
            throw ValidationException::withMessages([
                'camera_captured_at' => 'The camera capture expired. Record a new live video and submit it right away.',
            ]);
        }

        $video = $request->file('video');
        $temporaryPath = $video?->getRealPath();

        if (! is_string($temporaryPath) || $temporaryPath === '') {
            throw ValidationException::withMessages([
                'video' => 'The recorded video could not be read. Please record a new video.',
            ]);
        }

        $sessionBinding = $this->captureSessionBinding($request);
        $challenge = $this->captureChallenges->validate(
            $validated['capture_challenge_id'],
            $validated['capture_challenge_token'],
            $log,
            $request->user(),
            $sessionBinding,
            $capturedAt,
        );

        $disk = $this->privateVideoDisk();
        $videoMimeType = $video?->getMimeType();

        if (! is_string($videoMimeType) || ! in_array($videoMimeType, ['video/webm', 'video/mp4'], true)) {
            throw ValidationException::withMessages([
                'video' => 'The recording must be a WebM or MP4 video created by the live camera.',
            ]);
        }

        $durationInspection = $this->videoDurationProbe->inspect($temporaryPath, $videoMimeType);

        if ($durationInspection['status'] === 'probe_unavailable') {
            Log::error('Post-adoption video submission blocked: ffprobe is unavailable.', [
                'post_adoption_log_id' => $log->id,
            ]);

            $message = 'Video verification is temporarily unavailable. Please try again later or contact the shelter.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return back()->withInput()->withErrors(['video' => $message]);
        }

        if (in_array($durationInspection['status'], ['invalid_container', 'invalid_video_stream', 'invalid_duration'], true)) {
            throw ValidationException::withMessages([
                'video' => 'The recording could not be verified as a valid live camera video. Please record it again.',
            ]);
        }

        $verifiedDurationMs = $durationInspection['duration_ms'];

        if ($verifiedDurationMs === null) {
            Log::error('Post-adoption video probe returned no verified duration.', [
                'post_adoption_log_id' => $log->id,
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Video verification is temporarily unavailable.'], 503)
                : back()->withInput()->withErrors(['video' => 'Video verification is temporarily unavailable.']);
        }

        if (
            $verifiedDurationMs < $minimumDurationMs || $verifiedDurationMs > $maximumDurationMs
        ) {
            throw ValidationException::withMessages([
                'video' => 'The live camera video must be approximately three seconds long.',
            ]);
        }

        $declaredDurationMs = (int) $validated['recording_duration_ms'];
        $storedDurationMs = $verifiedDurationMs;
        $videoSha256 = hash_file('sha256', $temporaryPath);

        if (! is_string($videoSha256)) {
            Log::error('A welfare video did not produce a replay-protection hash.', [
                'post_adoption_log_id' => $log->id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Video verification is temporarily unavailable. Please try again later or contact the shelter.',
                ], 503);
            }

            return back()->withInput()->withErrors([
                'video' => 'Video verification is temporarily unavailable. Please try again later or contact the shelter.',
            ]);
        }

        if (PostAdoptionLog::query()->where('video_sha256', $videoSha256)->exists()) {
            throw ValidationException::withMessages([
                'video' => 'This video has already been used for a welfare check-in. Record a new live video.',
            ]);
        }

        $extension = match ($videoMimeType) {
            'video/mp4' => 'mp4',
            default => 'webm',
        };
        $directory = "post-adoption-videos/{$log->application_id}";
        $videoPath = $video->storeAs($directory, Str::uuid().'.'.$extension, $disk);

        if (! is_string($videoPath) || $videoPath === '') {
            throw new RuntimeException('The verified welfare video could not be stored.');
        }

        $survey = [
            'pet_current_status' => $validated['pet_current_status'],
            'behavioral_observations' => $validated['behavioral_observations'] ?? null,
            'living_conditions' => $validated['living_conditions'] ?? null,
            'eating_habits' => $validated['eating_habits'] ?? null,
            'vet_visit_details' => $validated['vet_visit_details'] ?? null,
            'concerns' => $validated['concerns'] ?? null,
            '_verification' => [
                'method' => 'server_capture_challenge+video_sha256',
                'capture_challenge_id' => $challenge->id,
                'challenge_issued_at' => $challenge->created_at->toIso8601String(),
                'challenge_expires_at' => $challenge->expires_at->toIso8601String(),
                'camera_captured_at' => $capturedAt->toIso8601String(),
                'server_received_at' => $now->toIso8601String(),
                'video_sha256' => $videoSha256,
                'video_mime_type' => $videoMimeType,
                'required_duration_ms' => $requiredDurationMs,
                'duration_tolerance_ms' => $durationToleranceMs,
                'declared_duration_ms' => $declaredDurationMs,
                'verified_duration_ms' => $verifiedDurationMs,
                'duration_verification_status' => $durationInspection['status'],
            ],
        ];

        try {
            $result = DB::transaction(function () use (
                $request,
                $log,
                $validated,
                $survey,
                $videoPath,
                $videoSha256,
                $videoMimeType,
                $storedDurationMs,
                $sessionBinding,
                $capturedAt,
            ): array {
                $lockedLog = PostAdoptionLog::query()
                    ->with('adoptionApplication')
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $this->authorizeOwnedApprovedLog($request, $lockedLog);
                $this->ensureLogIsDue($lockedLog);

                if ($lockedLog->submitted_date !== null) {
                    throw ValidationException::withMessages([
                        'report' => 'This check-in has already been submitted.',
                    ]);
                }

                $consumedChallenge = $this->captureChallenges->consumeLocked(
                    $validated['capture_challenge_id'],
                    $validated['capture_challenge_token'],
                    $lockedLog,
                    $request->user(),
                    $sessionBinding,
                    $capturedAt,
                    $videoSha256,
                );
                $survey['_verification']['challenge_consumed_at'] =
                    $consumedChallenge->consumed_at->toIso8601String();

                $lockedLog->update([
                    'submitted_date' => now(),
                    'survey_data' => $survey,
                    'pet_current_status' => $validated['pet_current_status'],
                    'behavioral_observations' => $validated['behavioral_observations'] ?? null,
                    'living_conditions' => $validated['living_conditions'] ?? null,
                    'eating_habits' => $validated['eating_habits'] ?? null,
                    'vet_visit_details' => $validated['vet_visit_details'] ?? null,
                    'concerns' => $validated['concerns'] ?? null,
                    'video_path' => $videoPath,
                    'video_sha256' => $videoSha256,
                    'video_mime_type' => $videoMimeType,
                    'video_duration_ms' => $storedDurationMs,
                ]);

                $newFlagReasons = $this->flagService->evaluate($lockedLog);

                AuditLogService::log(
                    $request->user()->id,
                    'Post-Adoption Welfare Report Submitted',
                    'PostAdoptionLog',
                    $lockedLog->id,
                    "Milestone: {$lockedLog->milestone->value}; three-second video capture challenge consumed; SHA-256 replay protection applied."
                );

                return [
                    'log' => $lockedLog->fresh(['adoptionApplication.user', 'adoptionApplication.pet']),
                    'new_flag_reasons' => $newFlagReasons,
                ];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            Storage::disk($disk)->delete($videoPath);

            throw ValidationException::withMessages([
                'video' => 'This video has already been used for a welfare check-in. Record a new live video.',
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($videoPath);
            throw $exception;
        }

        /** @var PostAdoptionLog $log */
        $log = $result['log'];
        $newFlagReasons = $result['new_flag_reasons'];

        $this->inApp->user(
            $request->user(), 'welfare_report_received', 'Post-adoption report received',
            'Your post-adoption check-in report has been received.',
            route('monitoring.my-checkins'), "welfare_report_received:{$log->id}", 'PostAdoptionLog', $log->id,
        );

        try {
            Mail::to($request->user())->queue(new WelfareReportReceiptMail($log));
        } catch (Throwable $exception) {
            Log::error('A welfare report receipt could not be queued.', [
                'post_adoption_log_id' => $log->id,
                'exception' => $exception::class,
            ]);
        }

        if ($newFlagReasons !== []) {
            $this->emailNotifications->staff(
                "Priority welfare review - check-in #{$log->id}",
                'A submitted welfare report needs priority review',
                [
                    "Check-in #{$log->id} for application #{$log->application_id} triggered automated welfare review rules.",
                    'Sign in to the protected monitoring queue to review the report. Survey answers and videos are intentionally not included in email.',
                ],
                'Open Flagged Monitoring',
                route('admin.monitoring.flagged'),
                false,
                'welfare_report_staff_alert',
                $log->id,
            );
        }

        $message = 'Your welfare report has been submitted. Thank you!';
        $redirect = route('monitoring.my-checkins');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect' => $redirect,
            ], 201);
        }

        return redirect()->to($redirect)->with('success', $message);
    }

    private function authorizeOwnedApprovedLog(Request $request, PostAdoptionLog $log): void
    {
        $log->loadMissing(['adoptionApplication.pet']);
        $application = $log->adoptionApplication;

        abort_if(! $application || $application->user_id !== $request->user()->id, 403);
        abort_if(
            $application->status !== ApplicationStatus::Approved,
            422,
            'Post-adoption reports are available only for approved adoptions.'
        );
        abort_unless($application->hasCompletedHandover(), 404);
    }

    /**
     * Base query shared by adopter-facing monitoring notices. Scoping through
     * the approved application relationship prevents account data leakage.
     *
     * @return Builder<PostAdoptionLog>
     */
    private function ownedApprovedLogs(Request $request): Builder
    {
        return PostAdoptionLog::query()
            ->afterCompletedHandover()
            ->select(self::ADOPTER_LOG_COLUMNS)
            ->with(['adoptionApplication.pet'])
            ->whereHas('adoptionApplication', function (Builder $query) use ($request): void {
                $query->where('user_id', $request->user()->id)
                    ->where('status', ApplicationStatus::Approved->value);
            });
    }

    private function setDisplayStatus(PostAdoptionLog $log): PostAdoptionLog
    {
        $log->setAttribute('display_status', $this->computeDisplayStatus($log));

        return $log;
    }

    private function captureSessionBinding(Request $request): string
    {
        $key = 'post_adoption_capture_session_binding';
        $binding = $request->session()->get($key);

        if (! is_string($binding) || strlen($binding) !== 64) {
            $binding = bin2hex(random_bytes(32));
            $request->session()->put($key, $binding);
        }

        return $binding;
    }

    private function computeDisplayStatus(PostAdoptionLog $log): string
    {
        if ($log->submitted_date !== null) {
            return 'Submitted';
        }

        $today = $this->clock->today();
        $scheduledDate = CarbonImmutable::parse(
            $log->scheduled_date->toDateString(),
            PostAdoptionScheduleService::TIMEZONE,
        )->startOfDay();

        if ($scheduledDate->lt($today)) {
            return 'Overdue';
        }

        return $scheduledDate->gt($today) ? 'Upcoming' : 'Pending';
    }

    private function ensureLogIsDue(PostAdoptionLog $log): void
    {
        $today = $this->clock->today();
        $scheduledDate = CarbonImmutable::parse(
            $log->scheduled_date->toDateString(),
            PostAdoptionScheduleService::TIMEZONE,
        )->startOfDay();

        abort_if(
            $scheduledDate->gt($today),
            422,
            'This check-in opens on '.$scheduledDate->format('F j, Y').'.'
        );
    }

    private function privateVideoDisk(): string
    {
        $disk = trim((string) config(
            'post_adoption.video_disk',
            config('post_adoption.photo_disk', 'local'),
        ));
        $configuration = config("filesystems.disks.{$disk}");

        if (
            $disk === ''
            || $disk === 'public'
            || ! is_array($configuration)
            || ($configuration['visibility'] ?? null) === 'public'
        ) {
            Log::critical('Post-adoption welfare video storage is not configured as a private disk.');
            abort(503, 'Private video storage is temporarily unavailable. Please contact the shelter.');
        }

        return $disk;
    }
}
