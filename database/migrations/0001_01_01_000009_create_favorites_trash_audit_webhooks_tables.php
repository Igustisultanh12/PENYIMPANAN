<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('favorable');
            $table->timestamps();

            $table->unique(['user_id', 'favorable_type', 'favorable_id']);
        });

        Schema::create('trash_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('item_type'); // file or folder
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('original_parent_id')->nullable();
            $table->timestamp('deleted_at');
            $table->timestamp('expires_at')->index(); // 30 days retention
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index(); // login, upload, delete, share, download, etc.
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // e.g., whatsapp, s3
            $table->string('event');
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('received');
            $table->text('response')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('trash_items');
        Schema::dropIfExists('favorites');
    }
};
