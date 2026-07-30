<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('adopter_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('housing_type'); // Apartment, House
            $table->boolean('has_yard')->default(false);
            $table->boolean('has_other_pets')->default(false);
            // standardized 5-level scale for KNN
            $table->integer('activity_level')->default(3); // 1-5
            $table->integer('patience_level')->default(3); // 1-5
            $table->integer('experience_level')->default(3); // 1-5
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('adopter_profiles');
    }
};
