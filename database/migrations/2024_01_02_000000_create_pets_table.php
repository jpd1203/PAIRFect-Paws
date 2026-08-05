<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('species'); // Dog | Cat
            $table->string('breed');
            $table->string('age_group'); // Baby | Young | Adult | Senior
            $table->unsignedTinyInteger('age_years')->nullable();
            $table->string('sex'); // Male | Female
            $table->date('intake_date');
            $table->string('health_status');
            $table->string('vaccination_records');
            $table->string('status')->default('Available'); // Available | Pending | Adopted
            $table->string('image_path')->nullable();

            // KNN behavioral feature vector (1-5 scale), mirrors the adopter intake fields
            $table->unsignedTinyInteger('energy_level')->default(3);
            $table->unsignedTinyInteger('independence_level')->default(3);
            $table->unsignedTinyInteger('trainability')->default(3);
            $table->unsignedTinyInteger('medical_needs')->default(1);
            $table->unsignedTinyInteger('temperament')->default(3); // 1=needs work .. 5=exceptionally gentle
            $table->string('physical_size')->default('Medium'); // Small | Medium | Large

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
