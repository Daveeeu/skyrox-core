<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hytale_users', function (Blueprint $table) {
            $table->id();
            $table->string('hytale_uuid')->unique()->nullable();
            $table->string('hytale_player_id')->nullable();
            $table->string('username')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('display_name')->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('auth0_user_id')->unique();
            $table->string('locale', 2)->default('en');
            $table->string('timezone')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->unsignedInteger('login_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->json('profile_data')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('hytale_uuid');
            $table->index('username');
            $table->index('email');
            $table->index('auth0_user_id');
            $table->index('is_active');
            $table->index('last_login_at');
            $table->index(['is_active', 'last_login_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hytale_users');
    }
};
