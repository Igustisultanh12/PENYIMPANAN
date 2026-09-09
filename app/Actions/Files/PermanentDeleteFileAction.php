<?php

namespace App\Actions\Files;

use App\Actions\Storage\CalculateStorageUsageAction;
use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\TrashItem;
use App\Models\User;
use App\Services\Storage\StorageService;

class PermanentDeleteFileAction
{
    public function __construct(
        protected StorageService $storageService,
        protected CalculateStorageUsageAction $storageUsageAction
    ) {}

    public function execute(User $user, int $fileId): void
    {
        $file = FileItem::withTrashed()
            ->where('owner_id', $user->id)
            ->where('id', $fileId)
            ->firstOrFail();

        // 1. Decrement storage usage quota atomically
        $this->storageUsageAction->decrement($user, $file);

        // 2. Delete physical file from storage disk
        $this->storageService->deletePhysicalFile($file->storage_path, $file->disk);

        // 3. Delete versions
        foreach ($file->versions as $version) {
            $this->storageService->deletePhysicalFile($version->storage_path, $version->disk);
        }

        // 4. Delete trash item entry
        TrashItem::where('user_id', $user->id)
            ->where('item_type', 'file')
            ->where('item_id', $fileId)
            ->delete();

        // 5. Force delete DB record
        $file->forceDelete();

        // 6. Audit Log
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'file.permanent_delete',
            'target_type' => 'File',
            'target_id' => $fileId,
            'metadata' => ['name' => $file->original_name],
        ]);
    }
}
