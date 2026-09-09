<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Services\DeviceManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        protected DeviceManagerService $deviceService
    ) {}

    /**
     * List all registered devices for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $devices = UserDevice::where('user_id', $request->user()->id)
            ->orderBy('last_synced_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    /**
     * Register or update desktop sync client device.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'client_version' => ['nullable', 'string', 'max:50'],
            'settings' => ['nullable', 'array'],
        ]);

        $device = $this->deviceService->registerDevice($request->user(), $validated, $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Perangkat desktop berhasil didaftarkan.',
            'data' => $device,
        ], 201);
    }

    /**
     * Device heartbeat checkin.
     */
    public function heartbeat(Request $request, string $uuid): JsonResponse
    {
        $device = UserDevice::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->deviceService->heartbeat($device, $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat acknowledged.',
            'status' => $device->status,
        ]);
    }

    /**
     * Update device sync configuration (e.g. selective sync folders, bandwidth).
     */
    public function updateSettings(Request $request, string $uuid): JsonResponse
    {
        $device = UserDevice::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $updated = $this->deviceService->updateSettings($device, $validated['settings']);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan perangkat berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * Revoke / unlink desktop device.
     * Note: Server never deletes local files on PC. Only server access is disconnected.
     */
    public function revoke(Request $request, string $uuid): JsonResponse
    {
        $device = UserDevice::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->deviceService->revokeDevice($device);

        return response()->json([
            'success' => true,
            'message' => 'Akses sinkronisasi perangkat berhasil dicabut. Berkas di PC tetap aman tersimpan.',
            'data' => $device,
        ]);
    }
}
