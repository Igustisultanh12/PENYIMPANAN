<?php

namespace App\Services\Storage;

use App\Models\FileItem;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class StorageService
{
    /**
     * Get the configured filesystem disk instance.
     */
    public function disk(?string $diskName = null): Filesystem
    {
        $disk = $diskName ?: config('filesystems.default', 'local');
        return Storage::disk($disk);
    }

    /**
     * Generate an obfuscated, tenant-isolated storage path.
     * Never uses incremental IDs or raw filenames.
     * Format: tenants/{user_uuid}/{yyyy}/{mm}/{file_uuid}.{ext}
     */
    public function generateStoragePath(User $user, string $fileUuid, string $extension): string
    {
        $year = date('Y');
        $month = date('m');
        $sanitizedExt = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($extension));

        return "tenants/{$user->uuid}/{$year}/{$month}/{$fileUuid}" . ($sanitizedExt ? ".{$sanitizedExt}" : '');
    }

    /**
     * Generate a temporary path for upload chunks.
     */
    public function generateChunkPath(string $uploadId, int $chunkIndex): string
    {
        return "chunks/{$uploadId}/chunk_{$chunkIndex}.part";
    }

    /**
     * Store an individual uploaded chunk.
     */
    public function storeChunk(string $uploadId, int $chunkIndex, $content): string
    {
        $chunkPath = $this->generateChunkPath($uploadId, $chunkIndex);
        Storage::disk('local')->put($chunkPath, $content);

        return $chunkPath;
    }

    /**
     * Stream and assemble chunks into the final target storage location.
     * Uses memory-safe streaming (10MB buffer) to handle files of any size (5GB+)
     * without loading entire files into PHP RAM.
     *
     * @return array{size: int, checksum: string, mime_type: string}
     */
    public function mergeChunks(string $uploadId, int $totalChunks, string $targetPath, ?string $targetDisk = null): array
    {
        $localDisk = Storage::disk('local');
        $destDisk = $this->disk($targetDisk);

        // Create temporary local merged file
        $tempMergedRelative = "chunks/{$uploadId}/assembled.tmp";
        $tempMergedPath = $localDisk->path($tempMergedRelative);
        if (!is_dir(dirname($tempMergedPath))) {
            mkdir(dirname($tempMergedPath), 0755, true);
        }

        $destHandle = fopen($tempMergedPath, 'wb');
        if (!$destHandle) {
            throw new RuntimeException("Cannot open assembled destination file for writing.");
        }

        $hashContext = hash_init('sha256');
        $totalBytes = 0;

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkRelativePath = $this->generateChunkPath($uploadId, $i);

                if (!$localDisk->exists($chunkRelativePath)) {
                    throw new RuntimeException("Missing chunk index {$i} for upload session {$uploadId}.");
                }

                $chunkFullPath = $localDisk->path($chunkRelativePath);
                $chunkHandle = fopen($chunkFullPath, 'rb');

                if (!$chunkHandle) {
                    throw new RuntimeException("Unable to read chunk {$i}.");
                }

                while (!feof($chunkHandle)) {
                    $buffer = fread($chunkHandle, 8192 * 1024); // 8MB buffer
                    if ($buffer === false) {
                        break;
                    }
                    fwrite($destHandle, $buffer);
                    hash_update($hashContext, $buffer);
                    $totalBytes += strlen($buffer);
                }

                fclose($chunkHandle);
            }
        } finally {
            fclose($destHandle);
        }

        $checksum = hash_final($hashContext);

        // Detect real MIME type directly from assembled file bytes (never trust browser header)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $tempMergedPath) ?: 'application/octet-stream';
        finfo_close($finfo);

        // Stream temporary merged file to destination disk (Local or S3/MinIO)
        $stream = fopen($tempMergedPath, 'rb');
        $destDisk->put($targetPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Clean up temporary chunks and merged temp file
        $localDisk->deleteDirectory("chunks/{$uploadId}");
        if (file_exists($tempMergedPath)) {
            @unlink($tempMergedPath);
        }

        return [
            'size' => $totalBytes,
            'checksum' => $checksum,
            'mime_type' => $realMime,
        ];
    }

    /**
     * Generate a signed temporary download URL.
     */
    public function getSignedDownloadUrl(FileItem $file, int $expirationMinutes = 60): string
    {
        return URL::temporarySignedRoute(
            'api.v1.files.download.signed',
            now()->addMinutes($expirationMinutes),
            ['file' => $file->uuid]
        );
    }

    /**
     * Delete file physical payload from disk.
     */
    public function deletePhysicalFile(string $path, ?string $diskName = null): bool
    {
        return $this->disk($diskName)->delete($path);
    }
}
