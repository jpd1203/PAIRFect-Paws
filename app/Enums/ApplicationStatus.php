<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Pending = 'Pending';
    case DocumentFlagged = 'DocumentFlagged';
    case PrimaryCandidate = 'PrimaryCandidate';
    case Waitlisted = 'Waitlisted';
    case UnderReview = 'UnderReview';
    case InterviewScheduled = 'InterviewScheduled';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Withdrawn = 'Withdrawn';
    case NoShow = 'NoShow';
    case Closed = 'Closed';
}
