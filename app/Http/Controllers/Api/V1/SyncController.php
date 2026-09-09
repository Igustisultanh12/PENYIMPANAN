<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Files\UploadFileAction;
use App\Http\Controllers\Controller;
use App\Models\FileItem;
use App\Models\SyncChange;
use App\Models\UserDevice;
use App\Services\OfficeEditorService;
use App\Services\SyncChangeLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SyncController extends Controller
{
    public function __construct(
        protected SyncChangeLogger $syncLogger,
        protected UploadFileAction $uploadFileAction
    ) {}

    /**
     * Get incremental change feed since given cursor ID.
     */
    public function changes(Request $request): JsonResponse
    {
        $user = $request->user();
        $cursor = (int) $request->query('cursor', 0);
        $limit = min((int) $request->query('limit', 100), 500);
        $deviceId = $request->query('device_id');

        $query = SyncChange::where('user_id', $user->id)
            ->where('id', '>', $cursor)
            ->orderBy('id', 'asc')
            ->limit($limit);

        // Optionally ignore echo changes initiated by this same device
        if ($deviceId && $request->boolean('exclude_self', false)) {
            $query->where(function ($q) use ($deviceId) {
                $q->whereNull('device_id')->orWhere('device_id', '!=', $deviceId);
            });
        }

        $changes = $query->get();
        $nextCursor = $changes->isNotEmpty() ? $changes->last()->id : $cursor;
        $hasMore = $changes->count() === $limit;

        return response()->json([
            'success' => true,
            'data' => [
                'changes' => $changes,
                'cursor' => $nextCursor,
                'has_more' => $hasMore,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Checkpoint device synchronization progress.
     */
    public function checkpoint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string'],
            'cursor' => ['required', 'integer'],
        ]);

        $device = UserDevice::where('user_id', $request->user()->id)
            ->where('device_id', $validated['device_id'])
            ->first();

        if ($device) {
            $device->update([
                'last_synced_at' => now(),
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sync checkpoint updated.',
        ]);
    }

    /**
     * Resolve synchronization file conflict.
     * Strategies:
     * - 'keep_local': Local file replaces cloud file (increments version).
     * - 'keep_cloud': Local device accepts cloud version (server confirms current file state).
     * - 'keep_both': Creates a new file copy with suffix "(Conflict {device_name} {date})".
     */
    public function resolveConflict(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_uuid' => ['required', 'string'],
            'strategy' => ['required', 'in:keep_local,keep_cloud,keep_both'],
            'device_name' => ['nullable', 'string'],
            'file' => ['required_if:strategy,keep_local,keep_both', 'file'],
        ]);

        $user = $request->user();
        $fileItem = FileItem::where('uuid', $validated['file_uuid'])
            ->where('owner_id', $user->id)
            ->firstOrFail();

        $strategy = $validated['strategy'];

        if ($strategy === 'keep_cloud') {
            // Keep Cloud: return current cloud file metadata & download link
            return response()->json([
                'success' => true,
                'strategy' => 'keep_cloud',
                'message' => 'Cloud version retained.',
                'data' => [
                    'file' => $fileItem,
                    'download_url' => route('api.v1.files.download', ['uuid' => $fileItem->uuid]),
                ],
            ]);
        }

        if ($strategy === 'keep_local') {
            // Overwrite cloud by uploading local file as next version
            $uploaded = $request->file('file');
            $updatedFile = $this->uploadFileAction->execute(
                $user,
                $uploaded,
                $fileItem->folder?->uuid
            );

            return response()->json([
                'success' => true,
                'strategy' => 'keep_local',
                'message' => 'Local version saved as new version on cloud.',
                'data' => $updatedFile,
            ]);
        }

        if ($strategy === 'keep_both') {
            // Create separate copy
            $uploaded = $request->file('file');
            $deviceName = $validated['device_name'] ?: 'Desktop';
            $dateStr = now()->format('Y-m-d_H-i');
            $conflictName = pathinfo($fileItem->original_name, PATHINFO_FILENAME)
                . " (Conflict Copy {$deviceName} {$dateStr})."
                . $fileItem->extension;

            // Upload as a new distinct file
            $newFile = $this->uploadFileAction->execute(
                $user,
                $uploaded,
                $fileItem->folder?->uuid
            );
            $newFile->update(['original_name' => $conflictName]);

            return response()->json([
                'success' => true,
                'strategy' => 'keep_both',
                'message' => 'Both files preserved. Conflict copy created.',
                'data' => [
                    'original' => $fileItem,
                    'conflict_copy' => $newFile,
                ],
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid strategy.'], 400);
    }
}
