<?php

namespace App\Enums;

enum DocumentVerificationStatus: string
{
    case Pending = 'Pending';
    case Verified = 'Verified';
    case NeedsResubmission = 'NeedsResubmission';
    case ManualReview = 'ManualReview';
    case LegacyReview = 'LegacyReview';
}
