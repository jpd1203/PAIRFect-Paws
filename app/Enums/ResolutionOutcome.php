<?php

namespace App\Enums;

enum ResolutionOutcome: string
{
    case Resolved = 'resolved';
    case FollowUpRequired = 'follow_up_required';
    case VeterinaryAttention = 'veterinary_attention';
    case ReturnRecommended = 'return_recommended';
    case PetReturned = 'pet_returned';

    public function label(): string
    {
        return match ($this) {
            self::Resolved => 'Resolved - No Further Action',
            self::FollowUpRequired => 'Follow-up Required',
            self::VeterinaryAttention => 'Veterinary Attention Recommended',
            self::ReturnRecommended => 'Return to Shelter Recommended',
            self::PetReturned => 'Pet Returned to Shelter',
        };
    }
}
