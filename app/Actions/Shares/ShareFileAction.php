<?php

namespace App\Actions\Shares;

use App\Enums\ShareAccessType;
use App\Enums\SharePermissionType;
use App\Models\AuditLog;
use App\Models\FileItem;
use App\Models\Folder;
use App\Models\Share;
use App\Models\SharePermission;
use App\Models\User;
use App\Services\Notifications\WhatsAppNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShareFileAction
{
    public function __construct(protected WhatsAppNotificationService $whatsAppService) {}

    public function execute(
        User $owner,
        Model $item, // FileItem or Folder
        SharePermissionType $permission = SharePermissionType::VIEWER,
        ShareAccessType $accessType = ShareAccessType::PUBLIC_LINK,
        ?string $targetContact = null,
        ?string $password = null,
        bool $allowDownload = true,
        ?string $expiresAt = null,
        array $customPermissions = []
    ): Share {
        if (!($item instanceof FileItem) && !($item instanceof Folder)) {
            throw ValidationException::withMessages([
                'item' => 'Can only share files or folders.',
            ]);
        }

        $sharedWithUserId = null;

        // Resolve specific user if targetContact is provided
        if ($targetContact && ($accessType === ShareAccessType::SPECIFIC_USER || $accessType === ShareAccessType::SPECIFIC_WHATSAPP)) {
            $cleanContact = trim($targetContact);
            $normalizedPhone = $this->whatsAppService->normalizePhoneNumber($cleanContact);

            $targetUser = User::where('email', $cleanContact)
                ->orWhere('whatsapp', $cleanContact)
                ->orWhere('whatsapp', $normalizedPhone)
                ->first();

            if ($targetUser) {
                $sharedWithUserId = $targetUser->id;
            }
        }

        $share = Share::create([
            'uuid' => (string) Str::uuid(),
            'token' => Str::random(40),
            'shareable_type' => get_class($item),
            'shareable_id' => $item->id,
            'owner_id' => $owner->id,
            'shared_with_user_id' => $sharedWithUserId,
            'permission' => $permission,
            'access_type' => $accessType,
            'target_contact' => $targetContact,
            'password' => $password ? Hash::make($password) : null,
            'allow_download' => $allowDownload,
            'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null,
        ]);

        SharePermission::create([
            'share_id' => $share->id,
            'can_view' => $customPermissions['can_view'] ?? true,
            'can_download' => $allowDownload && ($customPermissions['can_download'] ?? true),
            'can_edit' => $permission === SharePermissionType::EDITOR || ($customPermissions['can_edit'] ?? false),
            'can_manage' => $customPermissions['can_manage'] ?? false,
        ]);

        AuditLog::create([
            'user_id' => $owner->id,
            'action' => 'share.create',
            'target_type' => class_basename($item),
            'target_id' => $item->id,
            'metadata' => [
                'token' => $share->token,
                'permission' => $permission->value,
                'access_type' => $accessType->value,
            ],
        ]);

        return $share->load('permissions');
    }
}
