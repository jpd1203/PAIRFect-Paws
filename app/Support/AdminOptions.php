<?php

namespace App\Support;

class AdminOptions
{
    public const SPECIES = ['Dog', 'Cat'];
    public const AGE_GROUPS = ['Baby', 'Young', 'Adult', 'Senior'];
    public const HEALTH_STATUSES = ['Healthy', 'Under Care', 'Critical'];
    public const ADOPTION_STATUSES = ['Assessing', 'Available', 'Processing', 'Adopted'];
    public const SIZES = ['Small', 'Medium', 'Large'];

    public const INTERVENTION_TYPES = [
        'Phone Call Follow-up',
        'Email Reminder',
        'In-Person Home Visit',
        'Case Escalation to Supervisor',
    ];
}
