<?php

namespace App\Models;

use App\Enums\FileStatus;
use App\Enums\FileVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FileItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'files';

    protected $fillable = [
        'uuid',
        'owner_id',
        'folder_id',
        'original_name',
        'storage_name',
        'mime_type',
        'extension',
        'size',
        'checksum',
        'disk',
        'storage_path',
        'visibility',
        'status',
        'version',
        'is_starred',
    ];

    protected $casts = [
        'status' => FileStatus::class,
        'visibility' => FileVisibility::class,
        'is_starred' => 'boolean',
        'size' => 'integer',
        'version' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($file) {
            if (empty($file->uuid)) {
                $file->uuid = (string) Str::uuid();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class, 'file_id');
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(FileMetadata::class, 'file_id');
    }

    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'shareable');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favorable');
    }

    public function locks(): HasMany
    {
        return $this->hasMany(DocumentLock::class, 'file_id');
    }

    public function activeLock(): HasOne
    {
        return $this->hasOne(DocumentLock::class, 'file_id')
            ->where('expires_at', '>', now());
    }

    public function officeSessions(): HasMany
    {
        return $this->hasMany(OfficeSession::class, 'file_id');
    }

    public function getCategoryAttribute(): string
    {
        $mime = strtolower($this->mime_type ?? '');
        $ext = strtolower($this->extension ?? '');

        if (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'])) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'webm'])) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/') || in_array($ext, ['mp3', 'wav', 'flac', 'ogg', 'm4a'])) {
            return 'audio';
        }
        if (
            str_contains($mime, 'pdf') ||
            str_contains($mime, 'word') ||
            str_contains($mime, 'excel') ||
            str_contains($mime, 'powerpoint') ||
            str_contains($mime, 'document') ||
            str_contains($mime, 'text') ||
            in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md'])
        ) {
            return 'document';
        }
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'])) {
            return 'archive';
        }

        return 'other';
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . ($units[$i] ?? 'B');
    }
}
