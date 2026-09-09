<?php

namespace App\Actions\Files;

use App\Actions\Storage\CalculateStorageUsageAction;
use App\Enums\FileStatus;
use App\Enums\FileVisibility;
use App\Jobs\GenerateThumbnailJob;
use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\StorageUsage;
use App\Models\User;
use App\Services\Security\FileSecurityValidator;
use App\Services\Storage\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadFileAction
{
    public function __construct(
        protected StorageService $storageService,
        protected FileSecurityValidator $securityValidator,
        protected CalculateStorageUsageAction $storageUsageAction,
        protected \App\Services\SyncChangeLogger $syncLogger
    ) {}

    public function execute(User $user, UploadedFile $file, ?string $folderUuid = null): FileItem
    {
        $fileSize = $file->getSize();

        // 1. Quota Check
        $usage = StorageUsage::firstOrCreate(
            ['user_id' => $user->id],
            ['quota_bytes' => 10737418240]
        );

        if (!$usage->hasAvailableSpace($fileSize)) {
            throw ValidationException::withMessages([
                'file' => 'Storage quota exceeded. Please free up space or upgrade your plan.',
            ]);
        }

        // 2. Resolve Folder
        $folderId = null;
        if ($folderUuid) {
            $folder = Folder::where('uuid', $folderUuid)
                ->where('owner_id', $user->id)
                ->first();
            if ($folder) {
                $folderId = $folder->id;
            }
        }

        // 3. Security Inspection
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
        $clientMime = $file->getMimeType() ?: 'application/octet-stream';

        $inspection = $this->securityValidator->inspect($file->getRealPath(), $originalName, $clientMime);
        $status = $inspection['quarantine'] ? FileStatus::QUARANTINED : FileStatus::READY;

        // 4. Calculate Checksum
        $checksum = hash_file('sha256', $file->getRealPath());

        // 5. Generate Obfuscated Physical Storage Path
        $fileUuid = (string) Str::uuid();
        $storagePath = $this->storageService->generateStoragePath($user, $fileUuid, $extension);
        $storageName = basename($storagePath);
        $disk = config('filesystems.default', 'local');

        // 6. Put to Storage
        $stream = fopen($file->getRealPath(), 'rb');
        $this->storageService->disk($disk)->put($storagePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // 7. Check for deduplication / existing file with same name in folder
        $existingFile = FileItem::where('owner_id', $user->id)
            ->where('folder_id', $folderId)
            ->where('original_name', $originalName)
            ->whereNull('deleted_at')
            ->first();

        $isNewVersion = (bool) $existingFile;

        if ($existingFile) {
            // Create new version
            $newVersion = $existingFile->version + 1;
            FileVersion::create([
                'file_id' => $existingFile->id,
                'version_number' => $existingFile->version,
                'storage_name' => $existingFile->storage_name,
                'storage_path' => $existingFile->storage_path,
                'size' => $existingFile->size,
                'checksum' => $existingFile->checksum,
                'disk' => $existingFile->disk,
                'created_at' => $existingFile->updated_at,
            ]);

            $existingFile->update([
                'storage_name' => $storageName,
                'storage_path' => $storagePath,
                'size' => $fileSize,
                'checksum' => $checksum,
                'mime_type' => $clientMime,
                'extension' => $extension,
                'status' => $status,
                'version' => $newVersion,
            ]);

            $fileItem = $existingFile;
        } else {
            $fileItem = FileItem::create([
                'uuid' => $fileUuid,
                'owner_id' => $user->id,
                'folder_id' => $folderId,
                'original_name' => $originalName,
                'storage_name' => $storageName,
                'mime_type' => $clientMime,
                'extension' => strtolower($extension),
                'size' => $fileSize,
                'checksum' => $checksum,
                'disk' => $disk,
                'storage_path' => $storagePath,
                'visibility' => FileVisibility::PRIVATE,
                'status' => $status,
                'version' => 1,
            ]);
        }

        // 8. Increment Storage Quota
        $this->storageUsageAction->increment($user, $fileItem);

        // 9. Dispatch thumbnail processing if image
        if ($fileItem->category === 'image' && $fileItem->status === FileStatus::READY) {
            GenerateThumbnailJob::dispatch($fileItem);
        }

        // 10. Change Feed for Desktop Sync
        $this->syncLogger->logFileChange($user, $fileItem, $isNewVersion ? 'updated' : 'created');

        // 11. Audit Log
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'file.upload',
            'target_type' => 'File',
            'target_id' => $fileItem->id,
            'metadata' => [
                'name' => $fileItem->original_name,
                'size' => $fileItem->size,
                'uuid' => $fileItem->uuid,
            ],
        ]);

        return $fileItem;
    }
}
