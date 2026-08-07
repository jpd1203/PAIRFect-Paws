<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available = 'Available';
    case Processing = 'Processing';
    case Adopted = 'Adopted';
}
