<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\GoogleAuthAction;
use App\Actions\Auth\RegisterAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        protected RegisterAction $registerAction,
        protected GoogleAuthAction $googleAuthAction
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
            'terms' => ['accepted'],
        ]);

        $user = $this->registerAction->execute($validated);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Silakan periksa email Anda untuk memverifikasi akun.',
            'data' => [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'status' => $user->status->value,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'login:' . $request->ip() . '|' . strtolower($request->email);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            if ($user) {
                SecurityEvent::create([
                    'user_id' => $user->id,
                    'event_type' => 'failed_login',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'severity' => 'warning',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Email atau kata sandi tidak valid.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        if ($user->status === UserStatus::SUSPENDED) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.',
            ], 403);
        }

        // Create Sanctum Token
        $token = $user->createToken('mystorage_web_session')->plainTextToken;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.login',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'whatsapp' => $user->whatsapp,
                    'role' => $user->role->value,
                    'status' => $user->status->value,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'avatar_url' => $user->avatar_url,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'auth.logout',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['storageUsage', 'profile', 'twoFactor']);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp' => $user->whatsapp,
                'role' => $user->role->value,
                'status' => $user->status->value,
                'email_verified' => $user->hasVerifiedEmail(),
                'avatar_url' => $user->avatar_url,
                'two_factor_enabled' => (bool) ($user->twoFactor?->is_enabled),
                'storage' => [
                    'used_bytes' => $user->storageUsage?->total_bytes_used ?? 0,
                    'quota_bytes' => $user->storageUsage?->quota_bytes ?? 10737418240,
                    'usage_percentage' => $user->storageUsage?->usage_percentage ?? 0,
                ],
                'profile' => $user->profile,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'whatsapp' => $validated['whatsapp'] ?? $user->whatsapp,
        ]);

        if (isset($validated['timezone']) || isset($validated['locale'])) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
                    'locale' => $validated['locale'] ?? 'id',
                ]
            );
        }

        $user->load(['storageUsage', 'profile', 'twoFactor']);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp' => $user->whatsapp,
                'role' => $user->role->value,
                'status' => $user->status->value,
                'email_verified' => $user->hasVerifiedEmail(),
                'avatar_url' => $user->avatar_url,
                'two_factor_enabled' => (bool) ($user->twoFactor?->is_enabled),
                'storage' => [
                    'used_bytes' => $user->storageUsage?->total_bytes_used ?? 0,
                    'quota_bytes' => $user->storageUsage?->quota_bytes ?? 10737418240,
                    'usage_percentage' => $user->storageUsage?->usage_percentage ?? 0,
                ],
                'profile' => $user->profile,
            ],
        ]);
    }

    public function verifyEmail(string $token): JsonResponse
    {
        $hashedToken = hash('sha256', $token);

        $user = User::where('verification_token', $hashedToken)
            ->where('verification_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan verifikasi tidak valid atau telah kedaluwarsa.',
            ], 400);
        }

        $user->update([
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
            'verification_token' => null,
            'verification_token_expires_at' => null,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.email_verified',
            'metadata' => ['email' => $user->email],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alamat email berhasil diverifikasi. Akun Anda kini aktif.',
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $throttleKey = 'resend_verification:' . strtolower($request->email);
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Silakan tunggu {$seconds} detik sebelum meminta kirim ulang email verifikasi.",
            ], 429);
        }

        RateLimiter::hit($throttleKey, 600); // 10 minutes

        $user = User::where('email', strtolower($request->email))->first();

        if ($user && !$user->hasVerifiedEmail()) {
            $rawToken = \Illuminate\Support\Str::random(64);
            $user->update([
                'verification_token' => hash('sha256', $rawToken),
                'verification_token_expires_at' => now()->addHours(24),
            ]);

            $user->notify(new VerifyEmailNotification($rawToken));
        }

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar dan belum diverifikasi, tautan verifikasi baru telah dikirimkan.',
        ]);
    }

    public function googleRedirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function googleCallback(Request $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = $this->googleAuthAction->execute($googleUser);

            $token = $user->createToken('mystorage_google_auth')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Autentikasi Google berhasil.',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar_url' => $user->avatar_url,
                        'role' => $user->role->value,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi Google gagal: ' . $e->getMessage(),
            ], 400);
        }
    }
}
