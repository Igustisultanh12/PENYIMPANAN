<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageDisk extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'config',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
