<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->timestamp('aggression_history_verified_at')->nullable()->after('has_aggression_history');
        });

        // A legacy Yes could not come from the former false default. Version-2
        // raw assessments also required an explicit Yes/No selection. Preserve
        // all other historical No values for review without calling them safe.
        DB::table('pets')
            ->whereNotNull('has_aggression_history')
            ->where(function ($query) {
                $query->where('has_aggression_history', true)
                    ->orWhereExists(function ($assessments) {
                        $assessments->selectRaw('1')
                            ->from('assessment_records')
                            ->whereColumn('assessment_records.pet_id', 'pets.id')
                            ->whereNotNull('assessment_records.responses');
                    });
            })
            ->update(['aggression_history_verified_at' => DB::raw('COALESCE(last_assessed_at, updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn('aggression_history_verified_at');
        });
    }
};
