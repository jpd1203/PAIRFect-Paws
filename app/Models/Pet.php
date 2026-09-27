<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use App\Enums\Species;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    protected $fillable = [
        'name', 'species', 'breed', 'age', 'sex',
        'health_status', 'behavioral_notes', 'description', 'availability_status',
        'branch_id', 'intake_date', 'photo_path', 'is_archived', 'version',
        'energy_level', 'trainability', 'independence', 'temperament',
        'medical_needs', 'is_reactive_to_pets', 'has_aggression_history',
        'last_assessed_at', 'assessment_count', 'physical_size', 'vaccination_record_status', 'last_assessed_by',
    ];

    protected function casts(): array
    {
        return [
            'species' => Species::class,
            'availability_status' => AvailabilityStatus::class,
            'is_archived' => 'boolean',
            'intake_date' => 'date',
            'last_assessed_at' => 'datetime',
            'energy_level' => 'decimal:1',
            'trainability' => 'decimal:1',
            'independence' => 'decimal:1',
            'temperament' => 'decimal:1',
            'medical_needs' => 'decimal:1',
            'is_reactive_to_pets' => 'boolean',
            'has_aggression_history' => 'boolean',
        ];
    }

    /**
     * Boot the model — register the global scope that hides archived pets.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('notArchived', function (Builder $builder) {
            $builder->where('is_archived', false);
        });
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function adoptionApplications()
    {
        return $this->hasMany(AdoptionApplication::class);
    }

    public function assessmentRecords()
    {
        return $this->hasMany(AssessmentRecord::class);
    }

    public function scopeRecommendationEligible(Builder $query): Builder
    {
        return $query
            ->where('availability_status', AvailabilityStatus::Available->value)
            ->has('assessmentRecords', '>=', 3)
            ->whereNotNull('energy_level')
            ->whereNotNull('trainability')
            ->whereNotNull('independence')
            ->whereNotNull('temperament')
            ->whereNotNull('medical_needs')
            ->whereIn('physical_size', ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large']);
    }

    // ─── Accessors used by frontend Blade templates ────────────────────────

    public function getAgeDisplayAttribute(): string
    {
        if (! $this->age) {
            return 'Unknown';
        }
        if ($this->age >= 12) {
            return floor($this->age / 12).' yr'.(floor($this->age / 12) > 1 ? 's' : '');
        }

        return $this->age.' mo';
    }

    public function getAgeGroupAttribute(): string
    {
        if (! $this->age) {
            return 'Unknown';
        }
        if ($this->age <= 6) {
            return 'Baby';
        }
        if ($this->age <= 24) {
            return 'Young';
        }
        if ($this->age <= 84) {
            return 'Adult';
        }

        return 'Senior';
    }

    public function getAgeYearsAttribute(): ?int
    {
        return $this->age ? intdiv($this->age, 12) : null;
    }

    public function getAgeMonthsAttribute(): ?int
    {
        return $this->age ? $this->age % 12 : null;
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_archived) {
            return 'Archived';
        }
        return $this->availability_status?->value ?? 'Available';
    }

    public function getSpeciesDisplayAttribute(): string
    {
        return $this->species?->value ?? 'Unknown';
    }

    public function getSexDisplayAttribute(): string
    {
        return $this->sex ?? 'Unknown';
    }

    public function getHealthStatusClassAttribute(): string
    {
        return match ($this->health_status) {
            'Healthy' => 'active',
            'Needs Vet' => 'pending',
            'Under Treatment' => 'scheduled',
            'Critical' => 'rejected',
            default => 'pending',
        };
    }

    public function getAdoptionStatusClassAttribute(): string
    {
        $status = $this->availability_status?->value ?? 'Available';

        return match ($status) {
            'Available' => 'active',
            'Soft-Reserved' => 'upcoming',
            'Adopted' => 'adopted',
            'Under Review' => 'underreview',
            'On Hold' => 'onhold',
            default => 'pending',
        };
    }

    // Stub accessors for fields the frontend references but that may not exist yet
    public function getNotesAttribute(): ?string
    {
        return $this->behavioral_notes;
    }

    public function getStoryAttribute(): ?string
    {
        return $this->description ?: $this->behavioral_notes;
    }

    public function getAssessmentStatusAttribute(): string
    {
        $count = array_key_exists('assessment_records_count', $this->attributes)
            ? (int) $this->attributes['assessment_records_count']
            : $this->assessmentRecords()->count();

        return $count >= 3 ? 'complete' : 'pending';
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->photo_path) {
            return asset('storage/'.$this->photo_path);
        }

        return asset('images/rcpp-logo.png'); // Fallback image
    }

    public function getCardSubtitleAttribute(): string
    {
        return ($this->breed ?? $this->species->value).' • '.$this->age_display;
    }
}

