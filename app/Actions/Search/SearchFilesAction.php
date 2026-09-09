<?php

namespace App\Actions\Search;

use App\Models\FileItem;
use App\Models\Folder;
use App\Models\User;
use App\Services\Notifications\WhatsAppNotificationService;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchFilesAction
{
    public function __construct(protected WhatsAppNotificationService $whatsAppService) {}

    /**
     * Search files and folders owned by or shared with the user.
     */
    public function execute(User $user, array $filters = []): array
    {
        $query = $filters['q'] ?? '';
        $category = $filters['category'] ?? null;
        $extension = $filters['extension'] ?? null;

        $filesQuery = FileItem::where('owner_id', $user->id)
            ->whereNull('deleted_at');

        $foldersQuery = Folder::where('owner_id', $user->id)
            ->whereNull('deleted_at');

        if (!empty($query)) {
            $filesQuery->where('original_name', 'like', "%{$query}%");
            $foldersQuery->where('name', 'like', "%{$query}%");
        }

        if ($category) {
            $filesQuery->where(function ($q) use ($category) {
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

        if ($extension) {
            $filesQuery->where('extension', strtolower($extension));
        }

        $files = $filesQuery->latest()->limit(50)->get();
        $folders = empty($category) && empty($extension) ? $foldersQuery->latest()->limit(20)->get() : collect();

        return [
            'folders' => $folders,
            'files' => $files,
        ];
    }

    /**
     * Privacy-safe WhatsApp lookup for sharing.
     * Returns ONLY name, avatar, and sharable status.
     * Never exposes email, storage stats, or internal IDs.
     */
    public function lookupByWhatsApp(string $rawNumber): ?array
    {
        $normalized = $this->whatsAppService->normalizePhoneNumber($rawNumber);

        $user = User::where('whatsapp', $normalized)
            ->orWhere('whatsapp', $rawNumber)
            ->first();

        if (!$user) {
            return null;
        }

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
            'can_receive_share' => $user->isActive(),
        ];
    }
}
