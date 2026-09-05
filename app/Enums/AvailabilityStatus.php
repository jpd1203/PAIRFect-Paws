<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available = 'Available';
    case SoftReserved = 'Soft-Reserved';
    case Processing = 'Processing';
    case Adopted = 'Adopted';
    case UnderReview = 'Under Review';
    case OnHold = 'On Hold';
    case Assessing = 'Assessing';
}
