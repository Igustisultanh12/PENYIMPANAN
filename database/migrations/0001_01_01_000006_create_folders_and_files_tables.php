<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#3B82F6');
            $table->boolean('is_starred')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'parent_id', 'deleted_at']);
            $table->index(['owner_id', 'is_starred', 'deleted_at']);
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('original_name');
            $table->string('storage_name');
            $table->string('mime_type')->index();
            $table->string('extension', 50)->index();
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->index(); // SHA-256
            $table->string('disk')->default('local');
            $table->string('storage_path');
            $table->enum('visibility', ['private', 'shared', 'public'])->default('private');
            $table->enum('status', ['uploading', 'ready', 'quarantined', 'error'])->default('ready')->index();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_starred')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'folder_id', 'deleted_at']);
            $table->index(['owner_id', 'is_starred', 'deleted_at']);
            $table->index(['owner_id', 'mime_type', 'deleted_at']);
        });

        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_name');
            $table->string('storage_path');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('disk')->default('local');
            $table->timestamps();

            $table->unique(['file_id', 'version_number']);
        });

        Schema::create('file_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->unique()->constrained('files')->cascadeOnDelete();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable(); // seconds for video/audio
            $table->string('thumbnail_path')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_metadata');
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('files');
        Schema::dropIfExists('folders');
    }
};
