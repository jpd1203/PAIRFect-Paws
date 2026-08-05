<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pet_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assessed_by_name'); // snapshot, survives account deletion
            $table->unsignedTinyInteger('assessment_number'); // 1, 2, or 3 — max 3 per pet
            $table->string('species'); // Dog | Cat — determines which question set was used

            // Category averages (0-4 scale), computed from the raw answers at submit time
            $table->decimal('energy_level_avg', 3, 2)->default(0);
            $table->decimal('trainability_avg', 3, 2)->default(0);
            $table->decimal('independence_avg', 3, 2)->default(0);
            $table->decimal('temperament_avg', 3, 2)->default(0);

            // Full raw question => rating map, for anyone who needs the detail later
            $table->json('answers');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_assessments');
    }
};
