<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('species');                    // Cat, Dog
            $table->string('breed')->nullable();
            $table->integer('age')->nullable();           // in years or months
            $table->string('sex')->nullable();            // Male, Female
            $table->string('health_status')->nullable();
            $table->text('behavioral_notes')->nullable();
            $table->string('availability_status')->default('Available'); // Available, Processing, Adopted
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('intake_date')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('pets');
    }
};

