<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\Milestone;
use App\Models\AdoptionApplication;
use App\Models\PostAdoptionLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PostAdoptionScheduleService
{
    public const TIMEZONE = 'Asia/Manila';

    /**
     * Ensure every approved adoption has its three required monitoring logs.
     *
     * @return int Number of newly created logs.
     */
    public function ensureForApprovedApplications(): int
    {
        $created = 0;

        AdoptionApplication::query()
            ->where('status', ApplicationStatus::Approved->value)
            ->orderBy('id')
            ->chunkById(100, function ($applications) use (&$created) {
                foreach ($applications as $application) {
                    $created += $this->ensureForApplication($application)
                        ->filter(fn (PostAdoptionLog $log): bool => $log->wasRecentlyCreated)
                        ->count();
                }
            });

        return $created;
    }

    /**
     * @return Collection<int, PostAdoptionLog>
     */
    public function ensureForApplication(AdoptionApplication $application): Collection
    {
        if ($application->status !== ApplicationStatus::Approved) {
            throw new \InvalidArgumentException('Post-adoption check-ins can only be scheduled for an approved application.');
        }

        $adoptionDate = $this->adoptionDate($application);
        $scheduledDates = [
            Milestone::ThreeDays->value => $this->targetDate($adoptionDate, Milestone::ThreeDays),
            Milestone::ThreeWeeks->value => $this->targetDate($adoptionDate, Milestone::ThreeWeeks),
            Milestone::ThreeMonths->value => $this->targetDate($adoptionDate, Milestone::ThreeMonths),
        ];

        return collect($scheduledDates)->map(function (CarbonImmutable $scheduledDate, string $milestone) use ($application) {
            $log = PostAdoptionLog::firstOrCreate(
                [
                    'application_id' => $application->id,
                    'milestone' => $milestone,
                ],
                [
                    'scheduled_date' => $scheduledDate->toDateString(),
                    'reminders_sent' => 0,
                    'is_flagged' => false,
                    'version' => 1,
                ]
            );

            return $log;
        })->values();
    }

    public function adoptionDate(AdoptionApplication $application): CarbonImmutable
    {
        $anchor = $application->adopted_at
            ?? $application->queue_closed_at
            ?? $application->updated_at
            ?? $application->created_at;

        if ($anchor === null) {
            return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        }

        return CarbonImmutable::instance($anchor)
            ->setTimezone(self::TIMEZONE)
            ->startOfDay();
    }

    public function targetDate(CarbonImmutable $adoptionDate, Milestone $milestone): CarbonImmutable
    {
        return match ($milestone) {
            Milestone::ThreeDays => $adoptionDate->addDays(3),
            Milestone::ThreeWeeks => $adoptionDate->addWeeks(3),
            Milestone::ThreeMonths => $adoptionDate->addMonthsNoOverflow(3),
        };
    }
}
