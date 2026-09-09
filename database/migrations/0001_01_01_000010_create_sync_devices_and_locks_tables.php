<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id')->index(); // Unique per physical device installation
            $table->string('device_name');
            $table->string('platform', 50)->default('windows'); // windows, macos, linux
            $table->string('client_version', 50)->default('1.0.0');
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['active', 'revoked'])->default('active')->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('settings')->nullable(); // selective sync folder UUIDs, bandwidth limits, etc.
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });

        Schema::create('sync_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id')->nullable()->index(); // device that initiated the change, if any
            $table->enum('item_type', ['file', 'folder'])->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->uuid('item_uuid')->index();
            $table->enum('change_type', ['created', 'updated', 'deleted', 'restored', 'moved'])->index();
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('version')->nullable();
            $table->string('parent_uuid')->nullable()->index();
            $table->json('metadata')->nullable(); // name, size, mime_type, etc.
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });

        Schema::create('document_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id')->nullable();
            $table->string('lock_token', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['file_id', 'expires_at']);
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['document', 'spreadsheet', 'presentation'])->index();
            $table->string('extension', 20); // docx, xlsx, pptx
            $table->string('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('office_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_token', 64)->unique();
            $table->enum('document_type', ['document', 'spreadsheet', 'presentation'])->index();
            $table->enum('mode', ['edit', 'view'])->default('edit');
            $table->longText('draft_content')->nullable(); // JSON or XML document state for instant web editor
            $table->timestamp('last_autosave_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['file_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_sessions');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_locks');
        Schema::dropIfExists('sync_changes');
        Schema::dropIfExists('user_devices');
    }
};
