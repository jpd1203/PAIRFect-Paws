<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pet;
use App\Models\AssessmentRecord;
use App\Models\User;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure there is at least one user to be the assessor
        $assessor = User::first();
        if (!$assessor) {
            $assessor = User::create([
                'name' => 'Demo Assessor',
                'email' => 'assessor@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        $pets = Pet::all();
        $physicalSizes = ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large'];

        foreach ($pets as $pet) {
            // Delete existing assessments for this pet to avoid duplicates
            AssessmentRecord::where('pet_id', $pet->id)->delete();

            $totalEnergy = 0;
            $totalTrainability = 0;
            $totalIndependence = 0;
            $totalTemperament = 0;

            // Create 3 assessment records
            for ($i = 0; $i < 3; $i++) {
                $energy = rand(10, 50) / 10; // 1.0 to 5.0
                $trainability = rand(10, 50) / 10;
                $independence = rand(10, 50) / 10;
                $temperament = rand(10, 50) / 10;

                AssessmentRecord::create([
                    'pet_id' => $pet->id,
                    'assessor_id' => $assessor->id,
                    'energy_level' => $energy,
                    'trainability' => $trainability,
                    'independence' => $independence,
                    'temperament' => $temperament,
                ]);

                $totalEnergy += $energy;
                $totalTrainability += $trainability;
                $totalIndependence += $independence;
                $totalTemperament += $temperament;
            }

            // Update the Pet model with averages and other required fields for recommendation
            $pet->update([
                'energy_level' => round($totalEnergy / 3, 1),
                'trainability' => round($totalTrainability / 3, 1),
                'independence' => round($totalIndependence / 3, 1),
                'temperament' => round($totalTemperament / 3, 1),
                'medical_needs' => rand(10, 50) / 10,
                'is_reactive_to_pets' => (bool)rand(0, 1),
                'has_aggression_history' => false,
                'physical_size' => $physicalSizes[array_rand($physicalSizes)],
                'assessment_count' => 3,
                'last_assessed_at' => now(),
                'last_assessed_by' => $assessor->id,
            ]);

            $this->command->info("Added assessments for {$pet->name}");
        }
    }
}

