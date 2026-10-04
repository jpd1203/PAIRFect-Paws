<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\Milestone;
use App\Enums\PetCurrentStatus;
use App\Services\PostAdoptionClock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostAdoptionLog extends Model
{
    protected $fillable = [
        'application_id', 'milestone', 'scheduled_date', 'submitted_date',
        'survey_data',
        'pet_current_status', 'behavioral_observations', 'living_conditions',
        'eating_habits', 'vet_visit_details', 'concerns', 'photo_path',
        'photo_sha256', 'c2pa_manifest_sha256',
        'video_path', 'video_sha256', 'video_mime_type', 'video_duration_ms',
        'is_flagged', 'flag_reasons', 'reminders_sent', 'last_reminder_sent_at', 'resolved_at',
        'resolved_by_user_id', 'resolution_note', 'version',
    ];

    protected function casts(): array
    {
        return [
            'milestone' => Milestone::class,
            'pet_current_status' => PetCurrentStatus::class,
            'survey_data' => 'array',
            'is_flagged' => 'boolean',
            'flag_reasons' => 'array',
            'reminders_sent' => 'integer',
            'video_duration_ms' => 'integer',
            'version' => 'integer',
            'scheduled_date' => 'date',
            'submitted_date' => 'datetime',
            'last_reminder_sent_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // Relationships
    public function adoptionApplication()
    {
        return $this->belongsTo(AdoptionApplication::class, 'application_id');
    }

    /** Only completed handovers enter the active post-adoption workflow. */
    public function scopeAfterCompletedHandover(Builder $query): Builder
    {
        return $query->whereHas('adoptionApplication', fn (Builder $application) => $application
            ->where('status', ApplicationStatus::Approved->value)
            ->whereHas('handover', fn (Builder $handover) => $handover->where('adopter_outcome', 'received')));
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function captureChallenges()
    {
        return $this->hasMany(PostAdoptionCaptureChallenge::class);
    }

    // ─── Accessors for views ────────────────────────

    public function getUserAttribute()
    {
        return $this->adoptionApplication?->user;
    }

    public function getPetAttribute()
    {
        return $this->adoptionApplication?->pet;
    }

    public function getDueDateAttribute()
    {
        return Carbon::parse($this->scheduled_date);
    }

    public function getStatusSlugAttribute(): string
    {
        if ($this->is_flagged && ! $this->resolved_at) {
            return 'flagged';
        }
        if ($this->submitted_date) {
            return 'completed';
        }

        $today = app(PostAdoptionClock::class)->today();
        $dueDate = Carbon::parse($this->scheduled_date->toDateString(), 'Asia/Manila');

        if ($dueDate->lt($today)) {
            return 'overdue';
        }
        if ($today->diffInDays($dueDate) <= 3) {
            return 'pending';
        }

        return 'upcoming';
    }

    public function getStatusDisplayAttribute(): string
    {
        return ucfirst($this->status_slug);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return 'badge-'.$this->status_slug;
    }

    public function getMilestoneDisplayAttribute(): string
    {
        return $this->milestone?->label() ?? 'Check-in';
    }

    // Stubs
    public function getReportAttribute()
    {
        return null;
    }

    public function getFlaggedCaseAttribute()
    {
        return null;
    }
}
