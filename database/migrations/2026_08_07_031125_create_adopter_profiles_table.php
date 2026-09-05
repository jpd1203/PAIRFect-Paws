<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('adopter_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // BFI-2 standardized variables for KNN matching
            $table->tinyInteger('activity_level')->nullable();
            $table->tinyInteger('housing_type')->nullable(); // mapped to an integer scale
            $table->tinyInteger('has_children')->nullable(); // mapped to an integer scale
            $table->tinyInteger('financial_readiness')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adopter_profiles');
    }
};
