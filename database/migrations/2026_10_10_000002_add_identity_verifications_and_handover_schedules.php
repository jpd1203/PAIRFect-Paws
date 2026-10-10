<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('adoption_applications')->cascadeOnDelete();
            $table->string('stage', 30);
            $table->string('verification_method', 30);
            foreach (['government_id_presented', 'applicant_matches_id_photo', 'name_matches_application', 'submitted_document_consistent', 'no_material_discrepancy'] as $check) {
                $table->boolean($check)->default(false);
            }
            $table->string('status', 20);
            $table->text('discrepancy_note')->nullable();
            $table->string('document_fingerprint', 64);
            $table->unsignedInteger('release_attempt')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->index(['application_id', 'stage', 'id']);
        });
        Schema::table('handovers', function (Blueprint $table): void {
            $table->string('scheduled_method', 20)->nullable();
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->string('schedule_status', 30)->default('unscheduled');
            $table->unsignedInteger('schedule_version')->default(0);
            $table->dateTime('schedule_confirmed_at')->nullable();
            $table->foreignId('schedule_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('reschedule_options')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->string('reschedule_status', 20)->nullable();
            $table->dateTime('reschedule_requested_at')->nullable();
            $table->dateTime('reschedule_reviewed_at')->nullable();
            $table->foreignId('reschedule_reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            foreach (['schedule_24h_reminder_sent_at', 'schedule_2h_reminder_sent_at', 'receipt_reminder_sent_at', 'follow_up_flagged_at', 'missed_pickup_notified_at'] as $field) {
                $table->dateTime($field)->nullable();
            }
            $table->index(['schedule_status', 'scheduled_start_at']);
        });
    }

    public function down(): void
    {
        Schema::table('handovers', function (Blueprint $table): void {
            $table->dropForeign(['schedule_confirmed_by_user_id']);
            $table->dropForeign(['reschedule_reviewed_by_user_id']);
            $table->dropIndex(['schedule_status', 'scheduled_start_at']);
            $table->dropColumn(['scheduled_method', 'scheduled_start_at', 'scheduled_end_at', 'schedule_status', 'schedule_version', 'schedule_confirmed_at', 'schedule_confirmed_by_user_id', 'reschedule_options', 'reschedule_reason', 'reschedule_status', 'reschedule_requested_at', 'reschedule_reviewed_at', 'reschedule_reviewed_by_user_id', 'schedule_24h_reminder_sent_at', 'schedule_2h_reminder_sent_at', 'receipt_reminder_sent_at', 'follow_up_flagged_at', 'missed_pickup_notified_at']);
        });
        Schema::dropIfExists('identity_verifications');
    }
};
