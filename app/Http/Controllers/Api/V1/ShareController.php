<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Shares\RevokeShareAction;
use App\Actions\Shares\ShareFileAction;
use App\Enums\ShareAccessType;
use App\Enums\SharePermissionType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\Folder;
use App\Models\Share;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ShareController extends Controller
{
    public function __construct(
        protected ShareFileAction $shareFileAction,
        protected RevokeShareAction $revokeShareAction,
        protected StorageService $storageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type', 'owned'); // 'owned' or 'received'

        if ($type === 'received') {
            $shares = Share::where('shared_with_user_id', $user->id)
                ->with(['shareable', 'owner:id,name,email,avatar_url', 'permissions'])
                ->latest()
                ->get();
        } else {
            $shares = Share::where('owner_id', $user->id)
                ->with(['shareable', 'sharedWithUser:id,name,email,avatar_url', 'permissions'])
                ->latest()
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $shares,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_type' => ['required', 'in:file,folder'],
            'item_uuid' => ['required', 'string'],
            'permission' => ['nullable', 'in:viewer,commenter,editor'],
            'access_type' => ['nullable', 'in:public_link,specific_user,specific_whatsapp'],
            'target_contact' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:4'],
            'allow_download' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $user = $request->user();

        if ($validated['item_type'] === 'file') {
            $item = FileItem::where('uuid', $validated['item_uuid'])
                ->where('owner_id', $user->id)
                ->firstOrFail();
        } else {
            $item = Folder::where('uuid', $validated['item_uuid'])
                ->where('owner_id', $user->id)
                ->firstOrFail();
        }

        $share = $this->shareFileAction->execute(
            $user,
            $item,
            SharePermissionType::tryFrom($validated['permission'] ?? 'viewer') ?? SharePermissionType::VIEWER,
            ShareAccessType::tryFrom($validated['access_type'] ?? 'public_link') ?? ShareAccessType::PUBLIC_LINK,
            $validated['target_contact'] ?? null,
            $validated['password'] ?? null,
            $validated['allow_download'] ?? true,
            $validated['expires_at'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Tautan berbagi berhasil dibuat.',
            'data' => array_merge($share->toArray(), [
                'share_url' => url("/s/{$share->token}"),
            ]),
        ], 201);
    }

    public function revoke(Request $request, string $uuid): JsonResponse
    {
        $share = Share::where('uuid', $uuid)->firstOrFail();
        $this->revokeShareAction->execute($request->user(), $share);

        return response()->json([
            'success' => true,
            'message' => 'Akses berbagi berhasil dicabut.',
        ]);
    }

    public function accessPublic(Request $request, string $token): JsonResponse
    {
        $share = Share::where('token', $token)
            ->with(['shareable', 'owner:id,name,avatar_url', 'permissions'])
            ->firstOrFail();

        if ($share->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan berbagi ini telah kedaluwarsa.',
            ], 410);
        }

        if ($share->password) {
            $enteredPassword = $request->header('X-Share-Password');
            if (!$enteredPassword || !Hash::check($enteredPassword, $share->password)) {
                return response()->json([
                    'success' => false,
                    'password_required' => true,
                    'message' => 'Tautan ini dilindungi kata sandi.',
                ], 403);
            }
        }

        AuditLog::create([
            'user_id' => null,
            'action' => 'share.access_public',
            'target_type' => 'Share',
            'target_id' => $share->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $share->token,
                'permission' => $share->permission->value,
                'allow_download' => $share->allow_download,
                'owner' => $share->owner,
                'item_type' => class_basename($share->shareable_type),
                'item' => $share->shareable,
                'permissions' => $share->permissions,
            ],
        ]);
    }

    public function downloadPublic(Request $request, string $token)
    {
        $share = Share::where('token', $token)->with('shareable')->firstOrFail();

        if ($share->isExpired() || !$share->allow_download) {
            abort(403, 'Unduhan dinonaktifkan atau tautan kedaluwarsa.');
        }

        if ($share->password) {
            $enteredPassword = $request->query('pwd') ?: $request->header('X-Share-Password');
            if (!$enteredPassword || !Hash::check($enteredPassword, $share->password)) {
                abort(403, 'Kata sandi tidak sesuai.');
            }
        }

        if ($share->shareable_type !== FileItem::class) {
            abort(400, 'Unduhan langsung hanya berlaku untuk berkas tunggal.');
        }

        /** @var FileItem $file */
        $file = $share->shareable;
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
}
