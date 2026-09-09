<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Str;

class DeviceManagerService
{
    /**
     * Register or update a desktop client device.
     */
    public function registerDevice(User $user, array $data, ?string $ip = null): UserDevice
    {
        $deviceId = $data['device_id'] ?? Str::uuid()->toString();

        $device = UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'device_name' => $data['device_name'] ?? 'Desktop Workstation',
                'platform' => strtolower($data['platform'] ?? 'windows'),
                'client_version' => $data['client_version'] ?? '1.0.0',
                'ip_address' => $ip,
                'status' => 'active',
                'last_synced_at' => now(),
                'settings' => $data['settings'] ?? [
                    'selective_sync' => [],
                    'max_upload_kbps' => 0, // 0 = unlimited
                    'max_download_kbps' => 0,
                    'pause_on_metered' => true,
                    'pause_on_battery' => true,
                ],
            ]
        );

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'device.registered',
            'target_type' => 'UserDevice',
            'target_id' => $device->id,
            'metadata' => [
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'ip' => $ip,
            ],
        ]);

        return $device;
    }

    /**
     * Update device heartbeat timestamp and IP address.
     */
    public function heartbeat(UserDevice $device, ?string $ip = null): void
    {
        $device->update([
            'last_synced_at' => now(),
            'ip_address' => $ip ?: $device->ip_address,
        ]);
    }

    /**
     * Revoke device access.
     * Note: Server never deletes user's local files on PC. Only server sync credentials are invalidated.
     */
    public function revokeDevice(UserDevice $device): void
    {
        $device->update([
            'status' => 'revoked',
        ]);

        AuditLog::create([
            'user_id' => $device->user_id,
            'action' => 'device.revoked',
            'target_type' => 'UserDevice',
            'target_id' => $device->id,
            'metadata' => [
                'device_name' => $device->device_name,
            ],
        ]);
    }

    /**
     * Update selective sync or network bandwidth preferences.
     */
    public function updateSettings(UserDevice $device, array $settings): UserDevice
    {
        $current = $device->settings ?? [];
        $merged = array_merge($current, $settings);

        $device->update([
            'settings' => $merged,
        ]);

        return $device;
    }
}
