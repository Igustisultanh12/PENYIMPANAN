<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeSession extends Model
{
    use HasFactory;

    protected $table = 'office_sessions';

    protected $fillable = [
        'file_id',
        'user_id',
        'session_token',
        'document_type',
        'mode',
        'draft_content',
        'last_autosave_at',
        'expires_at',
    ];

    protected $casts = [
        'last_autosave_at' => 'datetime',
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
