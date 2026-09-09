<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageUsage extends Model
{
    protected $table = 'storage_usage';

    protected $fillable = [
        'user_id',
        'total_bytes_used',
        'quota_bytes',
        'images_bytes',
        'videos_bytes',
        'documents_bytes',
        'audio_bytes',
        'archives_bytes',
        'other_bytes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAvailableBytesAttribute(): int
    {
        return max(0, $this->quota_bytes - $this->total_bytes_used);
    }

    public function getUsagePercentageAttribute(): float
    {
        if ($this->quota_bytes === 0) {
            return 100.0;
        }

        return round(($this->total_bytes_used / $this->quota_bytes) * 100, 2);
    }

    public function hasAvailableSpace(int $bytes): bool
    {
        return ($this->total_bytes_used + $bytes) <= $this->quota_bytes;
    }
}
