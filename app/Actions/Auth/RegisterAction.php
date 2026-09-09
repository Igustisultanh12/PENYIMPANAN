<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\StorageUsage;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\VerifyEmailNotification;
use App\Services\Notifications\WhatsAppNotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterAction
{
    public function __construct(protected WhatsAppNotificationService $whatsAppService) {}

    public function execute(array $data): User
    {
        $normalizedPhone = null;
        if (!empty($data['whatsapp'])) {
            $normalizedPhone = $this->whatsAppService->normalizePhoneNumber($data['whatsapp']);

            if (User::where('whatsapp', $normalizedPhone)->exists()) {
                throw ValidationException::withMessages([
                    'whatsapp' => 'The WhatsApp number is already registered.',
                ]);
            }
        }

        $verificationToken = Str::random(64);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'whatsapp' => $normalizedPhone,
            'password' => Hash::make($data['password']),
            'status' => UserStatus::PENDING_VERIFICATION,
            'role' => UserRole::USER,
            'verification_token' => hash('sha256', $verificationToken),
            'verification_token_expires_at' => now()->addHours(24),
        ]);

        // Initialize user profile
        UserProfile::create([
            'user_id' => $user->id,
            'locale' => 'id',
            'timezone' => 'Asia/Jakarta',
        ]);

        // Initialize default storage quota (10GB)
        StorageUsage::create([
            'user_id' => $user->id,
            'quota_bytes' => 10737418240,
        ]);

        // Send queued verification notification
        $user->notify(new VerifyEmailNotification($verificationToken));

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.register',
            'target_type' => 'User',
            'target_id' => $user->id,
            'metadata' => ['email' => $user->email],
        ]);

        return $user;
    }
}
