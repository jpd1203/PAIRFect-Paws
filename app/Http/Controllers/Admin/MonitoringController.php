<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCheckInReminder;
use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;
use App\Services\EmailNotificationService;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MonitoringController extends Controller
{
    public function __construct(private EmailNotificationService $notifications) {}

    public function index()
    {
        $checkIns = PostAdoptionLog::query()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->orderByRaw('CASE WHEN is_flagged = 1 AND resolved_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get();

        $statusCounts = $checkIns
            ->countBy(fn (PostAdoptionLog $log): string => $log->status_slug)
            ->all();

        return view('admin.monitoring.index', compact('checkIns', 'statusCounts'));
    }

    public function flagged()
    {
        $flagged = PostAdoptionLog::query()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet'])
            ->where('is_flagged', true)
            ->whereNull('resolved_at')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.monitoring.flagged', compact('flagged'));
    }

    /** Stream a submitted welfare photo through a staff-authenticated route. */
    public function photo(PostAdoptionLog $log)
    {
        $path = $log->photo_path;

        abort_unless(
            is_string($path)
                && $path !== ''
                && ! str_contains($path, '..')
                && ! str_starts_with($path, '/')
                && ! preg_match('~^[A-Za-z]:[\\\\/]~', $path),
            404
        );

        $disk = Storage::disk((string) config('post_adoption.photo_disk', 'local'));
        abort_unless($disk->exists($path), 404);

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,8}$/', $extension) ? '.'.$extension : '';

        return $disk->response(
            $path,
            'post-adoption-check-in-'.$log->id.$extension,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline'
        );
    }

    /** Stream a submitted welfare video through a staff-authenticated route. */
    public function video(PostAdoptionLog $log)
    {
        $path = $log->video_path;

        abort_unless($this->isSafePrivatePath($path), 404);

        $mimeType = $log->video_mime_type;
        abort_unless(in_array($mimeType, ['video/webm', 'video/mp4'], true), 404);

        $diskName = trim((string) config(
            'post_adoption.video_disk',
            config('post_adoption.photo_disk', 'local'),
        ));
        $configuration = config("filesystems.disks.{$diskName}");

        abort_if(
            $diskName === ''
                || $diskName === 'public'
                || ! is_array($configuration)
                || ($configuration['visibility'] ?? null) === 'public',
            503,
            'Private video storage is temporarily unavailable.',
        );

        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($path), 404);

        $extension = $mimeType === 'video/mp4' ? 'mp4' : 'webm';

        return $disk->response(
            $path,
            "post-adoption-check-in-{$log->id}.{$extension}",
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    /** Queue one staff-requested reminder; the job records only SMTP-accepted deliveries. */
    public function sendReminder(Request $request, PostAdoptionLog $log)
    {
        $validated = $request->validate([
            'custom_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $log->refresh()->loadMissing('adoptionApplication.user');
        $adopter = $log->adoptionApplication?->user;
        $now = CarbonImmutable::now(PostAdoptionScheduleService::TIMEZONE);
        $scheduledDate = CarbonImmutable::parse(
            $log->scheduled_date->toDateString(),
            PostAdoptionScheduleService::TIMEZONE,
        )->startOfDay();

        if ($log->submitted_date !== null) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This check-in has already been submitted, so no reminder was queued.',
            ]);
        }

        if ($log->reminders_sent >= 2) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This check-in has already received the maximum of two reminders.',
            ]);
        }

        if ($scheduledDate->gt($now->startOfDay())) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This check-in is not due yet, so no reminder was queued.',
            ]);
        }

        if ($log->last_reminder_sent_at?->setTimezone(PostAdoptionScheduleService::TIMEZONE)->isSameDay($now)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'A reminder has already been delivered for this check-in today.',
            ]);
        }

        if (! $adopter?->email || ! $adopter->hasVerifiedEmail()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'The adopter does not have a verified email address, so no reminder was queued.',
            ]);
        }

        $customMessage = filled($validated['custom_message'] ?? null)
            ? trim($validated['custom_message'])
            : null;

        try {
            SendCheckInReminder::dispatch($log->id, $request->user()?->id, $customMessage);
        } catch (Throwable $exception) {
            Log::error('Post-adoption reminder could not be queued.', [
                'post_adoption_log_id' => $log->id,
                'exception' => $exception::class,
            ]);

            return back()->with('toast', [
                'type' => 'error',
                'message' => 'The reminder could not be queued. No reminder was recorded; check the mail and queue configuration, then try again.',
            ]);
        }

        AuditLogService::log(
            $request->user()?->id,
            'Post-Adoption Reminder Queued',
            'PostAdoptionLog',
            $log->id,
            'Staff queued a reminder for reliable background delivery.'
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The reminder was queued. Its counter will update only after the mail relay accepts delivery.',
        ]);
    }

    /** Manually flag a monitoring case and retain the reason in its audit data. */
    public function flag(Request $request, PostAdoptionLog $log)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $now = CarbonImmutable::now(PostAdoptionScheduleService::TIMEZONE);
        $reason = trim($validated['reason']);

        DB::transaction(function () use ($log, $reason, $now, $request): void {
            $lockedLog = PostAdoptionLog::query()->lockForUpdate()->findOrFail($log->id);
            $lockedLog->update([
                'is_flagged' => true,
                'flag_reasons' => $this->appendFlagReason(
                    $lockedLog->flag_reasons,
                    'manual_staff_flag',
                    $reason,
                    $now,
                    ['flagged_by_user_id' => $request->user()?->id]
                ),
                'resolved_at' => null,
                'resolved_by_user_id' => null,
                'resolution_note' => null,
            ]);
        });

        AuditLogService::log(
            $request->user()?->id,
            'Post-Adoption Log Manually Flagged',
            'PostAdoptionLog',
            $log->id,
            $reason
        );

        $this->notifications->staff(
            "Welfare case flagged - check-in #{$log->id}",
            'A post-adoption case was flagged for review',
            [
                "Check-in #{$log->id} was flagged by shelter staff.",
                'Sign in to review the protected case details and resolution history.',
            ],
            'Open Flagged Monitoring',
            route('admin.monitoring.flagged'),
            false,
            'manual_welfare_flag_staff_alert',
            $log->id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The case was flagged for staff review and an alert was queued.',
        ]);
    }

    /** Resolve an open flag while preserving its historical reasons. */
    public function resolve(Request $request, PostAdoptionLog $log)
    {
        $validated = $request->validate([
            'resolution_note' => ['required', 'string', 'max:2000'],
        ]);

        $resolved = DB::transaction(function () use ($log, $validated, $request): bool {
            $lockedLog = PostAdoptionLog::query()->lockForUpdate()->findOrFail($log->id);

            if (! $lockedLog->is_flagged || $lockedLog->resolved_at !== null) {
                return false;
            }

            $lockedLog->update([
                'resolution_note' => trim($validated['resolution_note']),
                'resolved_at' => now(),
                'resolved_by_user_id' => $request->user()?->id,
            ]);

            return true;
        });

        if (! $resolved) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This monitoring case does not have an unresolved flag.',
            ]);
        }

        AuditLogService::log(
            $request->user()?->id,
            'Post-Adoption Flag Resolved',
            'PostAdoptionLog',
            $log->id,
            trim($validated['resolution_note'])
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The flagged case was resolved successfully.',
        ]);
    }

    /**
     * @param  array<mixed>|null  $existing
     * @param  array<string, mixed>  $metadata
     * @return list<mixed>
     */
    private function appendFlagReason(
        ?array $existing,
        string $code,
        string $message,
        CarbonImmutable $now,
        array $metadata = []
    ): array {
        $reasons = $existing ?? [];
        if ($reasons !== [] && ! array_is_list($reasons)) {
            $reasons = [$reasons];
        }

        $reasons[] = array_merge([
            'code' => $code,
            'message' => $message,
            'flagged_at' => $now->toIso8601String(),
        ], $metadata);

        return array_values($reasons);
    }

    private function isSafePrivatePath(mixed $path): bool
    {
        return is_string($path)
            && $path !== ''
            && ! str_contains($path, '..')
            && ! str_starts_with($path, '/')
            && preg_match('~^[A-Za-z]:[\\\\/]~', $path) !== 1;
    }
}
