<?php

namespace App\Enums;

enum Role: string
{
    case Administrator = 'Administrator';
    case Volunteer = 'Volunteer';
    case Adopter = 'Adopter';
}
