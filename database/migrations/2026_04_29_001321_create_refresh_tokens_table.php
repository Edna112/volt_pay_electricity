<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // SHA-256 hex digest (64 chars)
            $table->string('token_hash', 64)->unique();

            $table->foreignId('access_token_id')
                ->constrained('personal_access_tokens')
                ->cascadeOnDelete();

            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();

            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->unsignedBigInteger('replaced_by_id')->nullable()->index();

            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
