<?php

namespace App\Services;

use App\Models\AdoptionApplication;
use App\Models\PostAdoptionLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Short-lived presentation state, isolated from official monitoring records. */
final class PostAdoptionWebDemoService
{
    private const KEY_PREFIX = 'post-adoption:web-demo:';

    public function enabled(): bool
    {
        return (bool) config('post_adoption.web_demo.enabled', false);
    }

    /** @return array<string, mixed>|null */
    public function stateFor(int $applicationId): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $state = Cache::get(self::KEY_PREFIX.$applicationId);

        if (! is_array($state) || ! isset($state['id'], $state['date'], $state['expires_at'])) {
            return null;
        }

        try {
            if (CarbonImmutable::parse($state['expires_at'])->isPast()) {
                Cache::forget(self::KEY_PREFIX.$applicationId);

                return null;
            }

            $date = CarbonImmutable::createFromFormat('!Y-m-d', $state['date'], PostAdoptionClock::TIMEZONE);
            if (! $date || $date->toDateString() !== $state['date']) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $state;
    }

    public function dateFor(PostAdoptionLog $log): CarbonImmutable
    {
        $state = $this->stateFor((int) $log->application_id);

        return $state
            ? CarbonImmutable::parse($state['date'], PostAdoptionClock::TIMEZONE)->startOfDay()
            : app(PostAdoptionClock::class)->today();
    }

    public function isDue(PostAdoptionLog $log): bool
    {
        return $log->scheduled_date->toDateString() <= $this->dateFor($log)->toDateString();
    }

    public function isOverdue(PostAdoptionLog $log): bool
    {
        return $log->scheduled_date->toDateString() < $this->dateFor($log)->toDateString();
    }

    public function reminderCount(PostAdoptionLog $log): int
    {
        $state = $this->stateFor((int) $log->application_id);

        return (int) ($state['logs'][$log->id]['reminders_sent'] ?? 0);
    }

    public function isDemoFlagged(PostAdoptionLog $log): bool
    {
        $state = $this->stateFor((int) $log->application_id);

        return $log->submitted_date === null
            && (bool) ($state['logs'][$log->id]['flagged'] ?? false)
            && ! (bool) ($state['logs'][$log->id]['resolved'] ?? false);
    }

    public function activate(AdoptionApplication $application, CarbonImmutable $date, int $actorId): void
    {
        abort_unless($this->enabled(), 404);
        abort_unless($application->hasCompletedHandover(), 422);

        Cache::lock($this->lockName((int) $application->id), 90)->block(5, function () use ($application, $date, $actorId): void {
            $state = $this->stateFor((int) $application->id);
            $expiresAt = $state['expires_at'] ?? now()->addMinutes((int) config('post_adoption.web_demo.ttl_minutes', 360))->toIso8601String();

            $this->save((int) $application->id, [
                'id' => $state['id'] ?? (string) Str::uuid(),
                'date' => $date->setTimezone(PostAdoptionClock::TIMEZONE)->toDateString(),
                'expires_at' => $expiresAt,
                'actor_id' => $actorId,
                'logs' => $state['logs'] ?? [],
            ]);
        });
    }

    public function reset(int $applicationId): void
    {
        Cache::lock($this->lockName($applicationId), 90)->block(5, fn () => Cache::forget(self::KEY_PREFIX.$applicationId));
    }

    public function canSendReminder(PostAdoptionLog $log): bool
    {
        $state = $this->stateFor((int) $log->application_id);
        $logState = $state['logs'][$log->id] ?? [];

        return $state !== null
            && $log->submitted_date === null
            && $log->adoptionApplication?->hasCompletedHandover()
            && $this->isDue($log)
            && (int) ($logState['reminders_sent'] ?? 0) < 2
            && ($logState['last_reminder_date'] ?? null) !== $state['date'];
    }

    /** @return array{count: int, flagged: bool} */
    public function recordReminder(PostAdoptionLog $log, string $sessionId, string $date): array
    {
        $state = $this->stateFor((int) $log->application_id);
        abort_unless($state && $state['id'] === $sessionId && $state['date'] === $date, 409);
        abort_unless($this->canSendReminder($log), 409);

        $count = $this->reminderCount($log) + 1;
        $state['logs'][$log->id] = [
            'reminders_sent' => $count,
            'last_reminder_date' => $date,
            'flagged' => $count >= 2,
            'resolved' => false,
        ];
        $this->save((int) $log->application_id, $state);

        return ['count' => $count, 'flagged' => $count >= 2];
    }

    public function resolve(PostAdoptionLog $log): void
    {
        Cache::lock($this->lockName((int) $log->application_id), 90)->block(5, function () use ($log): void {
            $state = $this->stateFor((int) $log->application_id);
            abort_unless($state && $this->isDemoFlagged($log), 422);
            $state['logs'][$log->id]['resolved'] = true;
            $this->save((int) $log->application_id, $state);
        });
    }

    public function lockName(int $applicationId): string
    {
        return self::KEY_PREFIX.'lock:'.$applicationId;
    }

    /** @param array<string, mixed> $state */
    private function save(int $applicationId, array $state): void
    {
        $remaining = CarbonImmutable::now()->diffInSeconds(CarbonImmutable::parse($state['expires_at']), false);
        Cache::put(self::KEY_PREFIX.$applicationId, $state, max(1, (int) $remaining));
    }
}
