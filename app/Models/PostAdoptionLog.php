<?php

namespace App\Models;

use App\Enums\Milestone;
use App\Enums\PetCurrentStatus;
use Illuminate\Database\Eloquent\Model;

class PostAdoptionLog extends Model
{
    protected $fillable = [
        'adoption_application_id', 'milestone', 'scheduled_date', 'submitted_date',
        'pet_current_status', 'behavioral_observations', 'living_conditions',
        'eating_habits', 'vet_visit_details', 'concerns', 'photo_path',
        'flagged_for_review', 'reminders_sent', 'resolved_at',
        'resolved_by_user_id', 'resolution_note', 'version',
    ];

    protected function casts(): array
    {
        return [
            'milestone'          => Milestone::class,
            'pet_current_status' => PetCurrentStatus::class,
            'flagged_for_review' => 'boolean',
            'scheduled_date'     => 'date',
            'submitted_date'     => 'datetime',
            'resolved_at'        => 'datetime',
        ];
    }

    // Relationships
    public function adoptionApplication()
    {
        return $this->belongsTo(AdoptionApplication::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
