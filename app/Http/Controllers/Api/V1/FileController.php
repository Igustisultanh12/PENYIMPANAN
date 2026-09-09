<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Files\DeleteFileAction;
use App\Actions\Files\MoveFileAction;
use App\Actions\Files\PermanentDeleteFileAction;
use App\Actions\Files\RestoreFileAction;
use App\Actions\Files\UploadFileAction;
use App\Actions\Storage\GenerateDownloadUrlAction;
use App\Enums\FileStatus;
use App\Http\Controllers\Controller;
use App\Models\FileItem;
use App\Models\Folder;
use App\Models\TrashItem;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(
        protected UploadFileAction $uploadFileAction,
        protected DeleteFileAction $deleteFileAction,
        protected RestoreFileAction $restoreFileAction,
        protected PermanentDeleteFileAction $permanentDeleteAction,
        protected MoveFileAction $moveFileAction,
        protected GenerateDownloadUrlAction $downloadUrlAction,
        protected StorageService $storageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $folderUuid = $request->query('folder_uuid');
        $filter = $request->query('filter'); // 'starred', 'recent', 'trash'
        $category = $request->query('category'); // 'image', 'video', 'document', etc.
        $perPage = (int) $request->query('per_page', 50);

        if ($filter === 'trash') {
            $trashed = FileItem::onlyTrashed()
                ->where('owner_id', $user->id)
                ->latest('deleted_at')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $trashed->items(),
                'meta' => [
                    'current_page' => $trashed->currentPage(),
                    'total' => $trashed->total(),
                    'last_page' => $trashed->lastPage(),
                ],
            ]);
        }

        $query = FileItem::where('owner_id', $user->id)
            ->whereNull('deleted_at')
            ->with(['metadata', 'folder']);

        if ($filter === 'starred') {
            $query->where('is_starred', true);
        } elseif ($filter === 'recent') {
            $query->latest('updated_at')->limit(30);
            return response()->json([
                'success' => true,
                'data' => $query->get(),
            ]);
        } else {
            if ($folderUuid) {
                $folder = Folder::where('uuid', $folderUuid)
                    ->where('owner_id', $user->id)
                    ->firstOrFail();
                $query->where('folder_id', $folder->id);
            } else {
                $query->whereNull('folder_id');
            }
        }

        if ($category) {
            $query->where(function ($q) use ($category) {
                match ($category) {
                    'image' => $q->where('mime_type', 'like', 'image/%'),
                    'video' => $q->where('mime_type', 'like', 'video/%'),
                    'audio' => $q->where('mime_type', 'like', 'audio/%'),
                    'document' => $q->where('mime_type', 'like', '%pdf%')
                        ->orWhere('mime_type', 'like', '%document%')
                        ->orWhere('mime_type', 'like', '%text%'),
                    'archive' => $q->whereIn('extension', ['zip', 'rar', '7z', 'tar', 'gz']),
                    default => null,
                };
            });
        }

        $files = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $files->items(),
            'meta' => [
                'current_page' => $files->currentPage(),
                'total' => $files->total(),
                'last_page' => $files->lastPage(),
            ],
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:102400'], // 100MB direct limit; larger files use chunk upload
            'folder_uuid' => ['nullable', 'string'],
        ]);

        $fileItem = $this->uploadFileAction->execute(
            $request->user(),
            $request->file('file'),
            $request->input('folder_uuid')
        );

        return response()->json([
            'success' => true,
            'message' => 'Berkas berhasil diunggah.',
            'data' => $fileItem->load(['metadata', 'folder']),
        ], 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $file = FileItem::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->with(['metadata', 'versions', 'folder'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $file,
        ]);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $file = FileItem::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'is_starred' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $file->original_name = $validated['name'];
        }
        if (isset($validated['is_starred'])) {
            $file->is_starred = $validated['is_starred'];
        }

        $file->save();

        app(\App\Services\SyncChangeLogger::class)->logFileChange($request->user(), $file, 'updated');

        return response()->json([
            'success' => true,
            'message' => 'Berkas berhasil diperbarui.',
            'data' => $file,
        ]);
    }

    public function move(Request $request, string $uuid): JsonResponse
    {
        $file = FileItem::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'target_folder_uuid' => ['nullable', 'string'],
        ]);

        $updated = $this->moveFileAction->execute(
            $request->user(),
            $file,
            $validated['target_folder_uuid'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Berkas berhasil dipindahkan.',
            'data' => $updated,
        ]);
    }

    public function download(Request $request, string $uuid): JsonResponse
    {
        $file = FileItem::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $url = $this->downloadUrlAction->execute($request->user(), $file);

        return response()->json([
            'success' => true,
            'download_url' => $url,
        ]);
    }

    public function streamDownload(Request $request, string $uuid)
    {
        if (!$request->hasValidSignature()) {
            abort(401, 'Tautan unduhan tidak valid atau telah kedaluwarsa.');
        }

        $file = FileItem::where('uuid', $uuid)->firstOrFail();
        $disk = $this->storageService->disk($file->disk);

        if (!$disk->exists($file->storage_path)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        return response()->streamDownload(function () use ($disk, $file) {
            $stream = $disk->readStream($file->storage_path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'Content-Length' => $file->size,
        ]);
    }

    public function preview(Request $request, string $uuid)
    {
        $file = FileItem::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $disk = $this->storageService->disk($file->disk);

        if (!$disk->exists($file->storage_path)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        $stream = $disk->readStream($file->storage_path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($file->original_name) . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $file = FileItem::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $this->deleteFileAction->execute($request->user(), $file);

        return response()->json([
            'success' => true,
            'message' => 'Berkas dipindahkan ke sampah.',
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $file = $this->restoreFileAction->execute($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Berkas berhasil dipulihkan.',
            'data' => $file,
        ]);
    }

    public function permanentDelete(Request $request, int $id): JsonResponse
    {
        $this->permanentDeleteAction->execute($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Berkas berhasil dihapus secara permanen.',
        ]);
    }
}
