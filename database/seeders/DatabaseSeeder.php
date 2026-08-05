<?php

namespace Database\Seeders;

use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\FlaggedCase;
use App\Models\FundRecord;
use App\Models\Pet;
use App\Models\PetAssessment;
use App\Models\PostAdoptionReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Adopter & Staff Users
        $adopter = User::firstOrCreate(
            ['email' => 'jessa@example.com'],
            [
                'full_name' => 'Jessa Dela Cruz',
                'phone_number' => '0917 999 8888',
                'address' => '123 Katipunan Ave, Quezon City',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'adopter',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@pairfectpaws.test'],
            [
                'full_name' => 'Admin Maria Santos',
                'phone_number' => '0918 985 2149',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'Admin',
                'is_active' => true,
            ]
        );

        $volunteer = User::firstOrCreate(
            ['email' => 'volunteer@pairfectpaws.test'],
            [
                'full_name' => 'Red Cubs Volunteer',
                'phone_number' => '0920 111 2222',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'Volunteer',
                'is_active' => true,
            ]
        );

        foreach ([
            ['email' => 'carlos.yulo@pairfectpaws.test', 'full_name' => 'Carlos Yulo', 'phone_number' => '0915 222 3333', 'role' => 'Volunteer'],
            ['email' => 'bea.alonzo@pairfectpaws.test', 'full_name' => 'Bea Alonzo', 'phone_number' => '0916 444 5555', 'role' => 'Volunteer'],
            ['email' => 'ramon.bautista@pairfectpaws.test', 'full_name' => 'Ramon Bautista', 'phone_number' => '0917 888 9999', 'role' => 'Admin'],
        ] as $volData) {
            User::firstOrCreate(['email' => $volData['email']], [
                'full_name' => $volData['full_name'],
                'phone_number' => $volData['phone_number'],
                'password' => Hash::make('ChangeMe123!'),
                'role' => $volData['role'],
                'is_active' => true,
            ]);
        }

        // 2. Pets
        $pets = [
            ['name' => 'Bruno', 'species' => 'Dog', 'breed' => 'Aspin', 'age_group' => 'Adult', 'age_years' => 3, 'age_months' => 36, 'sex' => 'Male', 'energy_level' => 4, 'independence_level' => 2, 'trainability' => 3, 'medical_needs' => 3, 'temperament' => 5, 'physical_size' => 'Large', 'status' => 'Available', 'assessment_count' => 2],
            ['name' => 'Mochi', 'species' => 'Cat', 'breed' => 'Puspin', 'age_group' => 'Young', 'age_years' => 1, 'age_months' => 12, 'sex' => 'Female', 'energy_level' => 2, 'independence_level' => 5, 'trainability' => 2, 'medical_needs' => 1, 'temperament' => 4, 'physical_size' => 'Small', 'status' => 'Available', 'assessment_count' => 3],
            ['name' => 'Luna', 'species' => 'Dog', 'breed' => 'Shih Tzu', 'age_group' => 'Senior', 'age_years' => 8, 'age_months' => 96, 'sex' => 'Female', 'energy_level' => 1, 'independence_level' => 3, 'trainability' => 4, 'medical_needs' => 4, 'temperament' => 5, 'physical_size' => 'Small', 'status' => 'Available', 'assessment_count' => 1],
            ['name' => 'Max', 'species' => 'Dog', 'breed' => 'Labrador Mix', 'age_group' => 'Baby', 'age_years' => 0, 'age_months' => 5, 'sex' => 'Male', 'energy_level' => 5, 'independence_level' => 1, 'trainability' => 3, 'medical_needs' => 1, 'temperament' => 3, 'physical_size' => 'Medium', 'status' => 'Assessing', 'assessment_count' => 1],
            ['name' => 'Mimi', 'species' => 'Cat', 'breed' => 'Persian Mix', 'age_group' => 'Adult', 'age_years' => 2, 'age_months' => 24, 'sex' => 'Female', 'energy_level' => 3, 'independence_level' => 3, 'trainability' => 3, 'medical_needs' => 2, 'temperament' => 4, 'physical_size' => 'Small', 'status' => 'Adopted', 'assessment_count' => 2],
            ['name' => 'Rocky', 'species' => 'Dog', 'breed' => 'Golden Retriever Mix', 'age_group' => 'Adult', 'age_years' => 2, 'age_months' => 28, 'sex' => 'Male', 'energy_level' => 5, 'independence_level' => 2, 'trainability' => 5, 'medical_needs' => 1, 'temperament' => 5, 'physical_size' => 'Large', 'status' => 'Available', 'assessment_count' => 2],
            ['name' => 'Cleo', 'species' => 'Cat', 'breed' => 'Siamese Mix', 'age_group' => 'Adult', 'age_years' => 4, 'age_months' => 48, 'sex' => 'Female', 'energy_level' => 3, 'independence_level' => 4, 'trainability' => 3, 'medical_needs' => 1, 'temperament' => 4, 'physical_size' => 'Small', 'status' => 'Available', 'assessment_count' => 1],
            ['name' => 'Buddy', 'species' => 'Dog', 'breed' => 'Beagle', 'age_group' => 'Adult', 'age_years' => 5, 'age_months' => 60, 'sex' => 'Male', 'energy_level' => 4, 'independence_level' => 3, 'trainability' => 4, 'medical_needs' => 2, 'temperament' => 4, 'physical_size' => 'Medium', 'status' => 'Assessing', 'assessment_count' => 1],
            ['name' => 'Coco', 'species' => 'Dog', 'breed' => 'Poodle Mix', 'age_group' => 'Young', 'age_years' => 1, 'age_months' => 14, 'sex' => 'Female', 'energy_level' => 3, 'independence_level' => 3, 'trainability' => 5, 'medical_needs' => 1, 'temperament' => 5, 'physical_size' => 'Small', 'status' => 'Available', 'assessment_count' => 2],
            ['name' => 'Simba', 'species' => 'Cat', 'breed' => 'Tabby Cat', 'age_group' => 'Baby', 'age_years' => 0, 'age_months' => 6, 'sex' => 'Male', 'energy_level' => 4, 'independence_level' => 4, 'trainability' => 2, 'medical_needs' => 1, 'temperament' => 5, 'physical_size' => 'Small', 'status' => 'Available', 'assessment_count' => 1],
        ];

        $petModels = [];
        foreach ($pets as $pet) {
            $petModels[$pet['name']] = Pet::firstOrCreate(['name' => $pet['name']], [
                ...$pet,
                'intake_date' => now()->subMonths(rand(1, 8)),
                'health_status' => rand(0, 4) === 0 ? 'Under Treatment' : 'Healthy',
                'vaccination_records' => 'Core vaccines complete (Rabies, DHPP/FVRCP)',
                'vaccination_record_status' => 'Complete',
                'last_assessed_at' => now()->subDays(rand(1, 15)),
                'last_assessed_by' => $volunteer->full_name,
            ]);
        }

        // 3. Pet Assessments
        foreach (['Mochi', 'Bruno', 'Rocky', 'Cleo', 'Max', 'Luna'] as $petName) {
            if (isset($petModels[$petName])) {
                PetAssessment::firstOrCreate(
                    ['pet_id' => $petModels[$petName]->id, 'assessment_number' => 1],
                    [
                        'assessed_by_id' => $volunteer->id,
                        'assessed_by_name' => $volunteer->full_name,
                        'assessment_number' => 1,
                        'species' => $petModels[$petName]->species,
                        'energy_level_avg' => $petModels[$petName]->energy_level,
                        'independence_avg' => $petModels[$petName]->independence_level,
                        'trainability_avg' => $petModels[$petName]->trainability,
                        'temperament_avg' => $petModels[$petName]->temperament,
                        'answers' => [
                            'behavioral' => 'Very friendly, responsive during initial evaluation.',
                            'medical' => 'Physical examination clear. Dewormed and vaccinated.',
                        ],
                    ]
                );
            }
        }

        // 4. Adopter Profile
        AdopterProfile::firstOrCreate(
            ['user_id' => $adopter->id],
            [
                'physical_activity_level' => 'Moderately Active',
                'time_availability' => 'Available (5–8 hrs/day)',
                'prior_pet_experience' => 'Experienced',
                'housing_type' => 'Apartment',
                'household_composition' => 'Couple Only / Roommates',
                'monthly_income_range' => '₱25,001 - ₱50,000',
            ]
        );

        // 5. Adoption Applications
        // A. Approved Application (Josh Oliver -> Mimi)
        $joshApplication = AdoptionApplication::firstOrCreate(
            ['email' => 'josh.oliver@example.com', 'pet_id' => $petModels['Mimi']->id],
            [
                'user_id' => $adopter->id,
                'first_name' => 'Josh', 'last_name' => 'Oliver',
                'phone_number' => '0917 123 4567', 'address' => '123 Katipunan Ave, Quezon City',
                'housing_type' => 'Apartment', 'household_composition' => 'Couple Only / Roommates',
                'monthly_income_range' => '₱25,001 - ₱50,000', 'prior_pet_experience' => 'Experienced',
                'physical_activity_level' => 'Moderately Active', 'time_availability' => 'Available (5–8 hrs/day)',
                'document_path' => 'seed/placeholder.pdf',
                'agreed_to_animal_welfare_act' => true,
                'status' => AdoptionApplication::STATUS_APPROVED,
                'interview_date' => now()->subMonths(3)->toDateString(),
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
                    ],
                ],
            ]
        );

        if ($joshApplication->wasRecentlyCreated) {
            $adoptedOn = now()->subMonths(3);
            $checkIn1 = CheckIn::create(['user_id' => $adopter->id, 'pet_id' => $petModels['Mimi']->id, 'application_id' => $joshApplication->id, 'milestone' => 'ThreeDay', 'due_date' => $adoptedOn->copy()->addDays(3), 'status' => CheckIn::STATUS_SUBMITTED]);
            $checkIn2 = CheckIn::create(['user_id' => $adopter->id, 'pet_id' => $petModels['Mimi']->id, 'application_id' => $joshApplication->id, 'milestone' => 'ThreeWeek', 'due_date' => $adoptedOn->copy()->addWeeks(3), 'status' => CheckIn::STATUS_SUBMITTED]);
            $checkIn3 = CheckIn::create(['user_id' => $adopter->id, 'pet_id' => $petModels['Mimi']->id, 'application_id' => $joshApplication->id, 'milestone' => 'ThreeMonth', 'due_date' => now()->addDays(5), 'status' => CheckIn::STATUS_PENDING]);

            PostAdoptionReport::create([
                'check_in_id' => $checkIn1->id,
                'user_id' => $adopter->id,
                'pet_id' => $petModels['Mimi']->id,
                'milestone' => 'ThreeDay',
                'health_status' => 'Excellent',
                'eating_and_drinking' => 'Normal appetite, drinking water regularly.',
                'behavior' => 'Very friendly, playful, adjusting quickly to the new home.',
                'living_conditions' => 'Indoor apartment with litter box and cat tower.',
                'vet_visit' => true,
                'concerns' => 'None, doing great!',
                'report_date' => $adoptedOn->copy()->addDays(3),
                'flagged' => false,
            ]);

            PostAdoptionReport::create([
                'check_in_id' => $checkIn2->id,
                'user_id' => $adopter->id,
                'pet_id' => $petModels['Mimi']->id,
                'milestone' => 'ThreeWeek',
                'health_status' => 'Good',
                'eating_and_drinking' => 'Eating well, active and happy.',
                'behavior' => 'Loves cuddling and lounging near windows.',
                'living_conditions' => 'Safe indoor environment.',
                'vet_visit' => false,
                'concerns' => 'No issues reported.',
                'report_date' => $adoptedOn->copy()->addWeeks(3),
                'flagged' => false,
            ]);
        }

        // B. Scheduled Interview Application (Maria Santos -> Bruno)
        AdoptionApplication::firstOrCreate(
            ['email' => 'maria.santos@example.com', 'pet_id' => $petModels['Bruno']->id],
            [
                'user_id' => $adopter->id,
                'first_name' => 'Maria', 'last_name' => 'Santos',
                'phone_number' => '0917 555 1212', 'address' => '45 Rizal St, Caloocan City',
                'housing_type' => 'House with yard', 'household_composition' => 'Household with teenager',
                'monthly_income_range' => '₱50,001 - ₱100,000', 'prior_pet_experience' => 'Experienced',
                'physical_activity_level' => 'Very Active', 'time_availability' => 'Available (5–8 hrs/day)',
                'document_path' => 'seed/placeholder.pdf',
                'agreed_to_animal_welfare_act' => true,
                'status' => AdoptionApplication::STATUS_SCHEDULED,
                'interview_date' => now()->addDays(1)->toDateString(),
                'interview_time' => '10:30:00',
                'conducted_by' => $admin->full_name,
                'compatibility_result' => [
                    'overall' => 92,
                    'rows' => [
                        ['label' => 'Activity vs energy', 'percent' => 95],
                        ['label' => 'Time vs Independence', 'percent' => 90],
                    ],
                ],
            ]
        );

        // C. Under Review Application (Kevin Tan -> Rocky)
        AdoptionApplication::firstOrCreate(
            ['email' => 'kevin.tan@example.com', 'pet_id' => $petModels['Rocky']->id],
            [
                'user_id' => $adopter->id,
                'first_name' => 'Kevin', 'last_name' => 'Tan',
                'phone_number' => '0918 333 4444', 'address' => '88 Ayala Ave, Makati City',
                'housing_type' => 'House with yard', 'household_composition' => 'Multi-generational Family',
                'monthly_income_range' => 'Above ₱100,000', 'prior_pet_experience' => 'Experienced',
                'physical_activity_level' => 'Very Active', 'time_availability' => 'Available (5–8 hrs/day)',
                'document_path' => 'seed/placeholder.pdf',
                'agreed_to_animal_welfare_act' => true,
                'status' => AdoptionApplication::STATUS_UNDER_REVIEW,
                'compatibility_result' => [
                    'overall' => 88,
                    'rows' => [
                        ['label' => 'Activity vs energy', 'percent' => 90],
                    ],
                ],
            ]
        );

        // D. Pending Application (Ana Reyes -> Mochi)
        AdoptionApplication::firstOrCreate(
            ['email' => 'ana.reyes@example.com', 'pet_id' => $petModels['Mochi']->id],
            [
                'user_id' => $adopter->id,
                'first_name' => 'Ana', 'last_name' => 'Reyes',
                'phone_number' => '0922 777 8888', 'address' => '12 Tomas Morato, Quezon City',
                'housing_type' => 'Apartment', 'household_composition' => 'Single',
                'monthly_income_range' => '₱25,001 - ₱50,000', 'prior_pet_experience' => 'Moderate Experience',
                'physical_activity_level' => 'Moderately Active', 'time_availability' => 'Available (5–8 hrs/day)',
                'document_path' => 'seed/placeholder.pdf',
                'agreed_to_animal_welfare_act' => true,
                'status' => AdoptionApplication::STATUS_PENDING,
            ]
        );

        // E. Rejected Application (Ricardo Cruz -> Max)
        AdoptionApplication::firstOrCreate(
            ['email' => 'ricardo.cruz@example.com', 'pet_id' => $petModels['Max']->id],
            [
                'user_id' => $adopter->id,
                'first_name' => 'Ricardo', 'last_name' => 'Cruz',
                'phone_number' => '0915 111 0000', 'address' => '99 EDSA, Pasay City',
                'housing_type' => 'Apartment', 'household_composition' => 'Single',
                'monthly_income_range' => 'Below ₱15,000', 'prior_pet_experience' => 'No Experience',
                'physical_activity_level' => 'Sedentary', 'time_availability' => 'Minimal (<1 hr/day)',
                'document_path' => 'seed/placeholder.pdf',
                'agreed_to_animal_welfare_act' => true,
                'status' => AdoptionApplication::STATUS_REJECTED,
                'interview_notes' => 'Housing rules prohibit large dogs. Time availability insufficient for puppy care.',
            ]
        );

        // 6. Overdue & Flagged Check-Ins
        $overdueCheckIn = CheckIn::updateOrCreate(
            ['user_id' => $adopter->id, 'pet_id' => $petModels['Bruno']->id, 'milestone' => 'ThreeWeek'],
            [
                'application_id' => $joshApplication->id,
                'due_date' => now()->subDays(12),
                'status' => CheckIn::STATUS_OVERDUE,
            ]
        );

        $overdueCheckIn2 = CheckIn::updateOrCreate(
            ['user_id' => $adopter->id, 'pet_id' => $petModels['Mimi']->id, 'milestone' => 'ThreeDay'],
            [
                'application_id' => $joshApplication->id,
                'due_date' => now()->subDays(8),
                'status' => CheckIn::STATUS_OVERDUE,
            ]
        );

        if ($overdueCheckIn->wasRecentlyCreated) {
            FlaggedCase::create([
                'check_in_id' => $overdueCheckIn->id,
                'description' => 'Overdue 3-Week Check-in report (12 days past due date)',
                'is_escalated' => true,
                'escalation_reason' => 'Multiple automated SMS & email reminders unacknowledged',
            ]);
        }

        $flaggedCheckIn = CheckIn::firstOrCreate(
            ['user_id' => $adopter->id, 'pet_id' => $petModels['Luna']->id, 'milestone' => 'ThreeDay'],
            [
                'application_id' => $joshApplication->id,
                'due_date' => now()->subDays(5),
                'status' => CheckIn::STATUS_FLAGGED,
            ]
        );

        if ($flaggedCheckIn->wasRecentlyCreated) {
            PostAdoptionReport::create([
                'check_in_id' => $flaggedCheckIn->id,
                'user_id' => $adopter->id,
                'pet_id' => $petModels['Luna']->id,
                'milestone' => 'ThreeDay',
                'health_status' => 'Poor',
                'eating_and_drinking' => 'Appetite loss, reluctant to eat dry food.',
                'behavior' => 'Lethargic and hiding under bed.',
                'living_conditions' => 'Indoor house.',
                'vet_visit' => false,
                'concerns' => 'Pet seems lethargic and not eating well since yesterday.',
                'report_date' => now()->subDays(4),
                'flagged' => true,
                'flag_reason' => 'Reported poor health status & loss of appetite. Shelter vet follow-up required.',
            ]);

            FlaggedCase::create([
                'check_in_id' => $flaggedCheckIn->id,
                'description' => 'Pet reported with Poor health status and lethargy',
                'marked_for_intervention' => true,
                'intervention_type' => 'Shelter Visit',
                'intervention_notes' => 'Field team scheduled to check on pet condition tomorrow morning',
            ]);
        }

        // 7. Fund Records / Shelter Ledger
        if (FundRecord::count() === 0) {
            FundRecord::insert([
                ['recorded_date' => now()->subDays(18)->toDateString(), 'activity' => 'GCash Community Donation - Pet Rescue Drive', 'donation_added' => 15000, 'shelter_spent' => null, 'recorded_by' => $admin->full_name, 'created_at' => now(), 'updated_at' => now()],
                ['recorded_date' => now()->subDays(14)->toDateString(), 'activity' => 'Veterinary Medical Supplies & Vaccines Batch #4', 'donation_added' => null, 'shelter_spent' => 6500, 'recorded_by' => $admin->full_name, 'created_at' => now(), 'updated_at' => now()],
                ['recorded_date' => now()->subDays(10)->toDateString(), 'activity' => 'BDO Corporate Sponsorship - Pet Food Supply', 'donation_added' => 25000, 'shelter_spent' => null, 'recorded_by' => $admin->full_name, 'created_at' => now(), 'updated_at' => now()],
                ['recorded_date' => now()->subDays(8)->toDateString(), 'activity' => 'Bulk Pet Kibble & Canned Food Stockup', 'donation_added' => null, 'shelter_spent' => 12000, 'recorded_by' => $admin->full_name, 'created_at' => now(), 'updated_at' => now()],
                ['recorded_date' => now()->subDays(5)->toDateString(), 'activity' => 'Anonymous Donor - Maya E-Wallet', 'donation_added' => 3500, 'shelter_spent' => null, 'recorded_by' => $volunteer->full_name, 'created_at' => now(), 'updated_at' => now()],
                ['recorded_date' => now()->subDays(2)->toDateString(), 'activity' => 'Spay & Neuter Outreach Clinic Operations', 'donation_added' => null, 'shelter_spent' => 4500, 'recorded_by' => $admin->full_name, 'created_at' => now(), 'updated_at' => now()],
                ['recorded_date' => now()->subDays(1)->toDateString(), 'activity' => 'Walk-in Shelter Cash Donation', 'donation_added' => 2000, 'shelter_spent' => null, 'recorded_by' => $volunteer->full_name, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // 8. Audit Logs
        if (AuditLog::count() === 0) {
            AuditLog::record($admin, 'Logged into Admin Dashboard');
            AuditLog::record($admin, 'Approved adoption application for Mimi');
            AuditLog::record($volunteer, 'Completed pet assessment for Bruno');
            AuditLog::record($admin, 'Scheduled interview with Maria Santos for pet Bruno');
            AuditLog::record($admin, 'Logged new community donation of ₱15,000');
            AuditLog::record($volunteer, 'Created intervention ticket for flagged case #2');
        }
    }
}
