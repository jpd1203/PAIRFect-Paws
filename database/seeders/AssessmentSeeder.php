<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Models\User;
use App\Services\Matching\ApplicationMatchService;
use App\Services\Matching\BehaviorAssessmentService;
use Illuminate\Database\Seeder;
use RuntimeException;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Synthetic assessments may only be seeded in local or testing environments.');
        }

        $staff1 = User::firstOrCreate(['email' => 'admin@pairfectpaws.com'], [
            'first_name' => 'Admin', 'last_name' => 'User', 'role' => Role::Administrator->value, 'password' => bcrypt('password'), 'email_verified_at' => now(), 'is_active' => true,
        ]);
        $staff2 = User::firstOrCreate(['email' => 'volunteer@pairfectpaws.com'], [
            'first_name' => 'Volunteer', 'last_name' => 'Staff', 'role' => Role::Volunteer->value, 'password' => bcrypt('password'), 'email_verified_at' => now(), 'is_active' => true,
        ]);
        $staff3 = User::firstOrCreate(['email' => 'evaluator@pairfectpaws.com'], [
            'first_name' => 'Evaluator', 'last_name' => 'Staff', 'role' => Role::Volunteer->value, 'password' => bcrypt('password'), 'email_verified_at' => now(), 'is_active' => true,
        ]);
        $staff = [$staff1, $staff2, $staff3];

        $pets = Pet::all();
        $petConfigs = [
            'Luna'   => ['species' => 'cat', 'energy' => 2, 'trainability' => 3, 'attachment' => 3, 'fear' => 1, 'size' => 'Small', 'life_stage' => 'adult'],
            'Buddy'  => ['species' => 'dog', 'energy' => 4, 'trainability' => 4, 'attachment' => 4, 'fear' => 1, 'size' => 'Large', 'life_stage' => 'adult'],
            'Mochi'  => ['species' => 'cat', 'energy' => 2, 'trainability' => 3, 'attachment' => 4, 'fear' => 1, 'size' => 'Small', 'life_stage' => 'young'],
            'Max'    => ['species' => 'dog', 'energy' => 3, 'trainability' => 4, 'attachment' => 3, 'fear' => 1, 'size' => 'Medium', 'life_stage' => 'adult'],
            'Coco'   => ['species' => 'dog', 'energy' => 3, 'trainability' => 3, 'attachment' => 4, 'fear' => 1, 'size' => 'Small', 'life_stage' => 'adult'],
            'Oliver' => ['species' => 'cat', 'energy' => 3, 'trainability' => 3, 'attachment' => 3, 'fear' => 1, 'size' => 'Medium', 'life_stage' => 'adult'],
        ];

        foreach ($pets as $pet) {
            AssessmentRecord::where('pet_id', $pet->id)->delete();

            $speciesStr = strtolower($pet->species->value ?? 'dog');
            $cfg = $petConfigs[$pet->name] ?? [
                'species' => $speciesStr,
                'energy' => 3,
                'trainability' => 3,
                'attachment' => 3,
                'fear' => 1,
                'size' => 'Medium',
                'life_stage' => 'adult',
            ];

            $pet->update([
                'physical_size' => $cfg['size'],
                'life_stage' => $cfg['life_stage'],
                'medical_needs' => 1,
                'has_aggression_history' => false,
                'aggression_history_verified_at' => now(),
                'high_vocalization' => false,
                'availability_status' => 'Available',
            ]);

            foreach ($staff as $observer) {
                $answers = $this->behaviorAnswers($cfg['species'], $cfg['energy'], $cfg['trainability'], $cfg['attachment'], $cfg['fear']);
                app(BehaviorAssessmentService::class)->record($pet, $observer, [
                    'responses' => $answers,
                ]);
            }

            app(ApplicationMatchService::class)->refreshPetSummary($pet);
            if ($this->command) {
                $this->command->info("Added 3 distinct assessments for {$pet->name}");
            }
        }
    }

    private function behaviorAnswers(string $species, int $energy, int $trainability, int $attachment, int $fear): array
    {
        $answers = [];
        foreach (config("matching.items.{$species}", []) as $group => $items) {
            $score = match ($group) {
                'energy' => $energy,
                'trainability' => $trainability,
                'attachment', 'sociability', 'attention_seeking' => $attachment,
                default => $fear,
            };
            foreach ($items as $key => $prompt) {
                $answers[$group][$key] = in_array("{$species}.{$group}.{$key}", config('matching.behavior_reverse', []), true)
                    ? 4 - $score : $score;
            }
        }

        return $answers;
    }
}
