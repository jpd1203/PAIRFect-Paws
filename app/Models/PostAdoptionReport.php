<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostAdoptionReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_in_id', 'user_id', 'pet_id', 'milestone', 'health_status',
        'eating_and_drinking', 'behavior', 'living_conditions', 'vet_visit',
        'concerns', 'photo_path', 'report_date', 'flagged', 'flag_reason',
    ];

    protected function casts(): array
    {
        return [
            'vet_visit' => 'boolean',
            'flagged' => 'boolean',
            'report_date' => 'date',
        ];
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function checkIn()
    {
        return $this->belongsTo(CheckIn::class);
    }

    public function getVetVisitDisplayAttribute(): string
    {
        return $this->vet_visit ? 'Yes' : 'No';
    }

    public function getMilestoneReportLabelAttribute(): string
    {
        return match ($this->milestone) {
            'ThreeDay' => '3-Day Check-in',
            'ThreeWeek' => '3-Week Check-in',
            'ThreeMonth' => '3-Month Check-in',
            default => $this->milestone,
        };
    }

    public function getHealthBadgeClassAttribute(): string
    {
        return match ($this->health_status) {
            'Excellent' => 'badge-excellent',
            'Good' => 'badge-good',
            'Fair' => 'badge-fair',
            'Poor' => 'badge-poor',
            default => 'badge-pending',
        };
    }
}
