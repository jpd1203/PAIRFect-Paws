<?php

namespace Database\Seeders;

use App\Models\AdoptionApplication;
use App\Models\CheckIn;
use App\Models\FundRecord;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Adopter demo account ----
        $adopter = User::firstOrCreate(
            ['email' => 'jessa@example.com'],
            [
                'full_name' => 'Jessa Dela Cruz',
                'password' => Hash::make('ChangeMe123!'), // demo only — force reset in production
                'role' => 'adopter',
            ]
        );

        // ---- Staff demo accounts (admin panel: /admin/login) ----
        $admin = User::firstOrCreate(
            ['email' => 'admin@pairfectpaws.test'],
            [
                'full_name' => 'Admin Name',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'Admin',
                'is_active' => true,
            ]
        );

        $volunteer = User::firstOrCreate(
            ['email' => 'volunteer@pairfectpaws.test'],
            [
                'full_name' => 'Red Cubs Volunteer',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'Volunteer',
                'is_active' => true,
            ]
        );

        // ---- Pets ----
        $pets = [
            ['name' => 'Bruno', 'species' => 'Dog', 'breed' => 'Aspin', 'age_group' => 'Adult', 'age_years' => 3, 'sex' => 'Male', 'energy_level' => 4, 'independence_level' => 2, 'trainability' => 3, 'medical_needs' => 3, 'temperament' => 5, 'physical_size' => 'Large', 'status' => 'Available'],
            ['name' => 'Mochi', 'species' => 'Cat', 'breed' => 'Puspin', 'age_group' => 'Young', 'age_years' => 1, 'sex' => 'Female', 'energy_level' => 2, 'independence_level' => 5, 'trainability' => 2, 'medical_needs' => 1, 'temperament' => 4, 'physical_size' => 'Small', 'status' => 'Available', 'assessment_count' => 3],
            ['name' => 'Luna', 'species' => 'Dog', 'breed' => 'Shih Tzu', 'age_group' => 'Senior', 'age_years' => 8, 'sex' => 'Female', 'energy_level' => 1, 'independence_level' => 3, 'trainability' => 4, 'medical_needs' => 4, 'temperament' => 5, 'physical_size' => 'Small', 'status' => 'Available'],
            ['name' => 'Max', 'species' => 'Dog', 'breed' => 'Labrador Mix', 'age_group' => 'Baby', 'age_years' => 0, 'sex' => 'Male', 'energy_level' => 5, 'independence_level' => 1, 'trainability' => 3, 'medical_needs' => 1, 'temperament' => 3, 'physical_size' => 'Medium', 'status' => 'Assessing', 'assessment_count' => 1],
            ['name' => 'Mimi', 'species' => 'Cat', 'breed' => 'Persian Mix', 'age_group' => 'Adult', 'age_years' => 2, 'sex' => 'Female', 'energy_level' => 3, 'independence_level' => 3, 'trainability' => 3, 'medical_needs' => 2, 'temperament' => 4, 'physical_size' => 'Small', 'status' => 'Adopted'],
        ];

        $petModels = [];
        foreach ($pets as $pet) {
            $petModels[$pet['name']] = Pet::firstOrCreate(['name' => $pet['name']], [
                ...$pet,
                'intake_date' => now()->subMonths(rand(1, 6)),
                'health_status' => 'Healthy',
                'vaccination_records' => 'Up to date',
                'vaccination_record_status' => 'Complete',
                'last_assessed_at' => now()->subDays(rand(2, 20)),
                'last_assessed_by' => $volunteer->full_name,
            ]);
        }

        // ---- A completed adoption with full 3-3-3 monitoring history (Mimi -> Josh Oliver) ----
        $joshApplication = AdoptionApplication::firstOrCreate(
            ['email' => 'josh.oliver@example.com', 'pet_id' => $petModels['Mimi']->id],
            [
                'user_id' => $adopter->id,
                'first_name' => 'Josh', 'last_name' => 'Oliver',
                'phone_number' => '0917 123 4567', 'address' => '123 Main St, City',
                'housing_type' => 'Apartment', 'household_composition' => 'Couple Only / Roommates',
                'monthly_income_range' => '₱25,001 - ₱50,000', 'prior_pet_experience' => 'No Experience',
                'physical_activity_level' => 'Moderately Active', 'time_availability' => 'Limited (1–3 hrs/day)',
                'document_path' => 'seed/placeholder.pdf',
                'agreed_to_animal_welfare_act' => true,
                'status' => AdoptionApplication::STATUS_APPROVED,
                'interview_date' => now()->subMonths(4),
                'interview_time' => '10:00:00',
                'conducted_by' => $volunteer->full_name,
                'interview_notes' => 'Applicant is well-prepared, has a quiet apartment suited for a calm cat.',
                'compatibility_result' => [
                    'overall' => 100,
                    'rows' => [
                        ['label' => 'Activity vs energy', 'percent' => 100],
                        ['label' => 'Time vs Independence', 'percent' => 100],
                        ['label' => 'Experience vs Trainability', 'percent' => 100],
                        ['label' => 'Housing vs Size', 'percent' => 100],
                        ['label' => 'Housing vs Temperament', 'percent' => 100],
                        ['label' => 'Finance vs Medical needs', 'percent' => 100],
                    ],
                ],
            ]
        );

        if ($joshApplication->wasRecentlyCreated) {
            $adoptedOn = now()->subMonths(4);
            $checkIn1 = CheckIn::create(['user_id' => $adopter->id, 'pet_id' => $petModels['Mimi']->id, 'application_id' => $joshApplication->id, 'milestone' => 'ThreeDay', 'due_date' => $adoptedOn->copy()->addDays(3), 'status' => CheckIn::STATUS_SUBMITTED]);
            CheckIn::create(['user_id' => $adopter->id, 'pet_id' => $petModels['Mimi']->id, 'application_id' => $joshApplication->id, 'milestone' => 'ThreeWeek', 'due_date' => $adoptedOn->copy()->addWeeks(3), 'status' => CheckIn::STATUS_SUBMITTED]);
            CheckIn::create(['user_id' => $adopter->id, 'pet_id' => $petModels['Mimi']->id, 'application_id' => $joshApplication->id, 'milestone' => 'ThreeMonth', 'due_date' => $adoptedOn->copy()->addMonths(3), 'status' => CheckIn::STATUS_SUBMITTED]);
        }

        // ---- A pending application (fresh, no interview yet) ----
        AdoptionApplication::firstOrCreate(
            ['email' => 'maria.santos@example.com', 'pet_id' => $petModels['Bruno']->id],
            [
                'user_id' => $adopter->id,
                'first_name' => 'Maria', 'last_name' => 'Santos',
                'phone_number' => '0917 555 1212', 'address' => '45 Rizal St, Caloocan',
                'housing_type' => 'House with yard', 'household_composition' => 'Household with teenager',
                'monthly_income_range' => '₱50,001 - ₱100,000', 'prior_pet_experience' => 'Experienced',
                'physical_activity_level' => 'Very Active', 'time_availability' => 'Available (5–8 hrs/day)',
                'document_path' => 'seed/placeholder.pdf',
                'agreed_to_animal_welfare_act' => true,
                'status' => AdoptionApplication::STATUS_PENDING,
            ]
        );

        // ---- Fund records ----
        if (FundRecord::count() === 0) {
            FundRecord::insert([
                ['recorded_date' => now()->subDays(10), 'activity' => 'LG@email.com Donated', 'donation_added' => 500, 'shelter_spent' => null, 'recorded_by' => $admin->full_name, 'created_at' => now(), 'updated_at' => now()],
                ['recorded_date' => now()->subDays(6), 'activity' => 'Veterinary Supplies', 'donation_added' => null, 'shelter_spent' => 1500, 'recorded_by' => $admin->full_name, 'created_at' => now(), 'updated_at' => now()],
                ['recorded_date' => now()->subDays(2), 'activity' => 'MT@email.com Donated', 'donation_added' => 100, 'shelter_spent' => null, 'recorded_by' => $admin->full_name, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
