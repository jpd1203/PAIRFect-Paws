<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Mail\CheckInReminderMail;
use App\Models\AdoptionApplication;
use App\Models\HandoverNotification;
use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;
use App\Services\InAppNotificationService;
use App\Services\PostAdoptionScheduleService;
use App\Services\PostAdoptionWebDemoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class PostAdoptionWebDemoController extends Controller
{
    public function __construct(
        private readonly PostAdoptionWebDemoService $demo,
        private readonly PostAdoptionScheduleService $schedules,
        private readonly InAppNotificationService $inApp,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureEnabled();

        $applications = $this->eligibleApplications()
            ->with(['user', 'pet'])
            ->orderByDesc('id')
            ->get();
        $selected = null;
        $logs = collect();
        $state = null;

        if ($request->filled('application_id')) {
            $selected = $this->eligibleApplication((int) $request->query('application_id'));
            $this->schedules->ensureForApplication($selected);
            $logs = $selected->postAdoptionLogs()->orderBy('scheduled_date')->get();
            $state = $this->demo->stateFor((int) $selected->id);
        }

        return view('admin.post-adoption-demo.index', compact('applications', 'selected', 'logs', 'state'));
    }

    public function activate(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $validated = $request->validate([
            'application_id' => ['required', 'integer'],
            'mode' => ['required', Rule::in(['date', 'due', 'next', 'overdue', 'all'])],
            'target_date' => ['required_if:mode,date', 'nullable', 'date_format:Y-m-d'],
        ]);
        $application = $this->eligibleApplication((int) $validated['application_id']);
        $this->schedules->ensureForApplication($application);
        $incomplete = $application->postAdoptionLogs()->whereNull('submitted_date')
            ->orderBy('scheduled_date')->get();

        if ($incomplete->isEmpty()) {
            throw ValidationException::withMessages(['mode' => 'This adoption has no incomplete check-ins to demonstrate.']);
        }

        $current = $this->demo->stateFor((int) $application->id)['date'] ?? null;
        $next = $incomplete->first(fn (PostAdoptionLog $log): bool => $current === null
            || $log->scheduled_date->toDateString() > $current);
        $date = match ($validated['mode']) {
            'due' => $incomplete->first()->scheduled_date->toImmutable(),
            'next' => $next?->scheduled_date?->toImmutable(),
            'overdue' => $incomplete->first()->scheduled_date->toImmutable()->addDay(),
            'all' => $incomplete->last()->scheduled_date->toImmutable()->addDay(),
            default => CarbonImmutable::createFromFormat('!Y-m-d', $validated['target_date'], 'Asia/Manila'),
        };

        if (! $date) {
            throw ValidationException::withMessages(['mode' => 'There is no later incomplete milestone. Choose another scenario.']);
        }

        $adoptionDate = $this->schedules->adoptionDate($application);
        if ($date->startOfDay()->lt($adoptionDate) || $date->year > 2100) {
            throw ValidationException::withMessages(['target_date' => 'Choose a date on or after the adoption date and before 2101.']);
        }

        $this->demo->activate($application, $date, (int) $request->user()->id);
        $state = $this->demo->stateFor((int) $application->id);
        foreach ($incomplete as $log) {
            if (! $this->demo->isDue($log)) {
                continue;
            }

            $overdue = $this->demo->isOverdue($log);
            $this->inApp->user(
                $application->user,
                $overdue ? 'demo_checkin_overdue' : 'demo_checkin_due',
                $overdue ? '[Demo] Post-adoption check-in overdue' : '[Demo] Post-adoption check-in due',
                "Presentation scenario for {$application->pet?->name}: {$log->milestone_display} check-in "
                    .($overdue ? 'is overdue.' : 'is due today.'),
                route('monitoring.my-checkins'),
                "demo_checkin:{$state['id']}:{$state['date']}:{$log->id}:".($overdue ? 'overdue' : 'due'),
                'PostAdoptionLog', $log->id,
            );
        }
        AuditLogService::log(
            $request->user()->id,
            'Post-Adoption Web Demo Date Set',
            'AdoptionApplication',
            $application->id,
            'Presentation date '.$date->toDateString().'; official schedule unchanged.'
        );

        return $this->backTo($application->id, 'Presentation date set for this adoption only.');
    }

    public function reminder(Request $request, PostAdoptionLog $log): RedirectResponse
    {
        $this->ensureEnabled();
        $request->validate(['confirm_email' => ['accepted']]);
        $application = $this->eligibleApplication((int) $log->application_id);
        $log->setRelation('adoptionApplication', $application);
        $adopter = $application->user;

        if (! $adopter?->is_active || ! $adopter->email || ! $adopter->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['confirm_email' => 'The selected adopter needs an active account and verified email.']);
        }

        try {
            $result = Cache::lock($this->demo->lockName((int) $log->application_id), 90)->block(5, function () use ($log, $adopter, $request): array {
                if (! $this->demo->canSendReminder($log)) {
                    throw ValidationException::withMessages([
                        'confirm_email' => 'This demo check-in is not due, was submitted, has already had two reminders, or was already reminded on this presentation date.',
                    ]);
                }

                $state = $this->demo->stateFor((int) $log->application_id);
                $reference = $state['id'].'-'.$state['date'];
                // A demo send is intentionally synchronous: only SMTP-accepted mail advances the demo counter.
                Mail::to($adopter)->sendNow(new CheckInReminderMail($log, null, true, $reference));

                $result = $this->demo->recordReminder($log, $state['id'], $state['date']);
                AuditLogService::log(
                    $request->user()->id,
                    'Post-Adoption Demo Reminder Sent',
                    'PostAdoptionLog',
                    $log->id,
                    "Presentation reminder {$result['count']} accepted for delivery; official reminder count unchanged."
                );

                if ($result['flagged']) {
                    $this->inApp->user(
                        $adopter,
                        'demo_checkin_flagged',
                        '[Demo] Post-adoption check-in flagged',
                        'Presentation scenario: this check-in reached two demo reminders without a report.',
                        route('monitoring.flagged-notice'),
                        "demo_checkin_flagged:{$state['id']}:{$log->id}",
                        'PostAdoptionLog', $log->id,
                    );
                    $this->inApp->administrators(
                        'demo_checkin_flagged',
                        '[Demo] Post-adoption case flagged',
                        "Presentation check-in #{$log->id} reached two demo reminders without a report.",
                        route('admin.monitoring.flagged'),
                        "demo_checkin_flagged:{$state['id']}:{$log->id}",
                        'PostAdoptionLog', $log->id,
                    );
                }

                return $result;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('A post-adoption presentation reminder could not be sent.', [
                'post_adoption_log_id' => $log->id,
                'exception' => $exception::class,
            ]);

            return $this->backTo($application->id, 'Demo reminder was not delivered; its counter was not changed.', 'error');
        }

        return $this->backTo(
            $application->id,
            "Demo reminder {$result['count']} was accepted for delivery to {$adopter->email}."
                .($result['flagged'] ? ' The demo case is now flagged for staff review.' : '')
        );
    }

    public function resolve(Request $request, PostAdoptionLog $log): RedirectResponse
    {
        $this->ensureEnabled();
        $validated = $request->validate(['resolution_note' => ['required', 'string', 'max:2000']]);
        $application = $this->eligibleApplication((int) $log->application_id);
        $this->demo->resolve($log);
        AuditLogService::log(
            $request->user()->id,
            'Post-Adoption Demo Flag Resolved',
            'PostAdoptionLog', $log->id,
            trim($validated['resolution_note'])
        );

        return $this->backTo($application->id, 'Demo flag resolved. Official welfare flags were not changed.');
    }

    public function reset(Request $request, AdoptionApplication $application): RedirectResponse
    {
        $this->ensureEnabled();
        $sessionId = $this->demo->stateFor((int) $application->id)['id'] ?? null;
        $this->demo->reset((int) $application->id);
        if (is_string($sessionId)) {
            HandoverNotification::query()
                ->where(function ($query) use ($sessionId): void {
                    $query->where('dedupe_key', 'like', "demo_checkin:{$sessionId}:%")
                        ->orWhere('dedupe_key', 'like', "demo_checkin_flagged:{$sessionId}:%");
                })
                ->whereNull('read_at')
                ->update(['read' => true, 'read_at' => now()]);
        }
        AuditLogService::log(
            $request->user()->id,
            'Post-Adoption Web Demo Reset',
            'AdoptionApplication', $application->id,
            'Presentation state cleared; official dates and reminder records unchanged.'
        );

        return $this->backTo($application->id, 'Presentation state reset for this adoption.');
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->demo->enabled(), 404);
    }

    private function eligibleApplications()
    {
        return AdoptionApplication::query()
            ->where('status', ApplicationStatus::Approved->value)
            ->whereHas('handover', fn ($query) => $query->where('adopter_outcome', 'received'));
    }

    private function eligibleApplication(int $id): AdoptionApplication
    {
        return $this->eligibleApplications()->with(['user', 'pet', 'handover'])->findOrFail($id);
    }

    private function backTo(int $applicationId, string $message, string $type = 'success'): RedirectResponse
    {
        return redirect()->route('admin.post-adoption-demo.index', ['application_id' => $applicationId])
            ->with('toast', ['type' => $type, 'message' => $message]);
    }
}
