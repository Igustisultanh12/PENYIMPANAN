<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'version_number',
        'storage_name',
        'storage_path',
        'size',
        'checksum',
        'disk',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'size' => 'integer',
        'version_number' => 'integer',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileItem::class, 'file_id');
    }
}
