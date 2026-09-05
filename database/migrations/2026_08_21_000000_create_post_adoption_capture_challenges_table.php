<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_adoption_capture_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('post_adoption_log_id')
                ->constrained('post_adoption_logs')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('session_hash', 64);
            $table->char('photo_sha256', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['post_adoption_log_id', 'user_id', 'expires_at'],
                'post_adoption_capture_challenges_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_adoption_capture_challenges');
    }
};
