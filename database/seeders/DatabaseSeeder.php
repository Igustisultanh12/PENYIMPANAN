<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@mystorage.local'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password123'),
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );
    }
}
