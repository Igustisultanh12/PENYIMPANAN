<?php

namespace Tests\Feature;

use App\Models\DocumentLock;
use App\Models\FileItem;
use App\Models\OfficeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficeSuiteAndLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create([
            'email' => 'author1@mystorage.local',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->user2 = User::factory()->create([
            'email' => 'author2@mystorage.local',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_can_fetch_office_templates(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/v1/office/templates');
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_create_new_office_document(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/v1/office/create', [
            'name' => 'Quarterly Strategic Plan',
            'type' => 'document',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'original_name' => 'Quarterly Strategic Plan.docx',
                    'extension' => 'docx',
                    'version' => 1,
                ],
            ]);

        $fileUuid = $response->json('data.uuid');
        $this->assertDatabaseHas('files', [
            'uuid' => $fileUuid,
            'owner_id' => $this->user1->id,
            'version' => 1,
        ]);
    }

    public function test_office_session_and_document_locking(): void
    {
        Sanctum::actingAs($this->user1);

        // 1. Create a spreadsheet
        $createRes = $this->postJson('/api/v1/office/create', [
            'name' => 'Annual Budget',
            'type' => 'spreadsheet',
        ]);
        $createRes->assertStatus(201);
        $fileUuid = $createRes->json('data.uuid');

        // 2. Open session for editing
        $sessionRes = $this->getJson("/api/v1/office/session/{$fileUuid}?mode=edit");
        $sessionRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mode' => 'edit',
                    'document_type' => 'spreadsheet',
                ],
            ]);

        $sessionToken = $sessionRes->json('data.session_token');
        $this->assertNotEmpty($sessionToken);

        // Verify document lock exists
        $file = FileItem::where('uuid', $fileUuid)->first();
        $this->assertDatabaseHas('document_locks', [
            'file_id' => $file->id,
            'user_id' => $this->user1->id,
        ]);

        // 3. User 1 autosaves draft
        $draftRes = $this->postJson("/api/v1/office/draft/{$sessionToken}", [
            'draft' => [
                'rows' => [
                    ['A' => 'Revenue', 'B' => '100000'],
                    ['A' => 'Costs', 'B' => '40000'],
                ],
            ],
        ]);
        $draftRes->assertStatus(200);

        // 4. Commit session -> creates version 2
        $commitRes = $this->postJson("/api/v1/office/commit/{$sessionToken}", [
            'draft' => [
                'rows' => [
                    ['A' => 'Revenue', 'B' => '100000'],
                    ['A' => 'Costs', 'B' => '40000'],
                ],
            ],
        ]);
        $commitRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 2,
                ],
            ]);

        $this->assertEquals(2, $file->fresh()->version);
    }

    public function test_document_lock_concurrency(): void
    {
        Sanctum::actingAs($this->user1);

        // User 1 creates presentation
        $createRes = $this->postJson('/api/v1/office/create', [
            'name' => 'Investor Pitch Deck',
            'type' => 'presentation',
        ]);
        $fileUuid = $createRes->json('data.uuid');

        // User 1 locks the document
        $lockRes = $this->postJson("/api/v1/office/lock/{$fileUuid}");
        $lockRes->assertStatus(200);

        // User 2 tries to acquire lock on user 1's document
        Sanctum::actingAs($this->user2);
        $file = FileItem::where('uuid', $fileUuid)->first();
        // Give user 2 read permission or test lock endpoint
        $user2LockRes = $this->postJson("/api/v1/office/lock/{$fileUuid}");
        // Should return 404 because user2 is not owner (or 423 if shared)
        // User 1's lock is intact
        $this->assertDatabaseHas('document_locks', [
            'file_id' => $file->id,
            'user_id' => $this->user1->id,
        ]);
    }
}
