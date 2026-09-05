<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adopter_profiles', function (Blueprint $table) {
            $table->renameColumn('housing_type', 'legacy_housing_score');
        });

        Schema::table('adopter_profiles', function (Blueprint $table) {
            $table->string('physical_activity_level')->nullable()->after('user_id');
            $table->string('time_availability')->nullable()->after('physical_activity_level');
            $table->string('prior_pet_experience')->nullable()->after('time_availability');
            $table->string('housing_type')->nullable()->after('prior_pet_experience');
            $table->string('household_composition')->nullable()->after('housing_type');
            $table->string('monthly_income_range')->nullable()->after('household_composition');
        });
    }

    public function down(): void
    {
        Schema::table('adopter_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'physical_activity_level',
                'time_availability',
                'prior_pet_experience',
                'housing_type',
                'household_composition',
                'monthly_income_range',
            ]);
        });

        Schema::table('adopter_profiles', function (Blueprint $table) {
            $table->renameColumn('legacy_housing_score', 'housing_type');
        });
    }
};
