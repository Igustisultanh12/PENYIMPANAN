<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorAuth extends Model
{
    protected $table = 'two_factor_authentication';

    protected $fillable = [
        'user_id',
        'secret',
        'is_enabled',
        'confirmed_at',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'is_enabled' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
