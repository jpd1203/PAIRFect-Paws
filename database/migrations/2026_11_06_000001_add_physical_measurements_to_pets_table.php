<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            // Fixed units; nullable legacy data is not a fabricated measurement.
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->decimal('height_cm', 10, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->dropColumn(['weight_kg', 'height_cm']);
        });
    }
};
