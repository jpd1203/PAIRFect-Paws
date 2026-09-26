<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Support\PhilippineAddress;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable;

    /**
     * Keep newly-created in-memory users consistent with the database default.
     * This matters when the same model instance is authenticated immediately
     * after creation, before it has been refreshed from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'is_active',
        'branch_id',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'street_address',
        'zip_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
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

    /** Dispatch the verification notification on its immediate auth channel. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /** Dispatch the password reset notification on its immediate auth channel. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
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

    public function handovers()
    {
        return $this->hasMany(Handover::class);
    }

    public function adopterProfile()
    {
        return $this->hasOne(AdopterProfile::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // ─── Accessors used by frontend Blade templates ────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function getAvatarInitialAttribute(): string
    {
        return strtoupper(substr($this->first_name ?? '?', 0, 1));
    }

    /** @return array<string, string|null> */
    public function getAddressComponentsAttribute(): array
    {
        return PhilippineAddress::fromAttributes($this->attributes);
    }

    public function getAddressAttribute(): ?string
    {
        return PhilippineAddress::format($this->getAddressComponentsAttribute());
    }
}
