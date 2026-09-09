<?php

namespace App\Services;

use App\Models\FileItem;
use App\Models\Folder;
use App\Models\SyncChange;
use App\Models\User;

class SyncChangeLogger
{
    public function logFileChange(User $user, FileItem $file, string $changeType, ?string $deviceId = null): SyncChange
    {
        $parentUuid = null;
        if ($file->folder_id) {
            $folder = $file->relationLoaded('folder') ? $file->folder : Folder::find($file->folder_id);
            $parentUuid = $folder?->uuid;
        }

        return SyncChange::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'item_type' => 'file',
            'item_id' => $file->id,
            'item_uuid' => $file->uuid,
            'change_type' => $changeType,
            'checksum' => $file->checksum,
            'version' => $file->version,
            'parent_uuid' => $parentUuid,
            'metadata' => [
                'name' => $file->original_name,
                'extension' => $file->extension,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'updated_at' => $file->updated_at?->toISOString() ?? now()->toISOString(),
            ],
        ]);
    }

    public function logFolderChange(User $user, Folder $folder, string $changeType, ?string $deviceId = null): SyncChange
    {
        $parentUuid = null;
        if ($folder->parent_id) {
            $parent = $folder->relationLoaded('parent') ? $folder->parent : Folder::find($folder->parent_id);
            $parentUuid = $parent?->uuid;
        }

        return SyncChange::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'item_type' => 'folder',
            'item_id' => $folder->id,
            'item_uuid' => $folder->uuid,
            'change_type' => $changeType,
            'checksum' => null,
            'version' => null,
            'parent_uuid' => $parentUuid,
            'metadata' => [
                'name' => $folder->name,
                'color' => $folder->color,
                'updated_at' => $folder->updated_at?->toISOString() ?? now()->toISOString(),
            ],
        ]);
    }
}
