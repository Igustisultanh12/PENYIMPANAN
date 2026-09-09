<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
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

    public function test_non_admin_cannot_access_admin_settings(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/v1/admin/settings');

        $response->assertStatus(403);
    }

    public function test_admin_can_retrieve_settings(): void
    {
        Setting::set('wa_gateway_url', 'http://127.0.0.1:3000');
        Setting::set('wa_notifications_enabled', '1');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/settings');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'wa_gateway_url' => 'http://127.0.0.1:3000',
                    'wa_notifications_enabled' => '1',
                ],
            ]);
    }

    public function test_admin_can_update_settings(): void
    {
        $payload = [
            'wa_notifications_enabled' => '0',
            'wa_gateway_url' => 'http://192.168.1.100:3000',
            'mail_host' => 'smtp.mailtrap.io',
            'mail_port' => '2525',
            'mail_username' => 'testuser',
            'mail_password' => 'secret123',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'cloud@simpan.site',
            'mail_from_name' => 'Simpan Cloud',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/settings', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('0', Setting::get('wa_notifications_enabled'));
        $this->assertEquals('http://192.168.1.100:3000', Setting::get('wa_gateway_url'));
        $this->assertEquals('smtp.mailtrap.io', Setting::get('mail_host'));
    }

    public function test_whatsapp_gateway_status_endpoint(): void
    {
        Http::fake([
            'http://127.0.0.1:3000/status-wa' => Http::response([
                'status' => true,
                'connected' => true,
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/whatsapp/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'ONLINE',
                    'connected' => true,
                ],
            ]);
    }

    public function test_send_test_whatsapp(): void
    {
        Setting::set('wa_notifications_enabled', '1');
        Setting::set('wa_gateway_url', 'http://127.0.0.1:3000');

        Http::fake([
            'http://127.0.0.1:3000/send*' => Http::response([
                'status' => true,
                'message' => 'Pesan terkirim',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/whatsapp/test', [
                'target_phone' => '081234567890',
                'message' => 'Halo tes',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_send_test_mail(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/mail/test', [
                'test_email' => 'admin@simpan.site',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_reset_whatsapp_session(): void
    {
        Http::fake([
            'http://127.0.0.1:3000/reset-session' => Http::response([
                'status' => 'success',
                'message' => 'Sesi di-reset',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/whatsapp/reset');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
