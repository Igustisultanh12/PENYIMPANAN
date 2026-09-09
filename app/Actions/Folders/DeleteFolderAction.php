<?php

namespace App\Actions\Folders;

use App\Models\AuditLog;
use App\Models\Folder;
use App\Models\TrashItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DeleteFolderAction
{
    public function __construct(
        protected \App\Services\SyncChangeLogger $syncLogger
    ) {}

    public function execute(User $user, Folder $folder): void
    {
        $parentId = $folder->parent_id;

        TrashItem::create([
            'user_id' => $user->id,
            'item_type' => 'folder',
            'item_id' => $folder->id,
            'original_parent_id' => $parentId,
            'deleted_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $folder->delete(); // Soft delete

        Cache::forget("user:{$user->uuid}:folders:" . ($parentId ?: 'root'));

        // Change Feed for Desktop Sync
        $this->syncLogger->logFolderChange($user, $folder, 'deleted');

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'folder.trash',
            'target_type' => 'Folder',
            'target_id' => $folder->id,
            'metadata' => ['name' => $folder->name],
        ]);
    }
}
