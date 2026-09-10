<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\StorageUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->regularUser = User::factory()->create([
            'role' => UserRole::USER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_list_users_with_storage(): void
    {
        StorageUsage::create([
            'user_id' => $this->regularUser->id,
            'total_bytes_used' => 1024 * 1024 * 50, // 50MB
            'quota_bytes' => 1024 * 1024 * 1024 * 15, // 15GB
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'storage' => [
                                'used_bytes',
                                'quota_bytes',
                                'quota_gb',
                                'percentage',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_admin_can_update_user_quota(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$this->regularUser->id}/quota", [
                'quota_gb' => 50,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quota_gb', 50);

        $this->assertDatabaseHas('storage_usage', [
            'user_id' => $this->regularUser->id,
            'quota_bytes' => 50 * 1024 * 1024 * 1024,
        ]);
    }

    public function test_admin_can_view_ssd_health(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/system/ssd-health');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'disk' => [
                        'total_bytes',
                        'used_bytes',
                        'free_bytes',
                        'used_percentage',
                    ],
                    'ssd' => [
                        'health_percentage',
                        'health_status',
                        'smart_status',
                    ],
                ],
            ]);
    }

    public function test_admin_can_run_speedtest(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/system/speedtest');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'download_mbps',
                    'upload_mbps',
                    'ping_ms',
                    'isp',
                ],
            ]);
    }

    public function test_admin_can_set_default_storage_quota(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/settings', [
                'default_storage_quota_gb' => 25,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('25', Setting::get('default_storage_quota_gb'));
    }
}
