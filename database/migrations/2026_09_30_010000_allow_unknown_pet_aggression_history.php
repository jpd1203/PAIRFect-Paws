<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing true/false values are left untouched: their provenance cannot
        // be determined from the old column. New pets start as unknown.
        Schema::table('pets', function (Blueprint $table) {
            $table->boolean('has_aggression_history')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('pets')->whereNull('has_aggression_history')->exists()) {
            throw new RuntimeException('Cannot restore a non-null aggression default while pets have unverified aggression history.');
        }

        Schema::table('pets', function (Blueprint $table) {
            $table->boolean('has_aggression_history')->nullable(false)->default(false)->change();
        });
    }
};
