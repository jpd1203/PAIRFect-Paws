<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundRecord extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'transaction_type',
        'source_or_destination',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_public' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRecordedDateAttribute()
    {
        return $this->created_at;
    }

    public function getActivityAttribute()
    {
        return $this->source_or_destination;
    }

    public function getDonationAddedAttribute()
    {
        return $this->transaction_type === 'Donation' ? $this->amount : null;
    }

    public function getShelterSpentAttribute()
    {
        return $this->transaction_type === 'Expense' ? $this->amount : null;
    }
}
