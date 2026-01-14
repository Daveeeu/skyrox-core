<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hytale_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hytale_user_id')->constrained('hytale_users')->onDelete('cascade');
            $table->string('session_id')->unique();
            $table->string('server_name')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->enum('device_type', ['desktop', 'mobile', 'tablet', 'unknown'])->default('unknown');
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at');
            $table->timestamp('terminated_at')->nullable();
            $table->string('termination_reason')->nullable();
            $table->json('session_data')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('session_id');
            $table->index('hytale_user_id');
            $table->index('is_active');
            $table->index('expires_at');
            $table->index('last_activity_at');
            $table->index(['hytale_user_id', 'is_active']);
            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hytale_sessions');
    }
};
