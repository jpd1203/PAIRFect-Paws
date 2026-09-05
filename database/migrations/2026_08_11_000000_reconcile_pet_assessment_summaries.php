<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pets')->orderBy('id')->each(function ($pet) {
            $records = DB::table('assessment_records')
                ->where('pet_id', $pet->id)
                ->orderBy('id')
                ->get();

            $count = $records->count();
            $updates = ['assessment_count' => $count];

            if ($count >= 3) {
                $updates += [
                    'energy_level' => round((float) $records->avg('energy_level'), 1),
                    'trainability' => round((float) $records->avg('trainability'), 1),
                    'independence' => round((float) $records->avg('independence'), 1),
                    'temperament' => round((float) $records->avg('temperament'), 1),
                    'last_assessed_at' => $records->max('created_at'),
                ];
            } else {
                // Cached final scores are invalid until three source records exist.
                $updates += [
                    'energy_level' => null,
                    'trainability' => null,
                    'independence' => null,
                    'temperament' => null,
                    'last_assessed_at' => $records->max('created_at'),
                ];

                if ($count === 0) {
                    $updates['last_assessed_by'] = null;
                }
            }

            DB::table('pets')->where('id', $pet->id)->update($updates);
        });
    }

    public function down(): void
    {
        // This migration repairs derived cache fields from immutable source records.
    }
};
