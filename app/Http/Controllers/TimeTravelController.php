<?php

namespace App\Http\Controllers;

use App\Models\PostAdoptionLog;
use App\Services\AuditLogService;
use App\Services\PostAdoptionClock;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TimeTravelController extends Controller
{
    public function __construct(
        private readonly PostAdoptionClock $clock,
        private readonly PostAdoptionScheduleService $schedules,
    ) {}

    public function index(): View
    {
        $this->ensureEnabled();
        $this->schedules->ensureForApprovedApplications();

        $today = $this->clock->today();
        $incomplete = PostAdoptionLog::query()->afterCompletedHandover()->whereNull('submitted_date');
        $dueCount = (clone $incomplete)->whereDate('scheduled_date', '<=', $today->toDateString())->count();
        $upcomingCount = (clone $incomplete)->whereDate('scheduled_date', '>', $today->toDateString())->count();
        $nextDate = (clone $incomplete)
            ->whereDate('scheduled_date', '>', $today->toDateString())
            ->min('scheduled_date');
        $lastDate = (clone $incomplete)->max('scheduled_date');

        return view('admin.time-travel.index', [
            'active' => $this->clock->isActive(),
            'effectiveDate' => $today,
            'realDate' => $this->clock->realToday(),
            'dueCount' => $dueCount,
            'upcomingCount' => $upcomingCount,
            'nextDate' => $nextDate,
            'lastDate' => $lastDate,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['date', 'next', 'all'])],
            'target_date' => [
                Rule::requiredIf($request->input('mode') === 'date'),
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        $this->schedules->ensureForApprovedApplications();
        $effectiveToday = $this->clock->today();
        $realToday = $this->clock->realToday();

        $target = match ($validated['mode']) {
            'next' => $this->nextIncompleteDateAfter($effectiveToday),
            'all' => $this->lastIncompleteDate()?->max($realToday),
            default => CarbonImmutable::parse($validated['target_date'], PostAdoptionClock::TIMEZONE)
                ->startOfDay(),
        };

        if ($target === null) {
            throw ValidationException::withMessages([
                'mode' => 'There are no upcoming incomplete check-ins to unlock.',
            ]);
        }

        if ($target->lt($realToday)) {
            throw ValidationException::withMessages([
                'target_date' => 'The temporary test date cannot be earlier than the real date.',
            ]);
        }

        $this->clock->travelTo($target);

        AuditLogService::log(
            $request->user()?->id,
            'Post-Adoption Test Clock Enabled',
            'PostAdoptionClock',
            null,
            'Effective post-adoption date set to '.$target->toDateString().'.'
        );

        return redirect()->route('time-travel.index')->with('toast', [
            'type' => 'success',
            'message' => 'Post-adoption testing date changed to '.$target->format('F j, Y').'.',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $previousDate = $this->clock->travelDate()?->toDateString();
        $this->clock->reset();

        AuditLogService::log(
            $request->user()?->id,
            'Post-Adoption Test Clock Reset',
            'PostAdoptionClock',
            null,
            $previousDate === null
                ? 'The post-adoption test clock was already using real time.'
                : "The temporary date {$previousDate} was cleared."
        );

        return redirect()->route('time-travel.index')->with('toast', [
            'type' => 'success',
            'message' => 'Post-adoption testing has returned to the real date.',
        ]);
    }

    private function nextIncompleteDateAfter(CarbonImmutable $date): ?CarbonImmutable
    {
        $value = PostAdoptionLog::query()
            ->afterCompletedHandover()
            ->whereNull('submitted_date')
            ->whereDate('scheduled_date', '>', $date->toDateString())
            ->min('scheduled_date');

        return $this->parseDatabaseDate($value);
    }

    private function lastIncompleteDate(): ?CarbonImmutable
    {
        return $this->parseDatabaseDate(
            PostAdoptionLog::query()->afterCompletedHandover()->whereNull('submitted_date')->max('scheduled_date')
        );
    }

    private function parseDatabaseDate(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value, PostAdoptionClock::TIMEZONE)->startOfDay()
            : null;
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->clock->enabled(), 404);
    }
}
