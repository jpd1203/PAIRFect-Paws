<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'password', 'phone_number', 'address', 'role', 'is_active',
    ];

    // Never expose the hashed password or remember token in API/array output
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Laravel auto-hashes on assignment
            'is_active' => 'boolean',
        ];
    }

    public function getAvatarInitialAttribute(): string
    {
        return $this->full_name ? strtoupper(substr($this->full_name, 0, 1)) : '?';
    }

    public function applications()
    {
        return $this->hasMany(AdoptionApplication::class);
    }

    public function checkIns()
    {
        return $this->hasMany(CheckIn::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdopter(): bool
    {
        return $this->role === 'adopter';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['Admin', 'Volunteer'], true);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('role', ['Admin', 'Volunteer']);
    }
}
