<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Pending = 'Pending';
    case UnderReview = 'UnderReview';
    case InterviewScheduled = 'InterviewScheduled';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
}
