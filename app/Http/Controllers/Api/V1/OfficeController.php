<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FileItem;
use App\Models\Folder;
use App\Models\OfficeSession;
use App\Services\DocumentLockService;
use App\Services\DocumentTemplateService;
use App\Services\OfficeEditorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OfficeController extends Controller
{
    public function __construct(
        protected OfficeEditorService $officeService,
        protected DocumentTemplateService $templateService,
        protected DocumentLockService $lockService
    ) {}

    /**
     * Get available document templates.
     */
    public function templates(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $templates = $this->templateService->getTemplates($type);

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Create a new office document (Word, Excel, PowerPoint).
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:document,spreadsheet,presentation'],
            'folder_uuid' => ['nullable', 'string'],
        ]);

        $folderId = null;
        if (!empty($validated['folder_uuid'])) {
            $folder = Folder::where('uuid', $validated['folder_uuid'])
                ->where('owner_id', $request->user()->id)
                ->firstOrFail();
            $folderId = $folder->id;
        }

        $file = $this->officeService->createDocument(
            $request->user(),
            $validated['name'],
            $validated['type'],
            $folderId
        );

        return response()->json([
            'success' => true,
            'message' => 'Dokumen baru berhasil dibuat.',
            'data' => $file->load('folder'),
        ], 201);
    }

    /**
     * Open or resume an office session for a given file.
     */
    public function session(Request $request, string $fileUuid): JsonResponse
    {
        $user = $request->user();
        Log::info("OfficeController@session: Membuka sesi dokumen UUID={$fileUuid} oleh User ID={$user->id} ({$user->email})");

        try {
            $file = FileItem::where('uuid', $fileUuid)
                ->where('owner_id', $user->id)
                ->first();

            if (!$file) {
                // Fallback: periksa apakah file ada di sistem
                $file = FileItem::where('uuid', $fileUuid)->first();
                if (!$file) {
                    Log::error("OfficeController@session: File dokumen UUID={$fileUuid} TIDAK DITEMUKAN di database untuk user_id={$user->id}");
                    return response()->json([
                        'success' => false,
                        'message' => "File dokumen dengan UUID '{$fileUuid}' tidak ditemukan.",
                    ], 404);
                }
            }

            $mode = $request->query('mode', 'edit');
            $deviceId = $request->header('X-Device-ID');

            $sessionData = $this->officeService->createSession($file, $user, $mode, $deviceId);

            Log::info("OfficeController@session: Berhasil memuat sesi dokumen '{$file->original_name}' (Type: {$sessionData['document_type']}, Mode: {$sessionData['mode']})");

            return response()->json([
                'success' => true,
                'data' => $sessionData,
            ]);
        } catch (\Throwable $e) {
            Log::error("OfficeController@session GAGAL untuk file UUID={$fileUuid}: " . $e->getMessage(), [
                'user_id' => $user->id,
                'file_uuid' => $fileUuid,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat sesi dokumen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Autosave draft content for active session.
     */
    public function saveDraft(Request $request, string $sessionToken): JsonResponse
    {
        try {
            $session = OfficeSession::where('session_token', $sessionToken)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $validated = $request->validate([
                'draft' => ['required'],
            ]);

            $this->officeService->saveDraft($session, $validated['draft']);

            return response()->json([
                'success' => true,
                'message' => 'Draft tersimpan otomatis.',
                'last_autosave_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error("OfficeController@saveDraft GAGAL: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan draft: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Commit changes to cloud storage and create a new version.
     */
    public function commit(Request $request, string $sessionToken): JsonResponse
    {
        try {
            $session = OfficeSession::where('session_token', $sessionToken)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $validated = $request->validate([
                'draft' => ['nullable'],
                'binary_base64' => ['nullable', 'string'],
            ]);

            $binaryBytes = !empty($validated['binary_base64'])
                ? base64_decode($validated['binary_base64'])
                : null;

            $deviceId = $request->header('X-Device-ID');

            $updatedFile = $this->officeService->commitSession(
                $session,
                $binaryBytes,
                $validated['draft'] ?? null,
                $deviceId
            );

            Log::info("OfficeController@commit: Berhasil menyimpan versi baru v{$updatedFile->version} untuk file '{$updatedFile->original_name}'");

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil disimpan ke cloud sebagai versi ' . $updatedFile->version . '.',
                'data' => $updatedFile,
            ]);
        } catch (\Throwable $e) {
            Log::error("OfficeController@commit GAGAL: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan dokumen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Acquire or refresh document lock.
     */
    public function lock(Request $request, string $fileUuid): JsonResponse
    {
        $file = FileItem::where('uuid', $fileUuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $deviceId = $request->header('X-Device-ID');
        $result = $this->lockService->acquireLock($file, $request->user(), $deviceId);

        return response()->json([
            'success' => $result['locked'],
            'data' => $result,
        ], $result['locked'] ? 200 : 423); // 423 Locked
    }

    /**
     * Release document lock.
     */
    public function unlock(Request $request, string $fileUuid): JsonResponse
    {
        $file = FileItem::where('uuid', $fileUuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $lockToken = $request->input('lock_token');
        if ($lockToken) {
            $this->lockService->releaseLock($file, $lockToken);
        } else {
            $this->lockService->forceUnlock($file, $request->user());
        }

        return response()->json([
            'success' => true,
            'message' => 'Kunci dokumen dilepaskan.',
        ]);
    }
}
