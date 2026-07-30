<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('PHP');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->string('paymongo_reference')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('donations');
    }
};
