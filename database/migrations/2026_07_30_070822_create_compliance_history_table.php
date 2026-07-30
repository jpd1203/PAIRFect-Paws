<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('compliance_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('flag_type'); // Missed Deadline, Welfare Concern, C2PA Failure
            $table->text('notes')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('compliance_history');
    }
};
