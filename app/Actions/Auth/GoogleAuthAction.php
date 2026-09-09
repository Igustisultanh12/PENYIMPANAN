<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\OAuthAccount;
use App\Models\StorageUsage;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleAuthAction
{
    public function execute(SocialiteUser $googleUser): User
    {
        $googleId = $googleUser->getId();
        $email = strtolower($googleUser->getEmail());

        // 1. Check if oauth_accounts already has this Google ID
        $oauthAccount = OAuthAccount::where('provider', 'google')
            ->where('provider_id', $googleId)
            ->first();

        if ($oauthAccount) {
            $user = $oauthAccount->user;

            // Auto-verify if not verified
            if (!$user->email_verified_at) {
                $user->update([
                    'email_verified_at' => now(),
                    'status' => UserStatus::ACTIVE,
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'auth.google_login',
                'target_type' => 'User',
                'target_id' => $user->id,
                'metadata' => ['provider' => 'google'],
            ]);

            return $user;
        }

        // 2. Check if a user already exists with this email
        $user = User::where('email', $email)->first();

        if ($user) {
            // Link Google account to existing user
            OAuthAccount::create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_id' => $googleId,
                'provider_email' => $email,
                'provider_avatar' => $googleUser->getAvatar(),
            ]);

            if (!$user->email_verified_at) {
                $user->update([
                    'email_verified_at' => now(),
                    'status' => UserStatus::ACTIVE,
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'auth.google_linked',
                'target_type' => 'User',
                'target_id' => $user->id,
                'metadata' => ['provider' => 'google'],
            ]);

            return $user;
        }

        // 3. Create brand new user via Google
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $googleUser->getName() ?: 'User ' . Str::random(6),
            'email' => $email,
            'whatsapp' => null,
            'password' => bcrypt(Str::random(32)), // Random secure password
            'status' => UserStatus::ACTIVE,
            'role' => UserRole::USER,
            'email_verified_at' => now(),
            'avatar_url' => $googleUser->getAvatar(),
        ]);

        OAuthAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => $googleId,
            'provider_email' => $email,
            'provider_avatar' => $googleUser->getAvatar(),
        ]);

        UserProfile::create([
            'user_id' => $user->id,
            'locale' => 'id',
            'timezone' => 'Asia/Jakarta',
        ]);

        StorageUsage::create([
            'user_id' => $user->id,
            'quota_bytes' => 10737418240, // 10 GB
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.google_register',
            'target_type' => 'User',
            'target_id' => $user->id,
            'metadata' => ['provider' => 'google', 'email' => $email],
        ]);

        return $user;
    }
}
