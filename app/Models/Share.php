<?php

namespace App\Models;

use App\Enums\ShareAccessType;
use App\Enums\SharePermissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Share extends Model
{
    protected $fillable = [
        'uuid',
        'token',
        'shareable_type',
        'shareable_id',
        'owner_id',
        'shared_with_user_id',
        'permission',
        'access_type',
        'target_contact',
        'password',
        'allow_download',
        'expires_at',
    ];

    protected $casts = [
        'permission' => SharePermissionType::class,
        'access_type' => ShareAccessType::class,
        'allow_download' => 'boolean',
        'expires_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $hidden = [
        'password',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($share) {
            if (empty($share->uuid)) {
                $share->uuid = (string) Str::uuid();
            }
            if (empty($share->token)) {
                $share->token = Str::random(40);
            }
        });
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function permissions(): HasOne
    {
        return $this->hasOne(SharePermission::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
