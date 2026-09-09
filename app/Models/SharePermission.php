<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharePermission extends Model
{
    protected $fillable = [
        'share_id',
        'can_view',
        'can_download',
        'can_edit',
        'can_manage',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_download' => 'boolean',
        'can_edit' => 'boolean',
        'can_manage' => 'boolean',
    ];

    public function share(): BelongsTo
    {
        return $this->belongsTo(Share::class);
    }
}
