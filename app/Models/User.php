<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'is_active',
        'branch_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => Role::class,
            'is_active'         => 'boolean',
        ];
    }

    // Convenience helpers
    public function isAdmin(): bool
    {
        return $this->role === Role::Administrator;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [Role::Administrator, Role::Volunteer]);
    }

    public function isAdopter(): bool
    {
        return $this->role === Role::Adopter;
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function adoptionApplications()
    {
        return $this->hasMany(AdoptionApplication::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}

