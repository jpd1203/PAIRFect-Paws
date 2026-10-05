<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\ResolutionOutcome;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\SendCheckInReminder;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\EmailNotificationService;
use App\Services\PostAdoptionClock;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MonitoringController extends Controller
{
    public function __construct(private EmailNotificationService $notifications) {}

    public function index()
    {
        $checkIns = PostAdoptionLog::query()
            ->afterCompletedHandover()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet', 'resolvedBy', 'returnHandledBy'])
            ->orderByRaw('CASE WHEN is_flagged = 1 AND resolved_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get();

        $statusCounts = $checkIns
            ->countBy(fn (PostAdoptionLog $log): string => $log->status_slug)
            ->all();

        $staffHandlers = User::query()->whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
            ->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();

        return view('admin.monitoring.index', compact('checkIns', 'statusCounts', 'staffHandlers'));
    }

    public function flagged()
    {
        $flagged = PostAdoptionLog::query()
            ->afterCompletedHandover()
            ->with(['adoptionApplication.user', 'adoptionApplication.pet', 'resolvedBy', 'returnHandledBy'])
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PostAdoptionLog $log): bool => $log->display_is_flagged);

        $staffHandlers = User::query()->whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
            ->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();

        return view('admin.monitoring.flagged', compact('flagged', 'staffHandlers'));
    }

    /** Stream a submitted welfare photo through a staff-authenticated route. */
    public function photo(PostAdoptionLog $log)
    {
        abort_unless($log->adoptionApplication?->hasCompletedHandover(), 404);
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
        abort_unless($log->adoptionApplication?->hasCompletedHandover(), 404);
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
        abort_unless($log->adoptionApplication?->hasCompletedHandover(), 404);
        $validated = $request->validate([
            'custom_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $log->refresh()->loadMissing('adoptionApplication.user');
        if ($log->adoptionApplication?->hasRecordedReturn()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This pet has been returned; no further check-in reminders may be sent.']);
        }
        $adopter = $log->adoptionApplication?->user;
        $now = app(PostAdoptionClock::class)->now();
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
        abort_unless($log->adoptionApplication?->hasCompletedHandover(), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $now = app(PostAdoptionClock::class)->now();
        $reason = trim($validated['reason']);

        DB::transaction(function () use ($log, $reason, $now, $request): void {
            $lockedLog = PostAdoptionLog::query()->lockForUpdate()->findOrFail($log->id);
            if ($lockedLog->resolution_outcome === ResolutionOutcome::PetReturned) {
                throw ValidationException::withMessages(['reason' => 'A recorded physical return cannot be reopened as a monitoring flag.']);
            }

            $updates = [
                'is_flagged' => true,
                'flag_reasons' => $this->appendFlagReason(
                    $lockedLog->flag_reasons,
                    'manual_staff_flag',
                    $reason,
                    $now,
                    ['flagged_by_user_id' => $request->user()?->id]
                ),
            ];
            if ($lockedLog->resolved_at !== null) {
                $updates += [
                    'resolved_at' => null,
                    'resolved_by_user_id' => null,
                    'resolution_note' => null,
                    'resolution_outcome' => null,
                ];
            }
            $lockedLog->update($updates);
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
        abort_unless($log->adoptionApplication?->hasCompletedHandover(), 404);
        $validated = $request->validate([
            'resolution_outcome' => ['required', Rule::enum(ResolutionOutcome::class)],
            'resolution_note' => ['required', 'string', 'max:2000'],
            'return_date' => ['required_if:resolution_outcome,pet_returned', 'nullable', 'date', 'before_or_equal:today'],
            'return_reason' => ['required_if:resolution_outcome,pet_returned', 'nullable', 'string', 'max:2000'],
            'return_condition' => ['required_if:resolution_outcome,pet_returned', 'nullable', 'string', 'max:2000'],
            'return_handled_by_user_id' => [
                'required_if:resolution_outcome,pet_returned', 'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', [Role::Administrator->value, Role::Volunteer->value])->where('is_active', true)),
            ],
            'confirm_return' => ['required_if:resolution_outcome,pet_returned', 'accepted_if:resolution_outcome,pet_returned'],
        ]);

        $outcome = ResolutionOutcome::from($validated['resolution_outcome']);
        $resolved = DB::transaction(function () use ($log, $validated, $request, $outcome): bool {
            $lockedLog = PostAdoptionLog::query()->lockForUpdate()->findOrFail($log->id);

            if (! $lockedLog->is_flagged || $lockedLog->resolved_at !== null) {
                return false;
            }

            if ($outcome === ResolutionOutcome::PetReturned) {
                $application = AdoptionApplication::query()->lockForUpdate()->findOrFail($lockedLog->application_id);
                $pet = Pet::withoutGlobalScope('notArchived')->lockForUpdate()->findOrFail($application->pet_id);
                if ($application->status !== ApplicationStatus::Approved
                    || ! $application->hasCompletedHandover()
                    || $pet->availability_status !== AvailabilityStatus::Adopted
                    || $application->hasRecordedReturn()) {
                    throw ValidationException::withMessages([
                        'resolution_outcome' => 'This placement cannot be returned again or is no longer an adopted, completed handover.',
                    ]);
                }

                $pet->update(['availability_status' => AvailabilityStatus::Returned]);
                $handover = $application->handover()->lockForUpdate()->firstOrFail();
                $handover->recordHistory('Pet returned to shelter', $request->user()->full_name);
                $handover->save();
            }

            $lockedLog->update([
                'resolution_note' => trim($validated['resolution_note']),
                'resolution_outcome' => $outcome,
                'resolved_at' => $outcome === ResolutionOutcome::FollowUpRequired ? null : now(),
                'resolved_by_user_id' => $outcome === ResolutionOutcome::FollowUpRequired ? null : $request->user()->id,
                'return_date' => $outcome === ResolutionOutcome::PetReturned ? $validated['return_date'] : null,
                'return_reason' => $outcome === ResolutionOutcome::PetReturned ? trim($validated['return_reason']) : null,
                'return_condition' => $outcome === ResolutionOutcome::PetReturned ? trim($validated['return_condition']) : null,
                'return_handled_by_user_id' => $outcome === ResolutionOutcome::PetReturned ? $validated['return_handled_by_user_id'] : null,
            ]);

            AuditLogService::log(
                $request->user()->id,
                match ($outcome) {
                    ResolutionOutcome::FollowUpRequired => 'Post-Adoption Follow-Up Required',
                    ResolutionOutcome::ReturnRecommended => 'Return to Shelter Recommended',
                    ResolutionOutcome::PetReturned => 'Pet Returned to Shelter',
                    default => 'Post-Adoption Flag Resolved',
                },
                'PostAdoptionLog',
                $lockedLog->id,
                'Outcome: '.$outcome->value.'; application #'.$lockedLog->application_id.'.',
            );

            return true;
        });

        if (! $resolved) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This monitoring case does not have an unresolved flag.',
            ]);
        }

        if (in_array($outcome, [ResolutionOutcome::FollowUpRequired, ResolutionOutcome::ReturnRecommended, ResolutionOutcome::PetReturned], true)) {
            $this->notifications->staff(
                "Post-adoption case #{$log->id} requires staff attention",
                match ($outcome) {
                    ResolutionOutcome::FollowUpRequired => 'Post-adoption case requires follow-up',
                    ResolutionOutcome::ReturnRecommended => 'A shelter return was recommended',
                    default => 'A pet return was recorded',
                },
                ['Review the protected monitoring case for the next staff action.'],
                'Open Monitoring',
                route('admin.monitoring.index'),
                $outcome !== ResolutionOutcome::FollowUpRequired,
                'welfare_resolution_staff_alert',
                $log->id,
            );
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $outcome === ResolutionOutcome::FollowUpRequired
                ? 'Follow-up recorded. The case remains open for staff action.'
                : 'The welfare outcome was recorded successfully.',
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
