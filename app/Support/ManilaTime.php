<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

final class ManilaTime
{
    public const DEFAULT_TIMEZONE = 'Asia/Manila';

    public static function timezone(): string
    {
        return (string) config('app.display_timezone', self::DEFAULT_TIMEZONE);
    }

    public static function at(DateTimeInterface $dateTime): CarbonImmutable
    {
        return CarbonImmutable::instance($dateTime)->setTimezone(self::timezone());
    }

    public static function format(DateTimeInterface $dateTime, string $format): string
    {
        return self::at($dateTime)->format($format);
    }

    /**
     * Format persisted ISO/RFC timestamp metadata without trusting legacy data
     * to be present or parseable.
     */
    public static function parseAndFormat(
        mixed $value,
        string $format,
        string $fallback = 'Not available',
    ): string {
        if ($value instanceof DateTimeInterface) {
            return self::format($value, $format);
        }

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        try {
            return self::format(CarbonImmutable::parse($value), $format);
        } catch (Throwable) {
            return $fallback;
        }
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }
}
