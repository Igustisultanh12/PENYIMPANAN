<?php

namespace App\Services;

use App\Models\DocumentLock;
use App\Models\FileItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DocumentLockService
{
    /**
     * Attempt to acquire a document lock for editing.
     */
    public function acquireLock(FileItem $file, User $user, ?string $deviceId = null, int $ttlSeconds = 300): array
    {
        // Purge expired locks for this file first
        DocumentLock::where('file_id', $file->id)
            ->where('expires_at', '<=', now())
            ->delete();

        $existingLock = DocumentLock::where('file_id', $file->id)
            ->with('user')
            ->first();

        if ($existingLock) {
            // If already locked by the same user, extend the lease
            if ($existingLock->user_id === $user->id) {
                $existingLock->update([
                    'expires_at' => now()->addSeconds($ttlSeconds),
                    'device_id' => $deviceId ?: $existingLock->device_id,
                ]);

                return [
                    'locked' => true,
                    'is_owner' => true,
                    'lock_token' => $existingLock->lock_token,
                    'expires_at' => $existingLock->expires_at->toIso8601String(),
                ];
            }

            // Locked by someone else
            return [
                'locked' => false,
                'is_owner' => false,
                'locked_by' => [
                    'id' => $existingLock->user->id,
                    'name' => $existingLock->user->name,
                    'email' => $existingLock->user->email,
                ],
                'expires_at' => $existingLock->expires_at->toIso8601String(),
            ];
        }

        // Create new lock
        $token = Str::random(64);
        $expiresAt = now()->addSeconds($ttlSeconds);

        $lock = DocumentLock::create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'lock_token' => $token,
            'expires_at' => $expiresAt,
        ]);

        return [
            'locked' => true,
            'is_owner' => true,
            'lock_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Heartbeat / renew an existing lock lease.
     */
    public function renewLock(FileItem $file, string $lockToken, int $ttlSeconds = 300): bool
    {
        $lock = DocumentLock::where('file_id', $file->id)
            ->where('lock_token', $lockToken)
            ->first();

        if (!$lock) {
            return false;
        }

        $lock->update([
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);

        return true;
    }

    /**
     * Explicitly release a document lock.
     */
    public function releaseLock(FileItem $file, string $lockToken): bool
    {
        return (bool) DocumentLock::where('file_id', $file->id)
            ->where('lock_token', $lockToken)
            ->delete();
    }

    /**
     * Force unlock by owner or administrator.
     */
    public function forceUnlock(FileItem $file, User $user): bool
    {
        if ($file->owner_id === $user->id || $user->isAdmin()) {
            DocumentLock::where('file_id', $file->id)->delete();
            return true;
        }

        return false;
    }

    /**
     * Check if file currently has an active lock.
     */
    public function checkLock(FileItem $file): ?DocumentLock
    {
        return DocumentLock::where('file_id', $file->id)
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();
    }
}
