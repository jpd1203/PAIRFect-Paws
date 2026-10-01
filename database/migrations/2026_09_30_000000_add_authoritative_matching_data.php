<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adopter_profiles', function (Blueprint $table) {
            $table->json('bfi_responses')->nullable();
            foreach (['extraversion', 'conscientiousness', 'neuroticism', 'openness'] as $dimension) {
                $table->double($dimension)->nullable();
            }
            $table->timestamp('bfi_completed_at')->nullable();
            $table->boolean('has_existing_pets')->nullable();
        });
        Schema::table('assessment_records', function (Blueprint $table) {
            $table->json('responses')->nullable();
            $table->string('scoring_version', 50)->nullable();
            foreach (['energy_level', 'trainability', 'independence', 'temperament'] as $feature) {
                $table->double($feature)->nullable()->change();
            }
            $table->index(['pet_id', 'assessor_id'], 'assessment_pet_observer_index');
        });
        Schema::table('pets', function (Blueprint $table) {
            $table->boolean('high_vocalization')->nullable();
            $table->string('life_stage', 20)->nullable();
            $table->string('assessment_scoring_version', 50)->nullable();
            foreach (['energy_level', 'trainability', 'independence', 'temperament', 'medical_needs'] as $feature) {
                $table->double($feature)->nullable()->change();
            }
        });
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->double('knn_distance')->nullable();
            $table->double('knn_penalty')->nullable();
            $table->timestamp('knn_computed_at')->nullable();
            $table->string('knn_algorithm_version', 50)->nullable();
            $table->string('knn_source_fingerprint', 64)->nullable();
        });

        // Legacy knn_score meant distance. Preserve it separately; only reuse a percentage
        // when the old code actually stored one. No legacy profile is fabricated or rescored.
        DB::table('adoption_applications')->orderBy('id')->chunkById(200, function ($applications) {
            foreach ($applications as $application) {
                $breakdown = json_decode($application->compatibility_result ?? 'null', true);
                DB::table('adoption_applications')->where('id', $application->id)->update([
                    'knn_distance' => $application->knn_score,
                    'knn_score' => is_numeric($breakdown['overall'] ?? null) ? $breakdown['overall'] : null,
                    'knn_algorithm_version' => 'legacy-lifestyle',
                ]);
            }
        });
        Schema::table('adoption_applications', fn (Blueprint $table) => $table->decimal('knn_score', 5, 2)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('adoption_applications', fn (Blueprint $table) => $table->float('knn_score')->nullable()->change());
        DB::table('adoption_applications')->update(['knn_score' => DB::raw('knn_distance')]);
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->dropColumn(['knn_distance', 'knn_penalty', 'knn_computed_at', 'knn_algorithm_version', 'knn_source_fingerprint']);
        });
        Schema::table('adopter_profiles', function (Blueprint $table) {
            $table->dropColumn(['bfi_responses', 'extraversion', 'conscientiousness', 'neuroticism', 'openness', 'bfi_completed_at', 'has_existing_pets']);
        });
        Schema::table('assessment_records', function (Blueprint $table) {
            $table->dropIndex('assessment_pet_observer_index');
            $table->dropColumn(['responses', 'scoring_version']);
        });
        Schema::table('pets', fn (Blueprint $table) => $table->dropColumn(['high_vocalization', 'life_stage', 'assessment_scoring_version']));
        // Keep widened numeric columns: rolling back must not truncate collected precision.
    }
};
