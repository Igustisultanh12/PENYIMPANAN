<?php

namespace App\Actions\Files;

use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\TrashItem;
use App\Models\User;

class DeleteFileAction
{
    public function __construct(
        protected \App\Services\SyncChangeLogger $syncLogger
    ) {}

    public function execute(User $user, FileItem $file): void
    {
        $folderId = $file->folder_id;

        TrashItem::create([
            'user_id' => $user->id,
            'item_type' => 'file',
            'item_id' => $file->id,
            'original_parent_id' => $folderId,
            'deleted_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $file->delete(); // Soft delete

        $this->syncLogger->logFileChange($user, $file, 'deleted');

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'file.trash',
            'target_type' => 'File',
            'target_id' => $file->id,
            'metadata' => ['name' => $file->original_name],
        ]);
    }
}
