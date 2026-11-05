<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandoverTransportIncident extends Model
{
    public const CATEGORIES = [
        'pet_not_received' => 'Pet not received (reported)',
        'delayed_delivery' => 'Delayed delivery',
        'incorrect_destination' => 'Incorrect destination (reported)',
        'suspected_mishandling' => 'Suspected mishandling',
        'injury_or_illness' => 'Animal injury or illness (reported)',
        'animal_distress' => 'Animal distress (reported)',
        'other_transport_concern' => 'Other transportation concern',
    ];

    public const STATUSES = ['open' => 'Open', 'under_investigation' => 'Under investigation', 'resolved' => 'Resolved'];

    public const OUTCOMES = [
        'adopter_confirmed_receipt' => 'Adopter confirmed receipt through the protected process',
        'animal_returned_to_shelter' => 'Animal returned to shelter (staff finding)',
        'delivery_failure_confirmed' => 'Delivery failure confirmed by investigation',
        'other_documented_resolution' => 'Other documented resolution',
    ];

    // All writes go through the locked service, never request mass assignment.
    protected $guarded = ['*'];

    protected $hidden = ['description', 'findings', 'resolution_notes', 'evidence_path', 'evidence_sha256', 'release_context', 'history'];

    protected function casts(): array
    {
        return [
            'release_attempt' => 'integer', 'version' => 'integer',
            'reported_at' => 'datetime', 'next_follow_up_at' => 'datetime',
            'follow_up_notified_at' => 'datetime', 'max_pending_notified_at' => 'datetime', 'resolved_at' => 'datetime',
            'release_context' => 'array', 'history' => 'array',
        ];
    }

    public function handover()
    {
        return $this->belongsTo(Handover::class);
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
