<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncChange extends Model
{
    use HasFactory;

    protected $table = 'sync_changes';

    protected $fillable = [
        'user_id',
        'device_id',
        'item_type',
        'item_id',
        'item_uuid',
        'change_type',
        'checksum',
        'version',
        'parent_uuid',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'version' => 'integer',
        'item_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
