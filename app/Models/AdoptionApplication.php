<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Status workflow (matches the admin Application Review pipeline):
 *   Pending -> Scheduled (interview booked) -> Under Review (interview notes
 *   recorded) -> Approved | Rejected
 */
class AdoptionApplication extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 0;
    public const STATUS_SCHEDULED = 1;
    public const STATUS_UNDER_REVIEW = 2;
    public const STATUS_APPROVED = 3;
    public const STATUS_REJECTED = 4;

    protected $fillable = [
        'user_id', 'pet_id', 'first_name', 'last_name', 'email', 'phone_number', 'address',
        'housing_type', 'household_composition', 'monthly_income_range', 'prior_pet_experience',
        'physical_activity_level', 'time_availability', 'document_path',
        'agreed_to_animal_welfare_act', 'status', 'note',
        'interview_date', 'interview_time', 'conducted_by', 'interview_notes',
        'decision_remarks', 'compatibility_result',
    ];

    protected function casts(): array
    {
        return [
            'agreed_to_animal_welfare_act' => 'boolean',
            'status' => 'integer',
            'interview_date' => 'date',
            'compatibility_result' => 'array',
        ];
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function checkIns()
    {
        return $this->hasMany(CheckIn::class, 'application_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ((int) $this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }

    /** Lowercase slug used for data-status filter attributes and badge/filter-btn classes. */
    public function getStatusSlugAttribute(): string
    {
        return match ((int) $this->status) {
            self::STATUS_PENDING => 'pending',
            self::STATUS_SCHEDULED => 'scheduled',
            self::STATUS_UNDER_REVIEW => 'underreview',
            self::STATUS_APPROVED => 'approved',
            self::STATUS_REJECTED => 'rejected',
            default => 'pending',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return 'badge-'.$this->status_slug;
    }

    public function getLastUpdatedAttribute()
    {
        return $this->updated_at;
    }

    public function getSubmittedOnAttribute()
    {
        return $this->created_at;
    }

    public function getInterviewDateDisplayAttribute(): ?string
    {
        return $this->interview_date?->format('F j, Y');
    }

    public function getInterviewTimeDisplayAttribute(): ?string
    {
        if (!$this->interview_time) {
            return null;
        }
        // interview_time is a TIME column; Eloquent gives back a string like "14:30:00"
        return \Carbon\Carbon::parse($this->interview_time)->format('g:i A');
    }
}
