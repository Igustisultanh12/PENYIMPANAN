<?php

namespace Tests\Feature;

use App\Models\FileItem;
use App\Models\Folder;
use App\Models\SyncChange;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesktopSyncAndDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'sync_tester@mystorage.local',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_user_can_register_desktop_client(): void
    {
        $response = $this->postJson('/api/v1/devices/register', [
            'device_id' => 'pc-laptop-9876',
            'device_name' => 'ThinkPad Workstation',
            'platform' => 'windows',
            'client_version' => '1.0.0',
            'settings' => [
                'selective_sync' => ['uuid-1', 'uuid-2'],
                'pause_on_battery' => true,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'device_id' => 'pc-laptop-9876',
                    'device_name' => 'ThinkPad Workstation',
                    'platform' => 'windows',
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->id,
            'device_id' => 'pc-laptop-9876',
        ]);
    }

    public function test_desktop_client_receives_incremental_change_feed(): void
    {
        // 1. Create a folder (which triggers SyncChangeLogger)
        $folderResponse = $this->postJson('/api/v1/folders', [
            'name' => 'Sync Projects',
        ]);
        $folderResponse->assertStatus(201);
        $folderUuid = $folderResponse->json('data.uuid');

        // 2. Query sync changes
        $syncResponse = $this->getJson('/api/v1/sync/changes?cursor=0');
        $syncResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $changes = $syncResponse->json('data.changes');
        $this->assertNotEmpty($changes);
        $this->assertEquals('folder', $changes[0]['item_type']);
        $this->assertEquals($folderUuid, $changes[0]['item_uuid']);
        $this->assertEquals('created', $changes[0]['change_type']);
    }

    public function test_desktop_checkpoint_updates_last_synced_at(): void
    {
        $device = UserDevice::create([
            'user_id' => $this->user->id,
            'device_id' => 'work-pc-44',
            'device_name' => 'Office PC',
            'platform' => 'windows',
        ]);

        $response = $this->postJson('/api/v1/sync/checkpoint', [
            'device_id' => 'work-pc-44',
            'cursor' => 10,
        ]);

        $response->assertStatus(200);
        $device->refresh();
        $this->assertNotNull($device->last_synced_at);
    }

    public function test_desktop_device_can_be_revoked_safely(): void
    {
        $device = UserDevice::create([
            'user_id' => $this->user->id,
            'device_id' => 'home-pc-12',
            'device_name' => 'Home PC',
            'platform' => 'windows',
            'status' => 'active',
        ]);

        $response = $this->deleteJson('/api/v1/devices/' . $device->uuid);
        $response->assertStatus(200);

        $device->refresh();
        $this->assertEquals('revoked', $device->status);
    }

    public function test_conflict_resolution_keep_cloud(): void
    {
        $file = FileItem::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'owner_id' => $this->user->id,
            'original_name' => 'contract.txt',
            'storage_name' => 'contract.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 100,
            'checksum' => 'checksum-test-1',
            'disk' => 'local',
            'storage_path' => 'tenants/test/contract.txt',
            'version' => 1,
        ]);

        $response = $this->postJson('/api/v1/sync/conflict', [
            'file_uuid' => $file->uuid,
            'strategy' => 'keep_cloud',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'strategy' => 'keep_cloud',
            ]);
    }

    public function test_conflict_resolution_keep_both(): void
    {
        $file = FileItem::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'owner_id' => $this->user->id,
            'original_name' => 'notes.txt',
            'storage_name' => 'notes.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 120,
            'checksum' => 'checksum-test-2',
            'disk' => 'local',
            'storage_path' => 'tenants/test/notes.txt',
            'version' => 1,
        ]);

        $localConflictFile = UploadedFile::fake()->createWithContent('notes.txt', 'Local modified content');

        $response = $this->postJson('/api/v1/sync/conflict', [
            'file_uuid' => $file->uuid,
            'strategy' => 'keep_both',
            'device_name' => 'MacBook-Pro',
            'file' => $localConflictFile,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'strategy' => 'keep_both',
            ]);

        $this->assertDatabaseHas('files', [
            'owner_id' => $this->user->id,
            'extension' => 'txt',
        ]);
    }
}
