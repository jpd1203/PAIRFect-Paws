<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdopterProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'physical_activity_level', 'time_availability',
        'prior_pet_experience', 'housing_type', 'household_composition',
        'monthly_income_range',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
