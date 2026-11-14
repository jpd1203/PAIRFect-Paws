<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handover_transport_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('handover_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('release_attempt');
            $table->string('source', 30);
            $table->string('category', 50);
            $table->text('description');
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reported_at');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->unsignedInteger('version')->default(1);
            $table->dateTime('next_follow_up_at')->nullable();
            $table->dateTime('follow_up_notified_at')->nullable();
            $table->dateTime('max_pending_notified_at')->nullable();
            $table->text('findings')->nullable();
            $table->string('resolution_outcome', 40)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_sha256', 64)->nullable();
            $table->json('release_context');
            $table->json('history');
            $table->timestamps();
            $table->unique(['handover_id', 'release_attempt', 'source', 'category'], 'transport_incident_attempt_source_unique');
            $table->index(['status', 'next_follow_up_at'], 'transport_incident_follow_up_index');
            $table->index(['assigned_to_user_id', 'status'], 'transport_incident_assignee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_transport_incidents');
    }
};
