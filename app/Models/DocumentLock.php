<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLock extends Model
{
    use HasFactory;

    protected $table = 'document_locks';

    protected $fillable = [
        'file_id',
        'user_id',
        'device_id',
        'lock_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileItem::class, 'file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return Carbon::now()->greaterThanOrEqualTo($this->expires_at);
    }
}
