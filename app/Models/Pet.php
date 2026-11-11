<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use App\Enums\Species;
use App\Services\PetSizeClassifier;
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
        'aggression_history_verified_at', 'high_vocalization', 'life_stage', 'assessment_scoring_version',
        'last_assessed_at', 'assessment_count', 'physical_size', 'vaccination_record_status', 'last_assessed_by',
        'weight_kg', 'height_cm',
    ];

    protected function casts(): array
    {
        return [
            'species' => Species::class,
            'availability_status' => AvailabilityStatus::class,
            'is_archived' => 'boolean',
            'intake_date' => 'date',
            'weight_kg' => 'decimal:3',
            'height_cm' => 'decimal:3',
            'age' => 'integer',
            'last_assessed_at' => 'datetime',
            'energy_level' => 'float',
            'trainability' => 'float',
            'independence' => 'float',
            'temperament' => 'float',
            'medical_needs' => 'float',
            'high_vocalization' => 'boolean',
            'is_reactive_to_pets' => 'boolean',
            'has_aggression_history' => 'boolean',
            'aggression_history_verified_at' => 'datetime',
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

        static::saving(function (Pet $pet) {
            if (! $pet->exists || $pet->isDirty(['species', 'weight_kg', 'age', 'physical_size'])) {
                $pet->forceFill(app(PetSizeClassifier::class)->changes($pet));
            }
            if ($pet->isDirty('has_aggression_history')) {
                $pet->aggression_history_verified_at = $pet->has_aggression_history === null ? null : now();
            }
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

    public function scopeFullyAssessed(Builder $query): Builder
    {
        return $query
            ->whereNotNull('assessment_scoring_version')
            ->where('assessment_count', '>=', config('matching.min_observers'))
            ->whereNotNull('energy_level')
            ->whereNotNull('trainability')
            ->whereNotNull('independence')
            ->whereNotNull('temperament')
            ->whereIn('pets.id', AssessmentRecord::query()
                ->select('pet_id')
                ->whereNotNull('responses')
                ->whereNotNull('assessor_id')
                ->groupBy('pet_id')
                ->havingRaw('COUNT(DISTINCT assessor_id) >= ?', [config('matching.min_observers')]));
    }

    public function scopeRecommendationEligible(Builder $query): Builder
    {
        return $query
            ->where('availability_status', AvailabilityStatus::Available->value)
            ->whereHas('assessmentRecords', fn (Builder $records) => $records->whereNotNull('responses'))
            ->whereNotNull('medical_needs')
            ->where('size_classification_status', 'classified')
            ->where('size_rule_version', PetSizeClassifier::VERSION)
            ->whereIn('physical_size', ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large'])
            ->whereIn('life_stage', ['young', 'adult', 'senior'])
            ->whereNotNull('has_aggression_history')
            ->whereNotNull('aggression_history_verified_at')
            ->whereNotNull('high_vocalization');
    }

    /** A size filter means current adult classification, not a historical label. */
    public function scopeWithClassifiedSize(Builder $query, string $species, string $size): Builder
    {
        $band = app(PetSizeClassifier::class)->bandsFor($species)[$size] ?? null;
        if ($band === null) {
            return $query->whereRaw('1 = 0');
        }
        foreach (['species' => $species, 'physical_size' => $size,
            'size_classification_status' => 'classified', 'size_rule_version' => PetSizeClassifier::VERSION] as $column => $value) {
            // Exact PHP strings must not become case-insensitive on MySQL.
            $query->where('pets.'.$column, $value)
                ->whereRaw('HEX(pets.'.$column.') = ?', [strtoupper(bin2hex($value))]);
        }
        $query->where('pets.age', '>=', $band['minimum_age_months'])
            ->where('pets.weight_kg', $band['minimum_kg'] === '0' ? '>' : '>=', $band['minimum_kg']);
        if ($band['maximum_kg'] !== null) {
            $query->where('pets.weight_kg', '<', $band['maximum_kg']);
        }

        return $query;
    }

    // ─── Accessors used by frontend Blade templates ────────────────────────

    public function getAgeDisplayAttribute(): string
    {
        if ($this->age === null) {
            return 'Unknown';
        }
        if ($this->age >= 12) {
            return floor($this->age / 12).' yr'.(floor($this->age / 12) > 1 ? 's' : '');
        }

        return $this->age.' mo';
    }

    public function getAgeGroupAttribute(): string
    {
        if ($this->age === null) {
            return 'Unknown';
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
        return $this->age === null ? null : intdiv($this->age, 12);
    }

    public function getAgeMonthsAttribute(): ?int
    {
        return $this->age === null ? null : $this->age % 12;
    }

    public function hasClassifiedPhysicalSize(): bool
    {
        return app(PetSizeClassifier::class)->isCurrent($this);
    }

    public function getSizeDisplayAttribute(): string
    {
        if ($this->hasClassifiedPhysicalSize()) {
            return $this->physical_size;
        }

        return match ($this->size_classification_status) {
            'juvenile' => 'Unavailable: not yet eligible for adult size',
            'age_unknown' => 'Unavailable: age is unknown',
            'weight_unavailable' => 'Unavailable: measured weight is missing or invalid',
            'unsupported_species' => 'Unavailable: unsupported species',
            default => 'Unavailable: classification needs staff review',
        };
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

    public function getWeightDisplayAttribute(): string
    {
        return $this->measurementDisplay($this->weight_kg, 'kg');
    }

    public function getHeightDisplayAttribute(): string
    {
        return $this->measurementDisplay($this->height_cm, 'cm');
    }

    private function measurementDisplay(?string $value, string $unit): string
    {
        return $value === null ? 'Not recorded' : rtrim(rtrim($value, '0'), '.').' '.$unit;
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
            'Returned' => 'onhold',
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
        return $this->assessment_scoring_version !== null
            && $this->assessment_count >= config('matching.min_observers')
            && $this->energy_level !== null && $this->trainability !== null
            && $this->independence !== null && $this->temperament !== null ? 'complete' : 'pending';
    }

    public function getTemperamentDisplayAttribute(): string
    {
        if ($this->assessment_status !== 'complete') {
            return 'Assessment in progress';
        }

        return number_format($this->temperament, 2).' / 5 fearfulness/reactivity';
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
        return $this->species_display.' · '.($this->breed ?? 'Mix').' · '.
        ($this->age_years ? $this->age_years.' yrs' : ($this->age_months ? $this->age_months.' mos' : $this->age_group)).' · '.
        ($this->sex === 'Female' ? 'F' : 'M');
    }
}
