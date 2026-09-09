<?php

namespace App\Actions\Files;

use App\Actions\Storage\CalculateStorageUsageAction;
use App\Enums\FileStatus;
use App\Enums\FileVisibility;
use App\Jobs\GenerateThumbnailJob;
use App\Models\AuditLog;
use App\Models\FileChunk;
use App\Models\FileItem;
use App\Models\FileVersion;
use App\Models\StorageUsage;
use App\Models\User;
use App\Services\Security\FileSecurityValidator;
use App\Services\Storage\StorageService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CompleteUploadAction
{
    public function __construct(
        protected StorageService $storageService,
        protected FileSecurityValidator $securityValidator,
        protected CalculateStorageUsageAction $storageUsageAction
    ) {}

    public function execute(User $user, string $uploadId): FileItem
    {
        // 1. Fetch chunk metadata
        $firstChunk = FileChunk::where('upload_id', $uploadId)
            ->where('user_id', $user->id)
            ->first();

        if (!$firstChunk) {
            throw ValidationException::withMessages([
                'upload_id' => 'Invalid or expired upload session.',
            ]);
        }

        $totalChunks = $firstChunk->total_chunks;
        $completedChunksCount = FileChunk::where('upload_id', $uploadId)
            ->where('status', 'completed')
            ->count();

        if ($completedChunksCount < $totalChunks) {
            throw ValidationException::withMessages([
                'chunks' => "Upload incomplete. {$completedChunksCount} of {$totalChunks} chunks received.",
            ]);
        }

        // 2. Storage Quota Check
        $usage = StorageUsage::firstOrCreate(
            ['user_id' => $user->id],
            ['quota_bytes' => 10737418240]
        );

        if (!$usage->hasAvailableSpace($firstChunk->total_size)) {
            throw ValidationException::withMessages([
                'quota' => 'Storage quota exceeded. Cannot finalize upload.',
            ]);
        }

        // 3. Assemble chunks to storage
        $originalName = $firstChunk->original_name;
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileUuid = (string) Str::uuid();
        $disk = config('filesystems.default', 'local');
        $storagePath = $this->storageService->generateStoragePath($user, $fileUuid, $extension);
        $storageName = basename($storagePath);

        $mergedResult = $this->storageService->mergeChunks($uploadId, $totalChunks, $storagePath, $disk);

        // 4. Security Inspection
        $fullPath = storage_path("app/{$storagePath}");
        $realMime = $mergedResult['mime_type'];
        $inspection = $this->securityValidator->inspect($fullPath, $originalName, $realMime);
        $status = $inspection['quarantine'] ? FileStatus::QUARANTINED : FileStatus::READY;

        // 5. Versioning / Deduplication
        $existingFile = FileItem::where('owner_id', $user->id)
            ->where('folder_id', $firstChunk->folder_id)
            ->where('original_name', $originalName)
            ->whereNull('deleted_at')
            ->first();

        if ($existingFile) {
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
                'size' => $mergedResult['size'],
                'checksum' => $mergedResult['checksum'],
                'mime_type' => $realMime,
                'extension' => strtolower($extension),
                'status' => $status,
                'version' => $existingFile->version + 1,
            ]);

            $fileItem = $existingFile;
        } else {
            $fileItem = FileItem::create([
                'uuid' => $fileUuid,
                'owner_id' => $user->id,
                'folder_id' => $firstChunk->folder_id,
                'original_name' => $originalName,
                'storage_name' => $storageName,
                'mime_type' => $realMime,
                'extension' => strtolower($extension),
                'size' => $mergedResult['size'],
                'checksum' => $mergedResult['checksum'],
                'disk' => $disk,
                'storage_path' => $storagePath,
                'visibility' => FileVisibility::PRIVATE,
                'status' => $status,
                'version' => 1,
            ]);
        }

        // 6. Delete file_chunks records
        FileChunk::where('upload_id', $uploadId)->delete();

        // 7. Increment Storage Quota
        $this->storageUsageAction->increment($user, $fileItem);

        // 8. Generate Thumbnail if image
        if ($fileItem->category === 'image' && $fileItem->status === FileStatus::READY) {
            GenerateThumbnailJob::dispatch($fileItem);
        }

        // 9. Audit Log
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'file.chunk_upload_complete',
            'target_type' => 'File',
            'target_id' => $fileItem->id,
            'metadata' => [
                'name' => $fileItem->original_name,
                'size' => $fileItem->size,
                'chunks' => $totalChunks,
            ],
        ]);

        return $fileItem;
    }
}
