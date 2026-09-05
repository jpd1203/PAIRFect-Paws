<?php

namespace App\Enums;

enum Milestone: string
{
    case ThreeDays = '3_days';
    case ThreeWeeks = '3_weeks';
    case ThreeMonths = '3_months';

    public function shortLabel(): string
    {
        return match ($this) {
            self::ThreeDays => '3-Day',
            self::ThreeWeeks => '3-Week',
            self::ThreeMonths => '3-Month',
        };
    }

    public function label(): string
    {
        return $this->shortLabel().' Check-in';
    }
}
