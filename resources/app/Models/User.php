<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'contact_number', 'address', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_APPLICANT = 'applicant';

    public const ROLE_BPLO = 'bplo';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TREASURER = 'treasurer';

    public const ROLE_INSPECTOR = 'inspector';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The dashboard each role lands on after login or when visiting /dashboard.
     */
    public function homePath(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => '/admin/dashboard',
            self::ROLE_BPLO => '/bplo/dashboard',
            self::ROLE_TREASURER => '/treasurer/dashboard',
            self::ROLE_INSPECTOR => '/inspector/dashboard',
            default => '/dashboard',
        };
    }

    public function isApplicant(): bool
    {
        return $this->role === self::ROLE_APPLICANT;
    }

    public function isBplo(): bool
    {
        return $this->role === self::ROLE_BPLO;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTreasurer(): bool
    {
        return $this->role === self::ROLE_TREASURER;
    }

    public function isInspector(): bool
    {
        return $this->role === self::ROLE_INSPECTOR;
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
