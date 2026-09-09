<?php

namespace App\Actions\Storage;

use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\User;
use App\Services\Storage\StorageService;

class GenerateDownloadUrlAction
{
    public function __construct(protected StorageService $storageService) {}

    public function execute(?User $user, FileItem $file, int $expirationMinutes = 60): string
    {
        $signedUrl = $this->storageService->getSignedDownloadUrl($file, $expirationMinutes);

        if ($user) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'file.download_request',
                'target_type' => 'File',
                'target_id' => $file->id,
                'metadata' => ['name' => $file->original_name],
            ]);
        }

        return $signedUrl;
    }
}
