<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('post_adoption_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adoption_application_id')->constrained('adoption_applications')->cascadeOnDelete();
            $table->string('milestone');              // ThreeDays, ThreeWeeks, ThreeMonths
            $table->date('scheduled_date');
            $table->timestamp('submitted_date')->nullable();
            // Welfare report fields
            $table->string('pet_current_status')->nullable();  // Good, Fair, Poor
            $table->text('behavioral_observations')->nullable();
            $table->text('living_conditions')->nullable();
            $table->text('eating_habits')->nullable();
            $table->text('vet_visit_details')->nullable();
            $table->text('concerns')->nullable();
            $table->string('photo_path')->nullable();
            // Flagging
            $table->boolean('flagged_for_review')->default(false);
            $table->unsignedInteger('reminders_sent')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('post_adoption_logs');
    }
};

