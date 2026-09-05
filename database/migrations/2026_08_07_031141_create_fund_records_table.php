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
        Schema::create('fund_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // The staff who recorded it, or null if online donation
            $table->decimal('amount', 10, 2);
            $table->string('transaction_type'); // 'Donation' or 'Expense'
            $table->string('source_or_destination'); // e.g., 'PayMongo', 'Veterinary Clinic', 'Manual Cash'
            $table->string('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_records');
    }
};
