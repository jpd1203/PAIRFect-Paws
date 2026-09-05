<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['energy_level', 'trainability', 'independence'] as $column) {
            DB::table('assessment_records')
                ->whereNotNull($column)
                ->whereBetween($column, [0, 4])
                ->update([$column => DB::raw("{$column} + 1")]);

            DB::table('pets')
                ->whereNotNull($column)
                ->whereBetween($column, [0, 4])
                ->update([$column => DB::raw("{$column} + 1")]);
        }

        // Legacy temperament stored direct fearfulness on 0-4. The unified
        // 1-5 scale stores calmness/safety, so the direction must be reversed.
        foreach (['assessment_records', 'pets'] as $table) {
            DB::table($table)
                ->whereNotNull('temperament')
                ->whereBetween('temperament', [0, 4])
                ->update(['temperament' => DB::raw('5 - temperament')]);
        }
    }

    public function down(): void
    {
        foreach (['energy_level', 'trainability', 'independence'] as $column) {
            DB::table('assessment_records')
                ->whereNotNull($column)
                ->whereBetween($column, [1, 5])
                ->update([$column => DB::raw("{$column} - 1")]);

            DB::table('pets')
                ->whereNotNull($column)
                ->whereBetween($column, [1, 5])
                ->update([$column => DB::raw("{$column} - 1")]);
        }

        foreach (['assessment_records', 'pets'] as $table) {
            DB::table($table)
                ->whereNotNull('temperament')
                ->whereBetween('temperament', [1, 5])
                ->update(['temperament' => DB::raw('5 - temperament')]);
        }
    }
};
