<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Files\CompleteUploadAction;
use App\Http\Controllers\Controller;
use App\Models\FileChunk;
use App\Models\Folder;
use App\Models\StorageUsage;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadChunkController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
        protected CompleteUploadAction $completeUploadAction
    ) {}

    /**
     * Initiate an upload session. Returns upload_id, expected chunks, and chunk size.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'total_size' => ['required', 'integer', 'min:1'],
            'chunk_size' => ['nullable', 'integer', 'min:1048576'], // Min 1MB
            'total_chunks' => ['required', 'integer', 'min:1'],
            'folder_uuid' => ['nullable', 'string'],
            'mime_type' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Check storage quota upfront
        $usage = StorageUsage::firstOrCreate(
            ['user_id' => $user->id],
            ['quota_bytes' => 10737418240]
        );

        if (!$usage->hasAvailableSpace($validated['total_size'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kapasitas penyimpanan tidak mencukupi untuk berkas ini.',
            ], 422);
        }

        $folderId = null;
        if (!empty($validated['folder_uuid'])) {
            $folder = Folder::where('uuid', $validated['folder_uuid'])
                ->where('owner_id', $user->id)
                ->first();
            if ($folder) {
                $folderId = $folder->id;
            }
        }

        $uploadId = (string) Str::uuid();
        $chunkSize = $validated['chunk_size'] ?? 5242880; // 5MB default

        return response()->json([
            'success' => true,
            'data' => [
                'upload_id' => $uploadId,
                'chunk_size' => $chunkSize,
                'total_chunks' => $validated['total_chunks'],
                'folder_id' => $folderId,
                'expires_at' => now()->addHours(24)->toISOString(),
            ],
        ]);
    }

    /**
     * Upload an individual chunk. Idempotent: duplicate chunks return HTTP 200 without corruption.
     */
    public function chunk(Request $request, string $uploadId): JsonResponse
    {
        $request->validate([
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'total_size' => ['required', 'integer', 'min:1'],
            'original_name' => ['required', 'string'],
            'chunk' => ['required', 'file'],
            'folder_id' => ['nullable', 'integer'],
            'checksum' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $chunkIndex = (int) $request->input('chunk_index');
        $uploadedChunk = $request->file('chunk');

        // Check if chunk is already stored (idempotency support)
        $existing = FileChunk::where('upload_id', $uploadId)
            ->where('chunk_index', $chunkIndex)
            ->where('status', 'completed')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Chunk already received.',
                'chunk_index' => $chunkIndex,
            ]);
        }

        // Store chunk in local temp disk
        $stream = fopen($uploadedChunk->getRealPath(), 'rb');
        $chunkRelativePath = $this->storageService->generateChunkPath($uploadId, $chunkIndex);
        Storage::disk('local')->put($chunkRelativePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        FileChunk::updateOrCreate(
            [
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
            ],
            [
                'user_id' => $user->id,
                'folder_id' => $request->input('folder_id'),
                'original_name' => $request->input('original_name'),
                'mime_type' => $uploadedChunk->getMimeType() ?: 'application/octet-stream',
                'total_size' => (int) $request->input('total_size'),
                'total_chunks' => (int) $request->input('total_chunks'),
                'chunk_size' => $uploadedChunk->getSize(),
                'checksum' => $request->input('checksum'),
                'storage_path' => $chunkRelativePath,
                'status' => 'completed',
                'expires_at' => now()->addHours(24),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Chunk {$chunkIndex} uploaded successfully.",
            'chunk_index' => $chunkIndex,
        ]);
    }

    /**
     * Check upload status and get array of uploaded chunk indices for resumability.
     */
    public function status(Request $request, string $uploadId): JsonResponse
    {
        $uploadedChunks = FileChunk::where('upload_id', $uploadId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->pluck('chunk_index')
            ->toArray();

        return response()->json([
            'success' => true,
            'upload_id' => $uploadId,
            'uploaded_chunks' => $uploadedChunks,
        ]);
    }

    /**
     * Finalize the upload session and assemble all chunks.
     */
    public function finalize(Request $request, string $uploadId): JsonResponse
    {
        $fileItem = $this->completeUploadAction->execute($request->user(), $uploadId);

        return response()->json([
            'success' => true,
            'message' => 'Berkas berhasil digabungkan dan disimpan.',
            'data' => $fileItem->load(['metadata', 'folder']),
        ]);
    }

    /**
     * Cancel upload session and delete partial chunks.
     */
    public function cancel(Request $request, string $uploadId): JsonResponse
    {
        FileChunk::where('upload_id', $uploadId)
            ->where('user_id', $request->user()->id)
            ->delete();

        Storage::disk('local')->deleteDirectory("chunks/{$uploadId}");

        return response()->json([
            'success' => true,
            'message' => 'Sesi unggah berhasil dibatalkan.',
        ]);
    }
}
