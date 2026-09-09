<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'whatsapp',
        'password',
        'status',
        'role',
        'avatar_url',
        'email_verified_at',
        'verification_token',
        'verification_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'role' => UserRole::class,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(OAuthAccount::class);
    }

    public function twoFactor(): HasOne
    {
        return $this->hasOne(TwoFactorAuth::class);
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class);
    }

    public function storageUsage(): HasOne
    {
        return $this->hasOne(StorageUsage::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(FileItem::class, 'owner_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class, 'owner_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class, 'owner_id');
    }

    public function sharedWithMe(): HasMany
    {
        return $this->hasMany(Share::class, 'shared_with_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function syncChanges(): HasMany
    {
        return $this->hasMany(SyncChange::class);
    }

    public function documentLocks(): HasMany
    {
        return $this->hasMany(DocumentLock::class);
    }

    public function officeSessions(): HasMany
    {
        return $this->hasMany(OfficeSession::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN || $this->role === UserRole::SUPER_ADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}
