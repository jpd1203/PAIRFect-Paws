<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fund_records', function (Blueprint $table) {
            $table->id();
            $table->date('recorded_date');
            $table->string('activity');
            $table->decimal('donation_added', 10, 2)->nullable();
            $table->decimal('shelter_spent', 10, 2)->nullable();
            $table->string('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_records');
    }
};
