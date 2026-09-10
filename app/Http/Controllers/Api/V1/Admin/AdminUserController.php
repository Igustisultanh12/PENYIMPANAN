<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Storage\CalculateStorageUsageAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StorageUsage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * Check if authenticated user has admin privileges.
     */
    protected function authorizeAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Halaman dan aksi ini hanya dapat diakses oleh Administrator.',
            ], 403);
        }
        return null;
    }

    /**
     * List all users with storage usage and quotas.
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        $query = User::with('storageUsage')->orderBy('id', 'asc');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->integer('per_page', 50));

        $users->getCollection()->transform(function (User $user) {
            $usage = $user->storageUsage;
            $usedBytes = $usage?->total_bytes_used ?? 0;
            $quotaBytes = $usage?->quota_bytes ?? 10737418240; // Default 10GB
            $percentage = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 1) : 0;

            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp' => $user->whatsapp,
                'role' => $user->role->value ?? (string) $user->role,
                'status' => $user->status->value ?? (string) $user->status,
                'avatar_url' => $user->avatar_url,
                'created_at' => $user->created_at?->toIso8601String(),
                'storage' => [
                    'used_bytes' => $usedBytes,
                    'quota_bytes' => $quotaBytes,
                    'quota_gb' => round($quotaBytes / (1024 * 1024 * 1024), 2),
                    'free_bytes' => max(0, $quotaBytes - $usedBytes),
                    'percentage' => min(100, $percentage),
                    'used_formatted' => $this->formatBytes($usedBytes),
                    'quota_formatted' => $this->formatBytes($quotaBytes),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Update storage quota for a specific user.
     */
    public function updateQuota(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'quota_gb' => ['required_without:quota_bytes', 'numeric', 'min:0.1', 'max:1000000'],
            'quota_bytes' => ['required_without:quota_gb', 'numeric', 'min:1048576'],
        ]);

        if (isset($validated['quota_gb'])) {
            $quotaBytes = (int) round($validated['quota_gb'] * 1024 * 1024 * 1024);
        } else {
            $quotaBytes = (int) $validated['quota_bytes'];
        }

        $storageUsage = StorageUsage::firstOrCreate(
            ['user_id' => $targetUser->id],
            ['total_bytes_used' => 0]
        );

        $oldQuota = $storageUsage->quota_bytes;
        $storageUsage->quota_bytes = $quotaBytes;
        $storageUsage->save();

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'admin.user_quota_update',
            'target_type' => 'User',
            'target_id'   => $targetUser->id,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => [
                'target_user' => $targetUser->email,
                'old_quota'   => $oldQuota,
                'new_quota'   => $quotaBytes,
                'new_quota_gb'=> round($quotaBytes / (1024 * 1024 * 1024), 2),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Kuota penyimpanan untuk {$targetUser->name} berhasil diperbarui menjadi " . $this->formatBytes($quotaBytes) . '.',
            'data' => [
                'user_id' => $targetUser->id,
                'quota_bytes' => $quotaBytes,
                'quota_gb' => round($quotaBytes / (1024 * 1024 * 1024), 2),
                'quota_formatted' => $this->formatBytes($quotaBytes),
            ],
        ]);
    }

    /**
     * Recalculate physical storage usage for a specific user.
     */
    public function recalculate(Request $request, int $id, CalculateStorageUsageAction $calculateAction): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        $targetUser = User::findOrFail($id);
        $usage = $calculateAction->execute($targetUser);

        return response()->json([
            'success' => true,
            'message' => "Penggunaan penyimpanan untuk {$targetUser->name} berhasil dihitung ulang.",
            'data' => [
                'total_bytes_used' => $usage->total_bytes_used,
                'quota_bytes' => $usage->quota_bytes,
                'percentage' => $usage->percentage,
                'used_formatted' => $this->formatBytes($usage->total_bytes_used),
                'quota_formatted' => $this->formatBytes($usage->quota_bytes),
            ],
        ]);
    }

    protected function formatBytes(int|float $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }
}
