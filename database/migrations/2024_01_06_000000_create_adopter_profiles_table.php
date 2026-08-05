<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Stores the "Pet Recommendation" intake answers (separate from the
        // formal AdoptionApplication) used purely to seed the KNN match sliders.
        Schema::create('adopter_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('physical_activity_level'); // Very Active .. Inactive
            $table->string('time_availability');        // Very Limited (0-1 hr/day) .. Highly Available (8+ hrs/day)
            $table->string('prior_pet_experience');      // No Experience .. Advanced / Expert
            $table->string('housing_type');              // Apartment | Condominium | House with yard
            $table->string('household_composition');
            $table->string('monthly_income_range');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adopter_profiles');
    }
};
