<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RecoveryCode;
use App\Models\TwoFactorAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SecurityController extends Controller
{
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => date('Y-m-d H:i:s', $session->last_activity),
                    'is_current' => request()->session()->getId() === $session->id,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function logoutOtherDevices(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete all Sanctum personal access tokens except current
        $currentId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentId)->delete();

        // Also clean up session table
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', request()->session()->getId())
            ->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'security.logout_other_devices',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Semua sesi perangkat lain telah berhasil di-logout.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kata sandi saat ini tidak cocok.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'security.password_changed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diubah.',
        ]);
    }

    public function setupTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        // Simple secret string for TOTP
        $secret = strtoupper(Str::random(16));

        $twoFactor = TwoFactorAuth::updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => $secret,
                'is_enabled' => false,
                'confirmed_at' => null,
            ]
        );

        $appName = urlencode(config('app.name', 'MyStorage'));
        $otpUrl = "otpauth://totp/{$appName}:{$user->email}?secret={$secret}&issuer={$appName}";

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret,
                'otp_url' => $otpUrl,
            ],
        ]);
    }

    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $twoFactor = $user->twoFactor;

        if (!$twoFactor) {
            return response()->json([
                'success' => false,
                'message' => 'Setup 2FA belum diinisialisasi.',
            ], 400);
        }

        // Generate 8 backup recovery codes
        $recoveryCodes = [];
        $rawCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $code = strtoupper(Str::random(4) . '-' . Str::random(4));
            $rawCodes[] = $code;
            $recoveryCodes[] = [
                'user_id' => $user->id,
                'code' => Hash::make($code),
                'created_at' => now(),
            ];
        }

        RecoveryCode::where('user_id', $user->id)->delete();
        RecoveryCode::insert($recoveryCodes);

        $twoFactor->update([
            'is_enabled' => true,
            'confirmed_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'security.2fa_enabled',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Autentikasi Dua Langkah (2FA) berhasil diaktifkan.',
            'recovery_codes' => $rawCodes,
        ]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kata sandi salah.',
            ], 422);
        }

        if ($user->twoFactor) {
            $user->twoFactor->delete();
        }

        RecoveryCode::where('user_id', $user->id)->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'security.2fa_disabled',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '2FA berhasil dinonaktifkan.',
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $logs = AuditLog::where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function apiTokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->select('id', 'name', 'abilities', 'last_used_at', 'created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    public function createApiToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['nullable', 'array'],
        ]);

        $abilities = $validated['abilities'] ?? ['files:read', 'files:write', 'storage:read'];
        $tokenResult = $request->user()->createToken($validated['name'], $abilities);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'api_token.create',
            'metadata' => ['token_name' => $validated['name']],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API Token berhasil dibuat. Harap salin sekarang karena tidak akan ditampilkan lagi.',
            'data' => [
                'token' => $tokenResult->plainTextToken,
                'name' => $validated['name'],
                'abilities' => $abilities,
            ],
        ], 201);
    }

    public function revokeApiToken(Request $request, int $tokenId): JsonResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'API token berhasil dicabut.',
        ]);
    }
}
