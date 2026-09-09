<?php

namespace App\Actions\Shares;

use App\Models\AuditLog;
use App\Models\Share;
use App\Models\User;

class RevokeShareAction
{
    public function execute(User $owner, Share $share): void
    {
        if ($share->owner_id !== $owner->id && !$owner->isAdmin()) {
            abort(403, 'Unauthorized to revoke this share.');
        }

        AuditLog::create([
            'user_id' => $owner->id,
            'action' => 'share.revoke',
            'target_type' => 'Share',
            'target_id' => $share->id,
            'metadata' => ['token' => $share->token],
        ]);

        $share->delete();
    }
}
