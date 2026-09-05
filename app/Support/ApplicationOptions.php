<?php

namespace App\Support;

class ApplicationOptions
{
    public const PHYSICAL_ACTIVITY_LEVELS = [
        'Low (Sedentary, short walks)',
        'Moderate (Daily walks, occasional play)',
        'High (Active, jogging, hiking)',
    ];

    public const TIME_AVAILABILITY_OPTIONS = [
        'Less than 2 hours/day',
        '2-4 hours/day',
        '4-8 hours/day',
        'More than 8 hours/day (Work from home / Retired)',
    ];

    public const PRIOR_EXPERIENCE_OPTIONS = [
        'First-time owner',
        'Have owned pets in the past',
        'Currently own pets',
        'Experienced with rescue/special needs animals',
    ];

    public const HOUSING_TYPES = [
        'Apartment / Condo',
        'Townhouse',
        'Single Family Home (No Yard)',
        'Single Family Home (Fenced Yard)',
    ];

    public const HOUSEHOLD_COMPOSITIONS = [
        'Living alone',
        'Living with adults only',
        'Living with children (under 12)',
        'Living with teenagers',
    ];

    public const INCOME_RANGES = [
        'Below ₱15,000',
        '₱15,000 - ₱30,000',
        '₱30,000 - ₱50,000',
        '₱50,000 - ₱80,000',
        'Above ₱80,000',
    ];
}
