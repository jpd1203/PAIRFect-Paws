<?php

namespace App\Enums;

enum PetCurrentStatus: string
{
    case Good = 'Good';
    case Fair = 'Fair';
    case Poor = 'Poor';
}
