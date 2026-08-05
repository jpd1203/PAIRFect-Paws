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
        if (Schema::hasTable('pets') && !Schema::hasColumn('pets', 'age_months')) {
            Schema::table('pets', function (Blueprint $table) {
                $table->integer('age_months')->nullable()->after('age_years');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pets') && Schema::hasColumn('pets', 'age_months')) {
            Schema::table('pets', function (Blueprint $table) {
                $table->dropColumn('age_months');
            });
        }
    }
};
