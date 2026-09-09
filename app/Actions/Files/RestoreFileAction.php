<?php

namespace App\Actions\Files;

use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\TrashItem;
use App\Models\User;

class RestoreFileAction
{
    public function execute(User $user, int $fileId): FileItem
    {
        $file = FileItem::onlyTrashed()
            ->where('owner_id', $user->id)
            ->where('id', $fileId)
            ->firstOrFail();

        $file->restore();

        TrashItem::where('user_id', $user->id)
            ->where('item_type', 'file')
            ->where('item_id', $fileId)
            ->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'file.restore',
            'target_type' => 'File',
            'target_id' => $file->id,
            'metadata' => ['name' => $file->original_name],
        ]);

        return $file;
    }
}
