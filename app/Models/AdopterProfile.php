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
        'bfi_responses', 'extraversion', 'conscientiousness', 'neuroticism', 'openness',
        'bfi_completed_at', 'has_existing_pets', 'has_children', 'financial_readiness',
    ];

    protected $hidden = ['bfi_responses'];

    protected function casts(): array
    {
        return [
            'bfi_responses' => 'array', 'bfi_completed_at' => 'datetime',
            'extraversion' => 'float', 'conscientiousness' => 'float',
            'neuroticism' => 'float', 'openness' => 'float',
            'has_existing_pets' => 'boolean', 'has_children' => 'boolean',
            'financial_readiness' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
