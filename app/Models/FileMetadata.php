<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileMetadata extends Model
{
    protected $table = 'file_metadata';

    protected $fillable = [
        'file_id',
        'width',
        'height',
        'duration',
        'thumbnail_path',
        'extra_attributes',
    ];

    protected $casts = [
        'extra_attributes' => 'array',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileItem::class, 'file_id');
    }
}
