<?php

namespace Tests\Feature;

use App\Models\StorageUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChunkUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_full_chunk_upload_resumable_flow(): void
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        StorageUsage::create([
            'user_id' => $user->id,
            'total_bytes_used' => 0,
            'quota_bytes' => 10737418240,
        ]);

        Sanctum::actingAs($user);

        // 1. Initiate chunk upload
        $totalSize = 10 * 1024 * 1024; // 10MB file
        $chunkSize = 5 * 1024 * 1024; // 5MB chunks -> 2 chunks total

        $initRes = $this->postJson('/api/v1/uploads/initiate', [
            'filename' => 'large_video.mp4',
            'total_size' => $totalSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => 2,
            'mime_type' => 'video/mp4',
        ]);

        $initRes->assertStatus(200);
        $uploadId = $initRes->json('data.upload_id');
        $this->assertNotEmpty($uploadId);

        // 2. Upload Chunk 0
        $chunk0Payload = UploadedFile::fake()->create('large_video.mp4', 5120);
        $chunk0Res = $this->postJson("/api/v1/uploads/{$uploadId}/chunk", [
            'chunk_index' => 0,
            'total_chunks' => 2,
            'total_size' => $totalSize,
            'original_name' => 'large_video.mp4',
            'chunk' => $chunk0Payload,
        ]);
        $chunk0Res->assertStatus(200);

        // 3. Test Resumability Status Check
        $statusRes = $this->getJson("/api/v1/uploads/{$uploadId}/status");
        $statusRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'uploaded_chunks' => [0],
            ]);

        // 4. Upload Chunk 1
        $chunk1Payload = UploadedFile::fake()->create('large_video.mp4', 5120);
        $chunk1Res = $this->postJson("/api/v1/uploads/{$uploadId}/chunk", [
            'chunk_index' => 1,
            'total_chunks' => 2,
            'total_size' => $totalSize,
            'original_name' => 'large_video.mp4',
            'chunk' => $chunk1Payload,
        ]);
        $chunk1Res->assertStatus(200);

        // 5. Finalize assembly
        $finalizeRes = $this->postJson("/api/v1/uploads/{$uploadId}/finalize");
        $finalizeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'original_name' => 'large_video.mp4',
                    'version' => 1,
                ],
            ]);

        $this->assertDatabaseHas('files', [
            'owner_id' => $user->id,
            'original_name' => 'large_video.mp4',
        ]);
    }
}
