<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'entity_name', 'entity_id', 'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

