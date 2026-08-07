<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;

class AdoptionApplication extends Model
{
    protected $fillable = [
        'user_id', 'pet_id', 'status', 'motivation_statement',
        'housing_type', 'income_range', 'document_path',
        'interview_date', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'         => ApplicationStatus::class,
            'interview_date' => 'datetime',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function postAdoptionLogs()
    {
        return $this->hasMany(PostAdoptionLog::class);
    }
}
