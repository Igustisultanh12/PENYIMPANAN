<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('token', 64)->unique()->index();
            $table->morphs('shareable'); // shareable_type, shareable_id (File or Folder)
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->enum('permission', ['viewer', 'commenter', 'editor'])->default('viewer');
            $table->enum('access_type', ['public_link', 'specific_user', 'specific_whatsapp'])->default('public_link');
            $table->string('target_contact')->nullable(); // email or normalized whatsapp
            $table->string('password')->nullable(); // hashed
            $table->boolean('allow_download')->default(true);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('share_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_id')->constrained('shares')->cascadeOnDelete();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_download')->default(true);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_permissions');
        Schema::dropIfExists('shares');
    }
};
