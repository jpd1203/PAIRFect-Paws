<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('species'); // Dog, Cat
            $table->string('breed')->nullable();
            $table->integer('age_months')->nullable();
            $table->string('status')->default('Available'); // Available, Soft-Reserved, Adopted
            $table->boolean('is_high_energy')->default(false);
            $table->boolean('is_reactive')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('pets');
    }
};
