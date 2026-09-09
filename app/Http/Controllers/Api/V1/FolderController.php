<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Folders\CreateFolderAction;
use App\Actions\Folders\DeleteFolderAction;
use App\Actions\Folders\MoveFolderAction;
use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function __construct(
        protected CreateFolderAction $createFolderAction,
        protected MoveFolderAction $moveFolderAction,
        protected DeleteFolderAction $deleteFolderAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $parentUuid = $request->query('parent_uuid');

        $parentId = null;
        if ($parentUuid) {
            $parentFolder = Folder::where('uuid', $parentUuid)
                ->where('owner_id', $user->id)
                ->firstOrFail();
            $parentId = $parentFolder->id;
        }

        $folders = Folder::where('owner_id', $user->id)
            ->where('parent_id', $parentId)
            ->whereNull('deleted_at')
            ->withCount(['files', 'children'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $folders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_uuid' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $folder = $this->createFolderAction->execute(
            $request->user(),
            $validated['name'],
            $validated['parent_uuid'] ?? null,
            $validated['color'] ?? '#3B82F6'
        );

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dibuat.',
            'data' => $folder,
        ], 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $folder = Folder::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->withCount(['files', 'children'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => array_merge($folder->toArray(), [
                'path' => $folder->path,
            ]),
        ]);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $folder = Folder::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_starred' => ['nullable', 'boolean'],
        ]);

        $folder->update(array_filter($validated, fn($v) => !is_null($v)));

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil diperbarui.',
            'data' => $folder,
        ]);
    }

    public function move(Request $request, string $uuid): JsonResponse
    {
        $folder = Folder::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'target_parent_uuid' => ['nullable', 'string'],
        ]);

        $updated = $this->moveFolderAction->execute(
            $request->user(),
            $folder,
            $validated['target_parent_uuid'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dipindahkan.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $folder = Folder::where('uuid', $uuid)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $this->deleteFolderAction->execute($request->user(), $folder);

        return response()->json([
            'success' => true,
            'message' => 'Folder dipindahkan ke sampah.',
        ]);
    }
}
