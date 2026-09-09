<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileChunk extends Model
{
    protected $fillable = [
        'upload_id',
        'user_id',
        'folder_id',
        'original_name',
        'mime_type',
        'total_size',
        'chunk_index',
        'total_chunks',
        'chunk_size',
        'checksum',
        'storage_path',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'total_chunks' => 'integer',
        'chunk_size' => 'integer',
        'total_size' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
