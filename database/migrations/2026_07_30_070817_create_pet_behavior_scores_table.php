<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pet_behavior_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            // standardized 5-level scale for KNN
            $table->integer('energy_level')->default(3); // 1-5
            $table->integer('sociability')->default(3); // 1-5
            $table->integer('trainability')->default(3); // 1-5
            $table->integer('adaptability')->default(3); // 1-5
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('pet_behavior_scores');
    }
};
