<?php

namespace App\Support;

class ReportOptions
{
    public const HEALTH_STATUSES = [
        'Good',
        'Fair',
        'Poor',
    ];

    public const EATING_HABITS = [
        'Normal',
        'Eating Less',
        'Not Eating',
        'Overeating',
    ];

    public const BEHAVIORS = [
        'Normal',
        'Anxious',
        'Aggressive',
        'Lethargic',
        'Hyperactive',
    ];

    public const LIVING_CONDITIONS = [
        'Indoor',
        'Outdoor',
        'Mixed',
        'Other',
    ];
}
