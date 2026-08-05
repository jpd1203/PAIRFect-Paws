<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    use HasFactory;

    public const STATUS_UPCOMING = 0;
    public const STATUS_PENDING = 1;
    public const STATUS_SUBMITTED = 2;
    public const STATUS_OVERDUE = 3;
    public const STATUS_FLAGGED = 4;

    protected $fillable = ['user_id', 'pet_id', 'application_id', 'milestone', 'due_date', 'status'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'status' => 'integer'];
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function application()
    {
        return $this->belongsTo(AdoptionApplication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report()
    {
        return $this->hasOne(PostAdoptionReport::class);
    }

    public function flaggedCase()
    {
        return $this->hasOne(FlaggedCase::class);
    }

    public function getMilestoneDisplayAttribute(): string
    {
        return match ($this->milestone) {
            'ThreeDay' => '3-Day Check-in',
            'ThreeWeek' => '3-Week Check-in',
            'ThreeMonth' => '3-Month Check-in',
            default => $this->milestone,
        };
    }

    public function getMilestoneShortAttribute(): string
    {
        return str_replace(' Check-in', '', $this->milestone_display);
    }

    public function getMilestoneReportLabelAttribute(): string
    {
        return $this->milestone_display;
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ((int) $this->status) {
            self::STATUS_UPCOMING => 'Upcoming',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SUBMITTED => 'Completed',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_FLAGGED => 'Flagged',
            default => 'Unknown',
        };
    }

    /** lowercase slug — matches admin filter-bar data-status values */
    public function getStatusSlugAttribute(): string
    {
        return match ((int) $this->status) {
            self::STATUS_UPCOMING => 'upcoming',
            self::STATUS_PENDING => 'pending',
            self::STATUS_SUBMITTED => 'completed',
            self::STATUS_OVERDUE => 'overdue',
            self::STATUS_FLAGGED => 'flagged',
            default => 'pending',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ((int) $this->status) {
            self::STATUS_UPCOMING => 'badge-upcoming',
            self::STATUS_PENDING => 'badge-pending',
            self::STATUS_SUBMITTED => 'badge-completed',
            self::STATUS_OVERDUE => 'badge-overdue',
            self::STATUS_FLAGGED => 'badge-flagged',
            default => 'badge-pending',
        };
    }

    public function getStatusDotClassAttribute(): string
    {
        return match ((int) $this->status) {
            self::STATUS_SUBMITTED => 'completed',
            self::STATUS_PENDING, self::STATUS_OVERDUE, self::STATUS_FLAGGED => 'pending',
            default => 'upcoming',
        };
    }
}
