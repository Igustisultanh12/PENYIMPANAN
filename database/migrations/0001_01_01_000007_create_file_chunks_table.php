<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_chunks', function (Blueprint $table) {
            $table->id();
            $table->uuid('upload_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('total_size');
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('chunk_size');
            $table->string('checksum', 64)->nullable();
            $table->string('storage_path');
            $table->string('status', 20)->default('pending'); // pending, completed
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['upload_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_chunks');
    }
};
