<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            // 6th KNN dimension — manually set by staff during assessment
            $table->decimal('medical_needs', 3, 1)->nullable()->after('temperament');

            // Business-logic filter flags — set during behavioural assessment
            $table->boolean('is_reactive_to_pets')->default(false)->after('medical_needs');
            $table->boolean('has_aggression_history')->default(false)->after('is_reactive_to_pets');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['medical_needs', 'is_reactive_to_pets', 'has_aggression_history']);
        });
    }
};
