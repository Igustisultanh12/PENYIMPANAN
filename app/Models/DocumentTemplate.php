<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $table = 'document_templates';

    protected $fillable = [
        'name',
        'type',
        'extension',
        'description',
        'thumbnail_url',
        'storage_path',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];
}
