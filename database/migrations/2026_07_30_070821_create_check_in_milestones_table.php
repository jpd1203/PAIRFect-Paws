<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('check_in_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('milestone_type'); // 3-day, 3-week, 3-month
            $table->timestamp('scheduled_for');
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('Pending'); // Pending, Completed, Missed
            $table->boolean('c2pa_verified')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('check_in_milestones');
    }
};
