<?php

namespace App\Actions\Files;

use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MoveFileAction
{
    public function execute(User $user, FileItem $file, ?string $targetFolderUuid = null): FileItem
    {
        $targetFolderId = null;

        if ($targetFolderUuid) {
            $folder = Folder::where('uuid', $targetFolderUuid)
                ->where('owner_id', $user->id)
                ->first();

            if (!$folder) {
                throw ValidationException::withMessages([
                    'target_folder' => 'Target destination folder not found.',
                ]);
            }

            $targetFolderId = $folder->id;
        }

        $oldFolderId = $file->folder_id;
        $file->update(['folder_id' => $targetFolderId]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'file.move',
            'target_type' => 'File',
            'target_id' => $file->id,
            'metadata' => [
                'name' => $file->original_name,
                'from_folder' => $oldFolderId,
                'to_folder' => $targetFolderId,
            ],
        ]);

        return $file->fresh();
    }
}
