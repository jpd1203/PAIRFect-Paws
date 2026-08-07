<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('adoption_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('Pending'); // Pending, UnderReview, InterviewScheduled, Approved, Rejected
            $table->text('motivation_statement')->nullable();
            $table->string('housing_type')->nullable();
            $table->string('income_range')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamp('interview_date')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('adoption_applications');
    }
};

