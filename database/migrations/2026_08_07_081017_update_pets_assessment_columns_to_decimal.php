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
        Schema::table('pets', function (Blueprint $table) {
            $table->decimal('energy_level', 3, 1)->nullable()->change();
            $table->decimal('trainability', 3, 1)->nullable()->change();
            $table->decimal('independence', 3, 1)->nullable()->change();
            $table->decimal('temperament', 3, 1)->nullable()->change();
            $table->string('last_assessed_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->integer('energy_level')->nullable()->change();
            $table->integer('trainability')->nullable()->change();
            $table->integer('independence')->nullable()->change();
            $table->integer('temperament')->nullable()->change();
            $table->dropColumn('last_assessed_by');
        });
    }
};
