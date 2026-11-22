<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_placements', function (Blueprint $table) {
            $table->json('handover_schedule')->nullable();
            $table->text('handover_receipt')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_mime_type')->nullable();
            $table->string('document_type')->nullable();
            $table->timestamp('document_uploaded_at')->nullable();
            $table->decimal('document_match_score', 5, 4)->nullable();
        });
        Schema::table('research_checkins', function (Blueprint $table) {
            $table->string('video_path')->nullable();
            $table->char('video_sha256', 64)->nullable()->unique();
            $table->string('video_mime_type')->nullable();
        });
        Schema::create('research_capture_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('placement_id')->constrained('research_placements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('milestone', 16);
            $table->char('token_hash', 64);
            $table->char('session_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->char('video_sha256', 64)->nullable();
            $table->timestamps();
            $table->index(['placement_id', 'milestone', 'consumed_at'], 'research_capture_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_capture_challenges');
        Schema::table('research_checkins', function (Blueprint $table) {
            $table->dropUnique(['video_sha256']);
            $table->dropColumn(['video_path', 'video_sha256', 'video_mime_type']);
        });
        Schema::table('research_placements', fn (Blueprint $table) => $table->dropColumn([
            'handover_schedule', 'handover_receipt', 'document_path', 'document_mime_type',
            'document_type', 'document_uploaded_at', 'document_match_score',
        ]));
    }
};
