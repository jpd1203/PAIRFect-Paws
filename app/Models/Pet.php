<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'species', 'breed', 'age_group', 'age_years', 'sex',
        'intake_date', 'health_status', 'vaccination_records', 'status', 'image_path',
        'energy_level', 'independence_level', 'trainability', 'medical_needs',
        'temperament', 'physical_size', 'assessment_count', 'last_assessed_at',
        'last_assessed_by', 'notes', 'vaccination_record_status',
    ];

    protected function casts(): array
    {
        return [
            'intake_date' => 'date',
            'last_assessed_at' => 'datetime',
        ];
    }

    public function assessments()
    {
        return $this->hasMany(PetAssessment::class)->latest('assessment_number');
    }

    public function applications()
    {
        return $this->hasMany(AdoptionApplication::class);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : asset('images/pet-placeholder.jpg');
    }

    public function getCardSubtitleAttribute(): string
    {
        return "{$this->species} · {$this->age_group} · {$this->sex}";
    }

    public function getSpeciesDisplayAttribute(): string
    {
        return $this->species;
    }

    public function getAgeDisplayAttribute(): string
    {
        return $this->age_group . ($this->age_years ? " ({$this->age_years} yrs)" : '');
    }

    public function getSexDisplayAttribute(): string
    {
        return $this->sex;
    }

    /** admin.css badge-suffix for health status: healthy | undercare | critical */
    public function getHealthStatusClassAttribute(): string
    {
        return match ($this->health_status) {
            'Healthy' => 'healthy',
            'Under Care' => 'undercare',
            'Critical' => 'critical',
            default => 'pending',
        };
    }

    /** admin.css badge-suffix for adoption status: assessing | available | processing | adopted */
    public function getAdoptionStatusClassAttribute(): string
    {
        return match ($this->status) {
            'Assessing' => 'undercare',
            'Available' => 'available',
            'Processing' => 'processing',
            'Adopted' => 'adopted',
            default => 'pending',
        };
    }

    public function getAssessmentStatusAttribute(): string
    {
        return $this->assessment_count >= 3 ? 'complete' : 'pending';
    }

    public function scopeSpecies($query, ?string $species)
    {
        if ($species && $species !== 'All Species') {
            $query->where('species', $species);
        }
        return $query;
    }

    public function scopeAgeGroup($query, ?string $age)
    {
        if ($age && $age !== 'All Ages') {
            $query->where('age_group', $age);
        }
        return $query;
    }

    public function scopeHealthStatus($query, ?string $health)
    {
        if ($health && $health !== 'All Health') {
            $query->where('health_status', $health);
        }
        return $query;
    }

    public function scopeAdoptionStatus($query, ?string $status)
    {
        if ($status && $status !== 'All Status') {
            $query->where('status', $status);
        }
        return $query;
    }
}
