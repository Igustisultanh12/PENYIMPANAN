<?php

namespace App\Actions\Storage;

use App\Models\FileItem;
use App\Models\StorageUsage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CalculateStorageUsageAction
{
    /**
     * Recalculate or atomically update storage usage for a given user.
     */
    public function execute(User $user): StorageUsage
    {
        $usage = StorageUsage::firstOrCreate(
            ['user_id' => $user->id],
            ['quota_bytes' => 10737418240] // 10 GB
        );

        $stats = FileItem::where('owner_id', $user->id)
            ->whereNull('deleted_at')
            ->select('mime_type', 'extension', 'size')
            ->get();

        $totalBytes = 0;
        $imagesBytes = 0;
        $videosBytes = 0;
        $documentsBytes = 0;
        $audioBytes = 0;
        $archivesBytes = 0;
        $otherBytes = 0;

        foreach ($stats as $file) {
            $size = (int) $file->size;
            $totalBytes += $size;
            $category = $file->category;

            match ($category) {
                'image' => $imagesBytes += $size,
                'video' => $videosBytes += $size,
                'document' => $documentsBytes += $size,
                'audio' => $audioBytes += $size,
                'archive' => $archivesBytes += $size,
                default => $otherBytes += $size,
            };
        }

        $usage->update([
            'total_bytes_used' => $totalBytes,
            'images_bytes' => $imagesBytes,
            'videos_bytes' => $videosBytes,
            'documents_bytes' => $documentsBytes,
            'audio_bytes' => $audioBytes,
            'archives_bytes' => $archivesBytes,
            'other_bytes' => $otherBytes,
        ]);

        // Invalidate Redis cache
        Cache::forget("user:{$user->uuid}:storage");

        return $usage;
    }

    /**
     * Atomically increment storage usage on successful upload.
     */
    public function increment(User $user, FileItem $file): StorageUsage
    {
        $usage = StorageUsage::firstOrCreate(
            ['user_id' => $user->id],
            ['quota_bytes' => 10737418240]
        );

        $size = (int) $file->size;
        $category = $file->category;

        $categoryColumn = match ($category) {
            'image' => 'images_bytes',
            'video' => 'videos_bytes',
            'document' => 'documents_bytes',
            'audio' => 'audio_bytes',
            'archive' => 'archives_bytes',
            default => 'other_bytes',
        };

        DB::transaction(function () use ($usage, $size, $categoryColumn) {
            $usage->increment('total_bytes_used', $size);
            $usage->increment($categoryColumn, $size);
        });

        Cache::forget("user:{$user->uuid}:storage");

        return $usage->fresh();
    }

    /**
     * Atomically decrement storage usage on permanent deletion.
     */
    public function decrement(User $user, FileItem $file): StorageUsage
    {
        $usage = StorageUsage::firstOrCreate(
            ['user_id' => $user->id],
            ['quota_bytes' => 10737418240]
        );

        $size = (int) $file->size;
        $category = $file->category;

        $categoryColumn = match ($category) {
            'image' => 'images_bytes',
            'video' => 'videos_bytes',
            'document' => 'documents_bytes',
            'audio' => 'audio_bytes',
            'archive' => 'archives_bytes',
            default => 'other_bytes',
        };

        DB::transaction(function () use ($usage, $size, $categoryColumn) {
            $usage->decrement('total_bytes_used', min($usage->total_bytes_used, $size));
            $usage->decrement($categoryColumn, min($usage->{$categoryColumn}, $size));
        });

        Cache::forget("user:{$user->uuid}:storage");

        return $usage->fresh();
    }
}
