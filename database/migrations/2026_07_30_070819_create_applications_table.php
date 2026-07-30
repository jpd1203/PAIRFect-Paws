<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('Pending'); // Pending, Interview, Approved, Rejected, Withdrawn
            $table->float('knn_compatibility_score')->nullable();
            $table->timestamp('interview_date')->nullable();
            $table->timestamps(); // Created_at is used for conflict resolution
        });
    }
    public function down(): void {
        Schema::dropIfExists('applications');
    }
};
