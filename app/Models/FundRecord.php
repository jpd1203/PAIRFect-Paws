<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundRecord extends Model
{
    use HasFactory;

    protected $fillable = ['recorded_date', 'activity', 'donation_added', 'shelter_spent', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'recorded_date' => 'date',
            'donation_added' => 'decimal:2',
            'shelter_spent' => 'decimal:2',
        ];
    }
}
