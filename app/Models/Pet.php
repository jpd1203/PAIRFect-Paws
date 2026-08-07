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
        'health_status', 'behavioral_notes', 'availability_status',
        'branch_id', 'intake_date', 'photo_path', 'is_archived', 'version',
    ];

    protected function casts(): array
    {
        return [
            'species'             => Species::class,
            'availability_status' => AvailabilityStatus::class,
            'is_archived'         => 'boolean',
            'intake_date'         => 'date',
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
}

