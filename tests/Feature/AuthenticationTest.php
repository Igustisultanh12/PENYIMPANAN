<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_details(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'whatsapp' => '081234567890',
            'password' => 'SecurePass123!#',
            'password_confirmation' => 'SecurePass123!#',
            'terms' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'budi@example.com',
                    'status' => 'pending_verification',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'whatsapp' => '6281234567890', // Normalized
        ]);
    }

    public function test_user_can_login_and_receive_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('SecurePass123!#'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123!#',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => ['uuid', 'name', 'email'],
                ],
            ]);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'sultan@example.com',
            'name' => 'Old Name',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'I Gusti Sultan',
                'whatsapp' => '08123456789',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'I Gusti Sultan')
            ->assertJsonPath('data.whatsapp', '08123456789');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'I Gusti Sultan',
            'whatsapp' => '08123456789',
        ]);
    }
}
