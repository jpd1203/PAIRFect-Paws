<?php

namespace App\Services;

use App\Models\PostAdoptionLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FlagEvaluationService
{
    /**
     * Evaluate a submitted survey and return the reasons that require staff review.
     *
     * @return list<string>
     */
    public function evaluate(PostAdoptionLog|int $log): array
    {
        $log = $log instanceof PostAdoptionLog ? $log->fresh() : PostAdoptionLog::findOrFail($log);
        $survey = $this->surveyFor($log);
        $reasons = [];

        if (Str::lower((string) ($survey['pet_current_status'] ?? '')) === 'poor') {
            $reasons[] = 'The adopter reported the pet\'s current welfare status as poor.';
        }

        foreach ((array) config('post_adoption.welfare.negative_answers', []) as $field => $answers) {
            $value = data_get($survey, $field);
            if ($value === null) {
                continue;
            }

            $normalizedValue = $this->normalize((string) $value);
            $normalizedAnswers = array_map(fn ($answer) => $this->normalize((string) $answer), (array) $answers);

            if (in_array($normalizedValue, $normalizedAnswers, true)) {
                $reasons[] = sprintf(
                    'The survey answer for %s indicates a possible welfare concern.',
                    str_replace(['.', '_'], ' ', $field)
                );
            }
        }

        $keywords = array_values(array_filter(array_map(
            fn ($keyword) => $this->normalize((string) $keyword),
            (array) config('post_adoption.welfare.negative_keywords', [])
        )));

        foreach (Arr::dot($survey) as $field => $value) {
            if (Str::startsWith((string) $field, '_')) {
                continue;
            }

            if (! is_scalar($value) || is_bool($value)) {
                continue;
            }

            $answer = ' '.$this->normalize((string) $value).' ';
            foreach ($keywords as $keyword) {
                if (str_contains($answer, ' '.$keyword.' ')) {
                    $reasons[] = sprintf(
                        'The %s response contains the welfare warning phrase "%s".',
                        str_replace(['.', '_'], ' ', (string) $field),
                        $keyword
                    );
                }
            }
        }

        $reasons = array_values(array_unique($reasons));
        if ($reasons === []) {
            return [];
        }

        $existingReasons = $this->normalizeFlagReasons($log->flag_reasons);
        $mergedReasons = $this->appendSurveyReasons($existingReasons, $reasons);
        $wasOpen = $log->is_flagged && $log->resolved_at === null;

        $log->update([
            'is_flagged' => true,
            'flag_reasons' => $mergedReasons,
            'resolved_at' => null,
            'resolved_by_user_id' => null,
            'resolution_note' => null,
        ]);

        if (! $wasOpen || count($mergedReasons) > count($existingReasons)) {
            AuditLogService::log(
                null,
                'Post-Adoption Log Flagged',
                'PostAdoptionLog',
                $log->id,
                'Auto-flagged from welfare survey: '.implode(' ', $reasons)
            );
        }

        return $reasons;
    }

    /**
     * Backstop for installations still invoking the legacy missed-submission scan.
     */
    public function flagMissedSubmissions(): int
    {
        $flagged = 0;
        $today = today(PostAdoptionScheduleService::TIMEZONE)->toDateString();

        PostAdoptionLog::whereNull('submitted_date')
            ->whereDate('scheduled_date', '<=', $today)
            ->where('is_flagged', false)
            ->where('reminders_sent', '>=', 2)
            ->eachById(function (PostAdoptionLog $log) use (&$flagged, $today) {
                $reason = 'Two reminders were sent while the check-in remained incomplete.';
                $wasFlagged = DB::transaction(function () use ($log, $today): bool {
                    $lockedLog = PostAdoptionLog::query()->lockForUpdate()->find($log->id);

                    if (
                        ! $lockedLog
                        || $lockedLog->submitted_date !== null
                        || $lockedLog->is_flagged
                        || $lockedLog->reminders_sent < 2
                        || $lockedLog->scheduled_date->toDateString() > $today
                    ) {
                        return false;
                    }

                    $lockedLog->update([
                        'is_flagged' => true,
                        'flag_reasons' => $this->appendMissedCheckInReason(
                            $this->normalizeFlagReasons($lockedLog->flag_reasons),
                            $lockedLog->reminders_sent,
                        ),
                    ]);

                    return true;
                });

                if (! $wasFlagged) {
                    return;
                }

                AuditLogService::log(
                    null,
                    'Post-Adoption Log Flagged (Missed Submission)',
                    'PostAdoptionLog',
                    $log->id,
                    $reason
                );
                $flagged++;
            });

        return $flagged;
    }

    /** @return array<string, mixed> */
    private function surveyFor(PostAdoptionLog $log): array
    {
        if (is_array($log->survey_data) && $log->survey_data !== []) {
            return $log->survey_data;
        }

        return array_filter([
            'pet_current_status' => $log->pet_current_status?->value,
            'behavioral_issues' => $log->behavioral_observations,
            'diet' => $log->eating_habits,
            'living_conditions' => $log->living_conditions,
            'vet_care' => $log->vet_visit_details,
            'concerns' => $log->concerns,
        ], fn ($value) => filled($value));
    }

    private function normalize(string $value): string
    {
        $normalized = Str::lower(Str::ascii($value));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $normalized));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFlagReasons(mixed $reasons): array
    {
        if (! is_array($reasons) || $reasons === []) {
            return [];
        }

        if (! array_is_list($reasons)) {
            $reasons = [$reasons];
        }

        return array_values(array_filter(array_map(function ($reason): ?array {
            if (is_array($reason)) {
                return $reason;
            }

            if (! is_scalar($reason) || trim((string) $reason) === '') {
                return null;
            }

            return [
                'code' => 'legacy_reason',
                'message' => trim((string) $reason),
            ];
        }, $reasons)));
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<string>  $messages
     * @return list<array<string, mixed>>
     */
    private function appendSurveyReasons(array $existing, array $messages): array
    {
        $knownMessages = collect($existing)
            ->pluck('message')
            ->filter(fn ($message): bool => is_string($message))
            ->map(fn (string $message): string => $this->normalize($message))
            ->all();

        foreach ($messages as $message) {
            if (in_array($this->normalize($message), $knownMessages, true)) {
                continue;
            }

            $existing[] = [
                'code' => 'survey_welfare_concern',
                'message' => $message,
                'flagged_at' => now()->toIso8601String(),
            ];
            $knownMessages[] = $this->normalize($message);
        }

        return array_values($existing);
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @return list<array<string, mixed>>
     */
    private function appendMissedCheckInReason(array $existing, int $remindersSent): array
    {
        $alreadyRecorded = collect($existing)->contains(
            fn (array $reason): bool => ($reason['code'] ?? null) === 'missed_check_in'
        );

        if (! $alreadyRecorded) {
            $existing[] = [
                'code' => 'missed_check_in',
                'message' => 'The adopter did not submit this check-in after two reminders.',
                'reminders_sent' => $remindersSent,
                'flagged_at' => now()->toIso8601String(),
            ];
        }

        return array_values($existing);
    }
}
