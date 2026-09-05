<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdopterProfile extends Model
{
    protected $fillable = [
        'user_id',
        'physical_activity_level',
        'time_availability',
        'prior_pet_experience',
        'housing_type',
        'household_composition',
        'monthly_income_range',
    ];

    /** @return array<string, string> */
    public function knnInputs(): array
    {
        return [
            'physical_activity_level' => (string) $this->physical_activity_level,
            'time_availability' => (string) $this->time_availability,
            'prior_pet_experience' => (string) $this->prior_pet_experience,
            'housing_type' => (string) $this->housing_type,
            'household_composition' => (string) $this->household_composition,
            'monthly_income_range' => (string) $this->monthly_income_range,
            'has_existing_pets' => in_array($this->prior_pet_experience, [
                'Currently own pets',
                'Experienced with rescue/special needs animals',
            ], true) ? 'yes' : 'no',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
