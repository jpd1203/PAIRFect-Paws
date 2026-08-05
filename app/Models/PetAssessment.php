<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PetAssessment extends Model
{
    use HasFactory;

    public const MAX_ASSESSMENTS_PER_PET = 3;

    protected $fillable = [
        'pet_id', 'assessed_by_id', 'assessed_by_name', 'assessment_number', 'species',
        'energy_level_avg', 'trainability_avg', 'independence_avg', 'temperament_avg', 'answers',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'energy_level_avg' => 'decimal:2',
            'trainability_avg' => 'decimal:2',
            'independence_avg' => 'decimal:2',
            'temperament_avg' => 'decimal:2',
        ];
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function assessedBy()
    {
        return $this->belongsTo(User::class, 'assessed_by_id');
    }

    /** Energy Level: 0-4 avg, higher = more energetic. */
    public static function energyLabel(float $avg): string
    {
        return match (true) {
            $avg > 3 => 'High',
            $avg >= 1.5 => 'Moderate',
            default => 'Low',
        };
    }

    /** Trainability: 0-4 avg, higher = easier to train. */
    public static function trainabilityLabel(float $avg): string
    {
        return match (true) {
            $avg > 3 => 'High',
            $avg >= 2 => 'Moderate',
            default => 'Low',
        };
    }

    /** Independence: 0-4 avg of attachment/attention-seeking items — LOWER = more independent. */
    public static function independenceLabel(float $avg): string
    {
        return match (true) {
            $avg > 3 => 'Very Attached',
            $avg >= 1.5 => 'Balanced',
            default => 'Independent',
        };
    }

    /** Temperament: 0-4 avg of fearfulness/reactivity items — higher = more fearful. */
    public static function temperamentLabel(float $avg): string
    {
        return match (true) {
            $avg > 3 => 'Very Fearful',
            $avg >= 1.5 => 'Somewhat Fearful',
            default => 'Confident',
        };
    }
}
