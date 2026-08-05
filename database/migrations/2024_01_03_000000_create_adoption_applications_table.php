<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('adoption_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone_number');
            $table->string('address');

            $table->string('housing_type');       // Apartment | Condominium | House with yard
            $table->string('household_composition'); // Lives Alone | Couple Only / Roommates | ...
            $table->string('monthly_income_range');
            $table->string('prior_pet_experience'); // No Experience .. Advanced / Expert
            $table->string('physical_activity_level')->nullable();
            $table->string('time_availability')->nullable();

            $table->string('document_path'); // valid ID / proof of residence, stored privately
            $table->boolean('agreed_to_animal_welfare_act')->default(false);

            $table->unsignedTinyInteger('status')->default(0);
            // 0 Submitted, 1 UnderReview, 2 Interview, 3 Approved, 4 Rejected
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoption_applications');
    }
};
