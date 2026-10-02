<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Pet;
use App\Models\User;
use App\Services\Matching\BehaviorAssessmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FreshStartPetsSeeder extends Seeder
{
    public function run(): void
    {
        if (Pet::withoutGlobalScope('notArchived')->exists()) {
            throw new RuntimeException('Fresh-start pet seeding requires an empty pets table.');
        }

        $staff = User::query()
            ->whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(3)
            ->get();

        if ($staff->count() !== 3) {
            throw new RuntimeException('Three active staff accounts are required for independent pet assessments. No accounts were created.');
        }

        // Synthetic demonstration records; values are not observations of real animals.
        // Columns: name, species, breed, age in months, sex, size, energy,
        // trainability, attachment/sociability, fear/reactivity, medical needs.
        $pets = [
            ['Koko', 'Dog', 'Aspin', 18, 'Female', 'Medium', 3, 3, 3, 1, 1],
            ['Oreo', 'Dog', 'Mixed Breed', 30, 'Male', 'Medium', 2, 3, 2, 1, 1],
            ['Bruno', 'Dog', 'Labrador Mix', 48, 'Male', 'Large', 4, 4, 3, 1, 1],
            ['Buddy', 'Dog', 'Golden Retriever Mix', 36, 'Male', 'Large', 3, 4, 4, 0, 1],
            ['Daisy', 'Dog', 'Beagle Mix', 14, 'Female', 'Small', 4, 3, 3, 1, 1],
            ['Max', 'Dog', 'Aspin', 72, 'Male', 'Medium', 2, 4, 2, 1, 2],
            ['Rocky', 'Dog', 'Terrier Mix', 96, 'Male', 'Small', 2, 3, 2, 2, 2],
            ['Tala', 'Dog', 'Shih Tzu Mix', 10, 'Female', 'Small', 3, 2, 4, 1, 1],
            ['Luna', 'Cat', 'Puspin', 24, 'Female', 'Small', 2, 2, 3, 1, 1],
            ['Mimi', 'Cat', 'Domestic Shorthair', 40, 'Female', 'Small', 1, 2, 4, 0, 1],
            ['Mochi', 'Cat', 'Persian Mix', 16, 'Male', 'Small', 2, 2, 3, 1, 1],
            ['Nala', 'Cat', 'Puspin', 12, 'Female', 'Small', 4, 3, 3, 1, 1],
            ['Leo', 'Cat', 'Domestic Shorthair', 60, 'Male', 'Medium', 3, 2, 2, 2, 2],
            ['Suki', 'Cat', 'Siamese Mix', 30, 'Female', 'Small', 3, 3, 4, 1, 1],
            ['Poppy', 'Cat', 'Puspin', 108, 'Female', 'Small', 1, 2, 3, 1, 2],
        ];

        $assessment = app(BehaviorAssessmentService::class);

        DB::transaction(function () use ($pets, $staff, $assessment): void {
            foreach ($pets as $index => [$name, $species, $breed, $age, $sex, $size, $energy, $trainability, $social, $fear, $medicalNeeds]) {
                $pet = Pet::create([
                    'name' => $name,
                    'species' => $species,
                    'breed' => $breed,
                    'age' => $age,
                    'sex' => $sex,
                    'physical_size' => $size,
                    'life_stage' => $age <= 24 ? 'young' : ($age > 84 ? 'senior' : 'adult'),
                    'health_status' => 'Healthy',
                    'vaccination_record_status' => 'Complete',
                    'description' => "Demo pet record for PAIRfect Paws testing. {$name}'s details and assessments are synthetic.",
                    'behavioral_notes' => 'Synthetic C-BARQ/Fe-BARQ answers for demonstration; not a real animal assessment.',
                    'availability_status' => 'Available',
                    'intake_date' => now()->subDays(15 + $index * 3)->toDateString(),
                    'medical_needs' => $medicalNeeds,
                    'is_reactive_to_pets' => false,
                    'has_aggression_history' => false,
                    'aggression_history_verified_at' => now(),
                    'high_vocalization' => false,
                    'is_archived' => false,
                ]);

                foreach ($staff as $observerIndex => $observer) {
                    $recorded = $assessment->record($pet, $observer, [
                        'responses' => $this->answers(strtolower($species), [
                            'energy' => $energy,
                            'trainability' => $trainability,
                            'social' => $social,
                            'fear' => $fear,
                        ], $observerIndex - 1),
                    ]);

                    if (! $recorded) {
                        throw new RuntimeException("Could not record an independent assessment for {$name}.");
                    }
                }
            }
        });
    }

    private function answers(string $species, array $traits, int $observerOffset): array
    {
        $answers = [];
        foreach (config("matching.items.{$species}", []) as $group => $items) {
            $base = match ($group) {
                'energy' => $traits['energy'],
                'trainability' => $traits['trainability'],
                'attachment', 'sociability', 'attention_seeking' => $traits['social'],
                default => $traits['fear'],
            };
            $score = max(0, min(4, $base + $observerOffset));

            foreach (array_keys($items) as $key) {
                $answers[$group][$key] = in_array("{$species}.{$group}.{$key}", config('matching.behavior_reverse', []), true)
                    ? 4 - $score : $score;
            }
        }

        return $answers;
    }
}
