<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_disks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('driver'); // local, s3, minio
            $table->json('config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('storage_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('total_bytes_used')->default(0);
            $table->unsignedBigInteger('quota_bytes')->default(10737418240); // 10 GB
            $table->unsignedBigInteger('images_bytes')->default(0);
            $table->unsignedBigInteger('videos_bytes')->default(0);
            $table->unsignedBigInteger('documents_bytes')->default(0);
            $table->unsignedBigInteger('audio_bytes')->default(0);
            $table->unsignedBigInteger('archives_bytes')->default(0);
            $table->unsignedBigInteger('other_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_usage');
        Schema::dropIfExists('storage_disks');
    }
};
