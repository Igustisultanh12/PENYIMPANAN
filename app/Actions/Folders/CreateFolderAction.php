<?php

namespace App\Actions\Folders;

use App\Models\AuditLog;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CreateFolderAction
{
    public function __construct(
        protected \App\Services\SyncChangeLogger $syncLogger
    ) {}

    public function execute(User $user, string $name, ?string $parentUuid = null, string $color = '#3B82F6'): Folder
    {
        $parentId = null;

        if ($parentUuid) {
            $parentFolder = Folder::where('uuid', $parentUuid)
                ->where('owner_id', $user->id)
                ->first();

            if (!$parentFolder) {
                throw ValidationException::withMessages([
                    'parent_folder' => 'Parent folder does not exist or access denied.',
                ]);
            }
            $parentId = $parentFolder->id;
        }

        // Prevent duplicate folder names under the same parent
        $exists = Folder::where('owner_id', $user->id)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'A folder with this name already exists in this location.',
            ]);
        }

        $folder = Folder::create([
            'owner_id' => $user->id,
            'parent_id' => $parentId,
            'name' => $name,
            'color' => $color,
        ]);

        // Invalidate folder listing cache
        Cache::forget("user:{$user->uuid}:folders:" . ($parentId ?: 'root'));

        // Change Feed for Desktop Sync
        $this->syncLogger->logFolderChange($user, $folder, 'created');

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'folder.create',
            'target_type' => 'Folder',
            'target_id' => $folder->id,
            'metadata' => ['name' => $name, 'uuid' => $folder->uuid],
        ]);

        return $folder;
    }
}
