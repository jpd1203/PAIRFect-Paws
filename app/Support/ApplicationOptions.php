<?php

namespace App\Support;

/**
 * Centralised dropdown option lists for the adoption application form
 * and the pet-recommendation intake form (same underlying adopter
 * lifestyle questions, reused in both places).
 */
class ApplicationOptions
{
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
        '₱10,001 - ₱25,000',
        '₱25,001 - ₱50,000',
        '₱50,001 - ₱100,000',
        '₱100,000 and above',
    ];

    public const PRIOR_EXPERIENCE_OPTIONS = [
        'No Experience',
        'Beginner',
        'Intermediate',
        'Experienced',
        'Advanced / Expert',
    ];

    public const PHYSICAL_ACTIVITY_LEVELS = [
        'Very Active',
        'Moderately Active',
        'Lightly Active',
        'Not Very Active',
        'Inactive',
    ];

    public const TIME_AVAILABILITY_OPTIONS = [
        'Very Limited (0–1 hr/day)',
        'Limited (1–3 hrs/day)',
        'Moderate (3–5 hrs/day)',
        'Available (5–8 hrs/day)',
        'Highly Available (8+ hrs/day)',
    ];

    // Legacy simple yes/no dropdown still used on the formal application (other pets in home)
    public const OTHER_PETS_OPTIONS = [
        'None',
        'Yes — spayed/neutered',
        'Yes — not spayed/neutered',
    ];

    public const HOUSEHOLD_SIZES = [
        '1', '2', '3', '4', '5+',
    ];
}
