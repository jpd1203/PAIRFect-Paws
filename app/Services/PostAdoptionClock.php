<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class PostAdoptionClock
{
    public const TIMEZONE = 'Asia/Manila';

    private const CACHE_KEY = 'post-adoption:time-travel-date';

    public function enabled(): bool
    {
        return (bool) config('post_adoption.time_travel.enabled', false);
    }

    public function now(): CarbonImmutable
    {
        $realNow = CarbonImmutable::now(self::TIMEZONE);
        $travelDate = $this->travelDate();

        if ($travelDate === null) {
            return $realNow;
        }

        return $travelDate->setTime(
            $realNow->hour,
            $realNow->minute,
            $realNow->second,
            $realNow->micro,
        );
    }

    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    public function realToday(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    public function travelDate(): ?CarbonImmutable
    {
        if (! $this->enabled()) {
            return null;
        }

        $value = Cache::get(self::CACHE_KEY);

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, self::TIMEZONE)->startOfDay();
        } catch (Throwable) {
            Cache::forget(self::CACHE_KEY);

            return null;
        }

        if ($date->toDateString() !== $value) {
            Cache::forget(self::CACHE_KEY);

            return null;
        }

        return $date;
    }

    public function travelTo(CarbonImmutable $date): void
    {
        if (! $this->enabled()) {
            abort(404);
        }

        Cache::forever(self::CACHE_KEY, $date->setTimezone(self::TIMEZONE)->toDateString());
    }

    public function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function isActive(): bool
    {
        return $this->travelDate() !== null;
    }
}
