<?php

namespace App\Enums;

enum PetCurrentStatus: string
{
    case Excellent = 'Excellent';
    case Good = 'Good';
    case Fair = 'Fair';
    case Poor = 'Poor';
}
