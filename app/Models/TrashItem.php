<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrashItem extends Model
{
    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'original_parent_id',
        'deleted_at',
        'expires_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
