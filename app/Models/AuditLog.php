<?php

namespace App\Models;

use App\Support\ManilaTime;
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

    // ─── Accessors for views ────────────────────────

    public function getTimestampAttribute()
    {
        return $this->created_at ? ManilaTime::at($this->created_at) : null;
    }

    public function getUserNameAttribute(): string
    {
        return $this->user ? $this->user->full_name : 'System';
    }

    public function getRoleAttribute(): string
    {
        return $this->user ? ($this->user->role?->value ?? 'System') : 'System';
    }
}
