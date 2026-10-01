<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AdopterProfile;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Models\User;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicationMatchService;
use App\Services\Matching\BfiScorer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit opt-in demo data. Never invoked by DatabaseSeeder or run against production. */
class MatchingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('MatchingDemoSeeder is restricted to local/testing environments.');
        }

        DB::transaction(function () {
            $observers = collect(range(1, 3))->map(fn ($i) => $this->user("observer-{$i}", Role::Volunteer));
            $sizes = ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large'];
            for ($i = 1; $i <= 12; $i++) {
                $species = $i <= 6 ? 'Dog' : 'Cat';
                $pet = Pet::firstOrNew(['name' => "Matching Demo {$species} {$i}"]);
                $pet->fill([
                    'species' => $species, 'sex' => $i % 2 ? 'Male' : 'Female',
                    'age' => $i === 6 ? 120 : 36, 'life_stage' => $i === 6 ? 'senior' : 'adult',
                    'availability_status' => 'Available', 'is_archived' => false,
                    'physical_size' => $sizes[($i - 1) % 5], 'medical_needs' => $i === 6 ? 5 : 2,
                    'has_aggression_history' => $i === 5, 'aggression_history_verified_at' => now(),
                    'high_vocalization' => $i === 4,
                    'description' => 'Synthetic matching QA record; not a real shelter animal.',
                    'last_assessed_at' => now(), 'last_assessed_by' => 'Matching demo observers',
                ])->saveQuietly();

                $answers = $this->behaviorAnswers(strtolower($species), $i);
                foreach ($observers as $observer) {
                    $record = AssessmentRecord::firstOrNew(['pet_id' => $pet->id, 'assessor_id' => $observer->id]);
                    $record->fill([
                        'responses' => ['species' => strtolower($species), 'answers' => $answers],
                        'scoring_version' => config('matching.algorithm_version'),
                    ])->saveQuietly();
                }
                app(ApplicationMatchService::class)->refreshPetSummary($pet);
            }

            foreach ([
                'energetic-yard' => [4, 4, 2, 4, 'Single Family Home (Fenced Yard)', false, 4],
                'calm-apartment' => [2, 4, 4, 2, 'Apartment / Condo', false, 3],
                'with-children' => [3, 4, 3, 3, 'Single Family Home (Fenced Yard)', true, 3],
                'limited-budget' => [3, 3, 3, 3, 'Townhouse', false, 2],
            ] as $key => [$e, $c, $n, $o, $housing, $children, $financial]) {
                $answers = [];
                foreach (array_combine(array_keys(config('matching.bfi.dimensions')), [$e, $c, $n, $o]) as $dimension => $value) {
                    foreach (config("matching.bfi.dimensions.{$dimension}") as $item) {
                        $answers[$item] = in_array($item, config('matching.bfi.reverse'), true) ? 6 - $value : $value;
                    }
                }
                $profile = AdopterProfile::updateOrCreate(['user_id' => $this->user($key)->id], [
                    'bfi_responses' => $answers, ...app(BfiScorer::class)->score($answers),
                    'bfi_completed_at' => now(), 'housing_type' => $housing,
                    'has_children' => $children, 'has_existing_pets' => false, 'financial_readiness' => $financial,
                    'physical_activity_level' => 'Moderate (Daily walks, occasional play)',
                    'time_availability' => '2-4 hours/day', 'prior_pet_experience' => 'First-time owner',
                    'household_composition' => $children ? 'Living with children (under 12)' : 'Living with adults only',
                    'monthly_income_range' => array_search($financial, config('matching.financial_levels'), true),
                ]);
                if ($this->command?->getOutput()->isVerbose()) {
                    $this->command->info("Top five: {$key}");
                    foreach (app(KnnRecommendationService::class)->recommendPets($profile) as $item) {
                        $this->command->line(sprintf('%s: %.2f%%; distance %.6f; penalty %.1f',
                            $item['pet']->name, $item['match']->compatibilityScore,
                            $item['match']->adjustedDistance, $item['match']->penalty));
                    }
                }
            }
        });
    }

    private function user(string $key, Role $role = Role::Adopter): User
    {
        return User::firstOrCreate(['email' => "matching-demo-{$key}@example.test"], [
            'first_name' => 'Matching Demo', 'last_name' => $key,
            'password' => 'MatchingDemo123!', 'role' => $role->value,
            'email_verified_at' => now(), 'is_active' => true,
        ]);
    }

    private function behaviorAnswers(string $species, int $index): array
    {
        $answers = [];
        foreach (config("matching.items.{$species}") as $group => $items) {
            $raw = match ($group) {
                'energy' => ($index - 1) % 5,
                'trainability' => 3,
                'attachment', 'sociability', 'attention_seeking' => 2,
                default => in_array($index, [5, 11], true) ? 4 : 1,
            };
            foreach ($items as $item => $wording) {
                $answers[$group][$item] = in_array("{$species}.{$group}.{$item}", config('matching.behavior_reverse'), true)
                    ? 4 - $raw : $raw;
            }
        }

        return $answers;
    }
}
