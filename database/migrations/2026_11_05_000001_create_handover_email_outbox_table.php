<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handover_email_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('handover_id')->constrained('handovers')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_key', 64)->unique();
            $table->string('kind', 60);
            $table->string('audience', 20);
            $table->unsignedInteger('schedule_version');
            $table->unsignedInteger('release_attempt');
            $table->string('schedule_fingerprint', 64);
            $table->text('payload'); // encrypted:array, including the recipient address
            $table->uuid('enqueue_token')->nullable();
            $table->timestamp('dispatch_lease_expires_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->unsignedInteger('queue_attempts')->default(0);
            $table->unsignedInteger('delivery_attempts')->default(0);
            $table->timestamp('transport_accepted_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->string('suppression_reason', 60)->nullable();
            $table->string('last_queue_error')->nullable();
            $table->string('last_transport_error')->nullable();
            $table->timestamps();
            $table->index(['transport_accepted_at', 'suppressed_at', 'dispatch_lease_expires_at'], 'handover_outbox_pending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_email_outbox');
    }
};
