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
    ];

    protected function casts(): array
    {
        return [
            'energy_level' => 'decimal:1',
            'trainability' => 'decimal:1',
            'independence' => 'decimal:1',
            'temperament' => 'decimal:1',
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
