<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentRecord extends Model
{
    protected $fillable = [
        'pet_id',
        'assessor_id',
        'energy_level',
        'trainability',
        'independence',
        'temperament',
        'responses', 'scoring_version',
    ];

    protected function casts(): array
    {
        return [
            'energy_level' => 'float',
            'trainability' => 'float',
            'independence' => 'float',
            'temperament' => 'float',
            'responses' => 'array',
        ];
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }
}
