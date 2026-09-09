<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Administrator accounts
        $admin = User::firstOrCreate(
            ['email' => 'admin@mystorage.local'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password123'),
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );
        if ($admin->role !== UserRole::ADMIN) {
            $admin->update(['role' => UserRole::ADMIN]);
        }

        $adminSite = User::firstOrCreate(
            ['email' => 'admin@simpan.site'],
            [
                'name' => 'Administrator Simpan',
                'password' => bcrypt('password123'),
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );
        if ($adminSite->role !== UserRole::ADMIN) {
            $adminSite->update(['role' => UserRole::ADMIN]);
        }

        // Seed Default Settings (WhatsApp Gateway & SMTP Mail)
        $defaultSettings = [
            'wa_notifications_enabled' => '1',
            'wa_gateway_url'           => 'http://127.0.0.1:3000',
            'mail_mailer'              => 'smtp',
            'mail_host'                => 'smtp.gmail.com',
            'mail_port'                => '587',
            'mail_username'            => '',
            'mail_password'            => '',
            'mail_encryption'          => 'tls',
            'mail_from_address'        => 'no-reply@simpan.site',
            'mail_from_name'           => 'MyStorage Cloud',
        ];

        foreach ($defaultSettings as $key => $value) {
            if (Setting::where('key', $key)->doesntExist()) {
                Setting::create(['key' => $key, 'value' => $value]);
            }
        }
    }
}
