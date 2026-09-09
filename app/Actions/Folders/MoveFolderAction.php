<?php

namespace App\Actions\Folders;

use App\Models\AuditLog;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class MoveFolderAction
{
    public function __construct(
        protected \App\Services\SyncChangeLogger $syncLogger
    ) {}

    public function execute(User $user, Folder $folder, ?string $targetParentUuid = null): Folder
    {
        $newParentId = null;

        if ($targetParentUuid) {
            $targetParent = Folder::where('uuid', $targetParentUuid)
                ->where('owner_id', $user->id)
                ->first();

            if (!$targetParent) {
                throw ValidationException::withMessages([
                    'target_parent' => 'Target parent folder not found.',
                ]);
            }

            // Cannot move folder into itself
            if ($targetParent->id === $folder->id) {
                throw ValidationException::withMessages([
                    'target_parent' => 'Cannot move a folder into itself.',
                ]);
            }

            // Cannot move folder into its own descendant
            $current = $targetParent;
            while ($current) {
                if ($current->parent_id === $folder->id) {
                    throw ValidationException::withMessages([
                        'target_parent' => 'Cannot move a folder into one of its subfolders.',
                    ]);
                }
                $current = $current->parent;
            }

            $newParentId = $targetParent->id;
        }

        $oldParentId = $folder->parent_id;
        $folder->update(['parent_id' => $newParentId]);

        Cache::forget("user:{$user->uuid}:folders:" . ($oldParentId ?: 'root'));
        Cache::forget("user:{$user->uuid}:folders:" . ($newParentId ?: 'root'));

        $freshFolder = $folder->fresh();
        $this->syncLogger->logFolderChange($user, $freshFolder, 'moved');

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'folder.move',
            'target_type' => 'Folder',
            'target_id' => $folder->id,
            'metadata' => ['from_parent' => $oldParentId, 'to_parent' => $newParentId],
        ]);

        return $freshFolder;
    }
}
