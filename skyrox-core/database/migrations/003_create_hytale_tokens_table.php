<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hytale_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hytale_user_id')->constrained('hytale_users')->onDelete('cascade');
            $table->enum('type', ['access_token', 'refresh_token', 'id_token']);
            $table->string('token_id')->unique();
            $table->string('token_hash')->unique();
            $table->text('encrypted_token');
            $table->text('scope')->nullable();
            $table->string('audience')->nullable();
            $table->string('issuer')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('not_before_at')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('token_id');
            $table->index('token_hash');
            $table->index('hytale_user_id');
            $table->index('type');
            $table->index('is_revoked');
            $table->index('expires_at');
            $table->index(['hytale_user_id', 'type']);
            $table->index(['type', 'is_revoked']);
            $table->index(['is_revoked', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hytale_tokens');
    }
};
