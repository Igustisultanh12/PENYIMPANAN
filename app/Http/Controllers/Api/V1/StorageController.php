<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Storage\CalculateStorageUsageAction;
use App\Http\Controllers\Controller;
use App\Models\StorageUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StorageController extends Controller
{
    public function __construct(protected CalculateStorageUsageAction $calculateUsageAction) {}

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cached per user in Redis/Cache for 15 minutes, invalidated when uploads or deletes occur
        $stats = Cache::remember("user:{$user->uuid}:storage_stats", 900, function () use ($user) {
            $usage = StorageUsage::firstOrCreate(
                ['user_id' => $user->id],
                ['quota_bytes' => 10737418240] // 10 GB
            );

            $quota = $usage->quota_bytes;
            $used = $usage->total_bytes_used;
            $free = max(0, $quota - $used);
            $percentage = $quota > 0 ? round(($used / $quota) * 100, 2) : 100;

            $status = 'normal';
            if ($percentage >= 100) {
                $status = 'blocked';
            } elseif ($percentage >= 90) {
                $status = 'critical';
            } elseif ($percentage >= 80) {
                $status = 'warning';
            }

            return [
                'quota_bytes' => $quota,
                'used_bytes' => $used,
                'free_bytes' => $free,
                'percentage' => $percentage,
                'status' => $status,
                'categories' => [
                    'images' => [
                        'bytes' => $usage->images_bytes,
                        'formatted' => $this->formatBytes($usage->images_bytes),
                    ],
                    'videos' => [
                        'bytes' => $usage->videos_bytes,
                        'formatted' => $this->formatBytes($usage->videos_bytes),
                    ],
                    'documents' => [
                        'bytes' => $usage->documents_bytes,
                        'formatted' => $this->formatBytes($usage->documents_bytes),
                    ],
                    'audio' => [
                        'bytes' => $usage->audio_bytes,
                        'formatted' => $this->formatBytes($usage->audio_bytes),
                    ],
                    'archives' => [
                        'bytes' => $usage->archives_bytes,
                        'formatted' => $this->formatBytes($usage->archives_bytes),
                    ],
                    'other' => [
                        'bytes' => $usage->other_bytes,
                        'formatted' => $this->formatBytes($usage->other_bytes),
                    ],
                ],
                'human_readable' => [
                    'quota' => $this->formatBytes($quota),
                    'used' => $this->formatBytes($used),
                    'free' => $this->formatBytes($free),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function recalculate(Request $request): JsonResponse
    {
        $usage = $this->calculateUsageAction->execute($request->user());
        Cache::forget("user:{$request->user()->uuid}:storage_stats");

        return response()->json([
            'success' => true,
            'message' => 'Penggunaan penyimpanan berhasil dihitung ulang.',
            'data' => $usage,
        ]);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . ($units[$i] ?? 'B');
    }
}
