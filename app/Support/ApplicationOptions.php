<?php

namespace App\Support;

/**
 * Centralised dropdown option lists for the adoption application form
 * and the pet-recommendation intake form.
 */
class ApplicationOptions
{
    public const PHYSICAL_ACTIVITY_LEVELS = [
        'Mostly Sedentary',
        'Light Activity',
        'Moderately Active',
        'Active',
        'Highly Active',
    ];

    public const TIME_AVAILABILITY_OPTIONS = [
        'Very Limited (0–1 hr/day)',
        'Limited (1–3 hrs/day)',
        'Moderate (3–5 hrs/day)',
        'Available (5–8 hrs/day)',
        'Highly Available (8+ hrs/day)',
    ];

    public const PRIOR_EXPERIENCE_OPTIONS = [
        'No Experience',
        'Beginner',
        'Intermediate',
        'Experienced',
        'Advanced / Expert',
    ];

    public const HOUSING_TYPES = [
        'Apartment',
        'Condominium',
        'House with yard',
    ];

    public const HOUSEHOLD_COMPOSITIONS = [
        'Lives Alone',
        'Couple Only / Roommates',
        'Household with teenager',
        'Household with young children',
        'Household with toddlers/infants',
    ];

    public const INCOME_RANGES = [
        'Below ₱10,000',
        '₱10,000 - ₱25,000',
        '₱25,000 - ₱50,000',
        '₱50,000 - ₱100,000',
        '₱100,000 and above',
    ];

    // Legacy fallback list
    public const OTHER_PETS_OPTIONS = [
        'None',
        'Dogs',
        'Cats',
        'Dogs and Cats',
        'Other Animals',
    ];

    public const HOUSEHOLD_SIZES = [
        '1 Person (Live Alone)',
        '2–3 People',
        '4–5 People',
        '6+ People',
    ];
}
