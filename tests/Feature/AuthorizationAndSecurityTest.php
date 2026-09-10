<?php

namespace Tests\Feature;

use App\Actions\Shares\ShareFileAction;
use App\Enums\FileStatus;
use App\Enums\ShareAccessType;
use App\Enums\SharePermissionType;
use App\Models\FileItem;
use App\Models\StorageUsage;
use App\Models\User;
use App\Services\Notifications\GenericWhatsAppProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_user_a_cannot_access_user_b_file(): void
    {
        $userA = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $userB = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);

        $fileB = FileItem::create([
            'owner_id' => $userB->id,
            'original_name' => 'secret_document.pdf',
            'storage_name' => 'secret_document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
            'checksum' => 'fake_hash',
            'disk' => 'local',
            'storage_path' => 'tenants/b/secret.pdf',
        ]);

        Sanctum::actingAs($userA);

        $response = $this->getJson("/api/v1/files/{$fileB->uuid}");
        $response->assertStatus(404); // Scoped to userA, hence not found
    }

    public function test_user_cannot_bypass_storage_quota(): void
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        StorageUsage::create([
            'user_id' => $user->id,
            'total_bytes_used' => 1000,
            'quota_bytes' => 1000, // Quota fully used
        ]);

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        $response = $this->postJson('/api/v1/files/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_expired_share_link_rejects_access(): void
    {
        $owner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);

        $file = FileItem::create([
            'owner_id' => $owner->id,
            'original_name' => 'shared_presentation.pdf',
            'storage_name' => 'shared_presentation.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 2048,
            'checksum' => 'checksum123',
            'disk' => 'local',
            'storage_path' => 'tenants/owner/pres.pdf',
        ]);

        $shareAction = new ShareFileAction(new GenericWhatsAppProvider());
        $share = $shareAction->execute(
            $owner,
            $file,
            SharePermissionType::VIEWER,
            ShareAccessType::PUBLIC_LINK,
            null,
            null,
            true,
            now()->subHour()->toDateTimeString() // Expired 1 hour ago
        );

        $response = $this->getJson("/api/v1/shares/public/{$share->token}");
        $response->assertStatus(410); // Gone / Expired
    }

    public function test_signed_download_workflow(): void
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        Storage::fake('local');
        Storage::disk('local')->put('tenants/test/file.txt', 'Hello world content');

        $file = FileItem::create([
            'owner_id' => $user->id,
            'original_name' => 'file.txt',
            'storage_name' => 'file.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 19,
            'checksum' => 'checksum123',
            'disk' => 'local',
            'storage_path' => 'tenants/test/file.txt',
            'status' => 'ready',
        ]);

        Sanctum::actingAs($user);

        // 1. Get signed download url
        $res = $this->getJson("/api/v1/files/{$file->uuid}/download");
        $res->assertStatus(200);
        $downloadUrl = $res->json('download_url');
        $this->assertNotEmpty($downloadUrl);

        // 2. Download without auth header using signed url
        $downloadRes = $this->get($downloadUrl);
        $downloadRes->assertStatus(200);
    }

    public function test_unauthenticated_preview_does_not_500(): void
    {
        $response = $this->get('/api/v1/files/some-fake-uuid/preview');
        // Should return 401 Unauthenticated instead of 500 Route [login] not defined
        $response->assertStatus(401);
    }

    public function test_query_token_authenticates_preview(): void
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $token = $user->createToken('test_token')->plainTextToken;

        Storage::fake('local');
        Storage::disk('local')->put('tenants/test/doc.txt', 'Direct preview content');

        $file = FileItem::create([
            'owner_id' => $user->id,
            'original_name' => 'doc.txt',
            'storage_name' => 'doc.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 22,
            'checksum' => 'checksum123',
            'disk' => 'local',
            'storage_path' => 'tenants/test/doc.txt',
            'status' => 'ready',
        ]);

        // Request with ?token= query parameter (simulating img/video/iframe tags)
        $response = $this->get("/api/v1/files/{$file->uuid}/preview?token={$token}");
        $response->assertStatus(200);
    }
}
