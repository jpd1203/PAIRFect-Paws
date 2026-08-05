<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false; // uses its own `timestamp` column

    protected $fillable = ['user_id', 'user_name', 'role', 'action', 'timestamp'];

    protected function casts(): array
    {
        return ['timestamp' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convenience writer used throughout the admin controllers so every
     * approval / rejection / flag / reminder / escalation / resolution
     * is recorded consistently.
     */
    public static function record(?User $actor, string $action): self
    {
        return self::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->full_name ?? 'System',
            'role' => $actor?->role ?? 'System',
            'action' => $action,
            'timestamp' => now(),
        ]);
    }
}
