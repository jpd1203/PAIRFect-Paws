<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Milestone;
use App\Enums\PetCurrentStatus;
use App\Enums\Role;
use App\Enums\Species;
use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Branch;
use App\Models\Handover;
use App\Models\HandoverNotification;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdopterDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::first() ?? Branch::create([
            'name' => 'Main Shelter',
            'address' => '123 Shelter Street, Quezon City',
            'contact_number' => '(02) 8123-4567',
        ]);

        // ─── 1. Ensure Pets Exist ──────────────────────────────────────────────
        $petsData = [
            'Mochi' => [
                'species' => Species::Cat,
                'breed' => 'Persian',
                'age' => 12,
                'sex' => 'Female',
                'health_status' => 'Healthy',
                'availability_status' => AvailabilityStatus::Adopted,
                'description' => 'Mochi is a sweet, docile Persian cat who loves quiet mornings, soft cushions, and gentle chin scratches.',
            ],
            'Mimi' => [
                'species' => Species::Cat,
                'breed' => 'Persian Mix',
                'age' => 24,
                'sex' => 'Female',
                'health_status' => 'Healthy',
                'availability_status' => AvailabilityStatus::SoftReserved,
                'description' => 'Mimi is a gentle soul who purrs happily when brushed and enjoys resting by sunlit windows.',
            ],
            'Buddy' => [
                'species' => Species::Dog,
                'breed' => 'Golden Retriever',
                'age' => 36,
                'sex' => 'Male',
                'health_status' => 'Good',
                'availability_status' => AvailabilityStatus::Available,
                'description' => 'Buddy is an enthusiastic, affectionate retriever who loves fetch, swimming, and outdoor adventures.',
            ],
            'Luna' => [
                'species' => Species::Cat,
                'breed' => 'Siamese',
                'age' => 20,
                'sex' => 'Female',
                'health_status' => 'Excellent',
                'availability_status' => AvailabilityStatus::Available,
                'description' => 'Luna is an inquisitive and vocal Siamese who loves interacting with visitors and playing with feather wands.',
            ],
            'Max' => [
                'species' => Species::Dog,
                'breed' => 'Labrador',
                'age' => 48,
                'sex' => 'Male',
                'health_status' => 'Good',
                'availability_status' => AvailabilityStatus::Available,
                'description' => 'Max is a high-energy Labrador with a big heart who excels with active families with large yards.',
            ],
            'Coco' => [
                'species' => Species::Dog,
                'breed' => 'Shih Tzu',
                'age' => 24,
                'sex' => 'Female',
                'health_status' => 'Excellent',
                'availability_status' => AvailabilityStatus::Adopted,
                'description' => 'Coco is a charming, friendly lap dog who enjoys being pampered and meeting people.',
            ],
            'Bruno' => [
                'species' => Species::Dog,
                'breed' => 'Mixed Breed',
                'age' => 30,
                'sex' => 'Male',
                'health_status' => 'Good',
                'availability_status' => AvailabilityStatus::Adopted,
                'description' => 'Bruno is an alert and loyal companion rescued from an industrial park who thrives in a loving home.',
            ],
        ];

        $pets = [];
        foreach ($petsData as $name => $data) {
            $pets[$name] = Pet::firstOrCreate(
                ['name' => $name],
                array_merge($data, [
                    'branch_id' => $branch->id,
                    'intake_date' => now()->subMonths(4),
                    'is_archived' => false,
                    'version' => 1,
                    'physical_size' => 'Medium',
                    'energy_level' => 3.5,
                    'trainability' => 4.0,
                    'independence' => 3.0,
                    'temperament' => 4.5,
                ])
            );
        }

        // ─── 2. Seed Adopter Accounts ─────────────────────────────────────────
        $adopterEmails = ['adopter@example.com', 'jessa@example.com'];

        foreach ($adopterEmails as $email) {
            $isJane = ($email === 'adopter@example.com');
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $isJane ? 'Jane' : 'Jessa',
                    'last_name' => $isJane ? 'Doe' : 'Dela Cruz',
                    'password' => Hash::make('password'),
                    'role' => Role::Adopter->value,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            // Adopter Profile for Recommendations
            AdopterProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'physical_activity_level' => 'Moderate (30-60 mins daily walks/play)',
                    'time_availability' => 'Part-time away (4-6 hours alone)',
                    'prior_pet_experience' => 'Currently own pets',
                    'housing_type' => 'House with fenced yard',
                    'household_composition' => 'Adults only (2+ adults)',
                    'monthly_income_range' => 'PHP 50,000 - PHP 99,999',
                ]
            );

            $this->seedAdopterFlowForUser($user, $pets);
        }
    }

    private function seedAdopterFlowForUser(User $user, array $pets): void
    {
        // ─── Application 1: Mochi — Adopted (Unlocks Post-Adoption Check-ins!) ───
        $appMochi = AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Mochi']->id],
            [
                'applicant_first_name' => $user->first_name,
                'applicant_last_name' => $user->last_name,
                'applicant_email' => $user->email,
                'applicant_phone' => '+63 918 985 2149',
                'applicant_region' => 'NCR',
                'applicant_province' => 'Metro Manila',
                'applicant_city_municipality' => 'Quezon City',
                'applicant_barangay' => 'Katipunan',
                'applicant_street_address' => '12 Acacia Street',
                'applicant_zip_code' => '1105',
                'status' => ApplicationStatus::Approved,
                'motivation_statement' => 'We are excited to provide a calm, caring home for Mochi where she can lounge safely.',
                'housing_type' => 'House with fenced yard',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_uploaded_at' => now()->subMonths(1),
                'document_verified_at' => now()->subMonths(1),
                'knn_score' => 88.5,
                'compatibility_result' => ['overall' => 89, 'energy' => 90, 'space' => 92, 'experience' => 85],
                'adopted_at' => now()->subMonths(1),
                'created_at' => now()->subMonths(1)->subDays(7),
                'updated_at' => now()->subMonths(1),
            ]
        );

        // Handover for Mochi: Received -> Triggers $hasReceivedPet = true in sidebar!
        $handoverMochi = Handover::updateOrCreate(
            ['code' => 'hv-mochi-' . $user->id],
            [
                'application_id' => $appMochi->id,
                'pet_id' => $pets['Mochi']->id,
                'user_id' => $user->id,
                'adopter_name' => $user->first_name . ' ' . $user->last_name,
                'adopter_phone' => '+63 918 985 2149',
                'adopter_email' => $user->email,
                'adopter_address' => '12 Acacia Street, Katipunan, Quezon City',
                'adopter_distance' => '5 km from shelter',
                'approved_at' => now()->subMonths(1)->subDays(2),
                'release_method' => 'pickup',
                'release_date' => now()->subMonths(1)->toDateString(),
                'release_time' => '10:00',
                'staff_name' => 'Shelter Staff',
                'proof_name' => 'mochi-handover.jpg',
                'proof_url' => 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?auto=format&fit=crop&w=400&q=80',
                'released_at' => now()->subMonths(1),
                'adopter_outcome' => 'received',
                'adopter_confirmed_at' => now()->subMonths(1)->addHours(2),
                'adopter_note' => 'Picked Mochi up smoothly. She is purring on her new bed.',
                'reopen_count' => 0,
                'history' => [
                    ['at' => now()->subMonths(1)->subDays(2)->toIso8601String(), 'label' => 'Application approved', 'actor' => 'Staff'],
                    ['at' => now()->subMonths(1)->toIso8601String(), 'label' => 'Marked as released at shelter', 'actor' => 'Staff'],
                    ['at' => now()->subMonths(1)->addHours(2)->toIso8601String(), 'label' => 'Adopter confirmed receipt', 'actor' => $user->first_name],
                    ['at' => now()->subMonths(1)->addHours(2)->toIso8601String(), 'label' => 'Post-adoption monitoring activated', 'actor' => 'System'],
                ],
            ]
        );

        HandoverNotification::updateOrCreate(
            ['handover_id' => $handoverMochi->id, 'kind' => 'completed'],
            [
                'user_id' => $user->id,
                'title' => 'Welcome home, Mochi!',
                'body' => 'Thank you for confirming receipt. Your adoption is complete and post-adoption check-ins are active.',
                'channels' => ['In-app', 'Email'],
                'action_label' => 'View my check-ins',
                'action_url' => '/monitoring/my-checkins',
                'read' => true,
                'created_at' => now()->subMonths(1)->addHours(2),
            ]
        );

        // Post-Adoption Check-in 1: 3 Days (Submitted & Completed)
        PostAdoptionLog::updateOrCreate(
            ['application_id' => $appMochi->id, 'milestone' => Milestone::ThreeDays->value],
            [
                'scheduled_date' => now()->subDays(25)->toDateString(),
                'submitted_date' => now()->subDays(24),
                'pet_current_status' => PetCurrentStatus::Excellent,
                'behavioral_observations' => 'Mochi is very calm and curious. She enjoys curling up on the sofa and watching birds through the window.',
                'living_conditions' => 'Spacious indoor environment with multiple scratching posts, warm bedding, and fresh water bowls.',
                'eating_habits' => 'She has finished both morning and evening meals with healthy appetite.',
                'vet_visit_details' => 'Visited local clinic for initial welcome checkup; vaccines and weight confirmed good.',
                'concerns' => 'None at this time; adjusting beautifully.',
                'is_flagged' => false,
                'reminders_sent' => 0,
            ]
        );

        // Post-Adoption Check-in 2: 3 Weeks (Due Today! Shows in "Submit Report")
        PostAdoptionLog::updateOrCreate(
            ['application_id' => $appMochi->id, 'milestone' => Milestone::ThreeWeeks->value],
            [
                'scheduled_date' => now()->toDateString(),
                'submitted_date' => null,
                'is_flagged' => false,
                'reminders_sent' => 0,
            ]
        );

        // Post-Adoption Check-in 3: 3 Months (Upcoming)
        PostAdoptionLog::updateOrCreate(
            ['application_id' => $appMochi->id, 'milestone' => Milestone::ThreeMonths->value],
            [
                'scheduled_date' => now()->addMonths(2)->toDateString(),
                'submitted_date' => null,
                'is_flagged' => false,
                'reminders_sent' => 0,
            ]
        );

        // ─── Application 2: Mimi — Released, Waiting for Confirmation! ─────────
        $appMimi = AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Mimi']->id],
            [
                'applicant_first_name' => $user->first_name,
                'applicant_last_name' => $user->last_name,
                'applicant_email' => $user->email,
                'applicant_phone' => '+63 918 985 2149',
                'applicant_region' => 'NCR',
                'applicant_province' => 'Metro Manila',
                'applicant_city_municipality' => 'Quezon City',
                'applicant_barangay' => 'Katipunan',
                'applicant_street_address' => 'Unit 7B Katipunan Ave',
                'applicant_zip_code' => '1105',
                'status' => ApplicationStatus::Approved,
                'motivation_statement' => 'We fell in love with Mimi and have everything prepared for her arrival.',
                'housing_type' => 'Apartment/Condo',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_uploaded_at' => now()->subDays(4),
                'document_verified_at' => now()->subDays(3),
                'adopted_at' => now()->subDays(2),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(2),
            ]
        );

        // Handover for Mimi: Delivery in progress, adopter_outcome = null (Awaiting Confirmation!)
        $handoverMimi = Handover::updateOrCreate(
            ['code' => 'hv-mimi-' . $user->id],
            [
                'application_id' => $appMimi->id,
                'pet_id' => $pets['Mimi']->id,
                'user_id' => $user->id,
                'adopter_name' => $user->first_name . ' ' . $user->last_name,
                'adopter_phone' => '+63 918 985 2149',
                'adopter_email' => $user->email,
                'adopter_address' => 'Unit 7B Katipunan Ave, Quezon City',
                'adopter_distance' => '8 km from shelter',
                'approved_at' => now()->subDays(3),
                'release_method' => 'delivery',
                'release_date' => now()->subDay()->toDateString(),
                'release_time' => '10:30',
                'staff_name' => 'Rina Sarmiento',
                'courier' => 'Lalamove',
                'tracking_number' => 'LLM-77341902',
                'proof_name' => 'mimi-crate-dispatch.jpg',
                'proof_url' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=400&q=80',
                'released_at' => now()->subDay(),
                'adopter_outcome' => null, // Waiting for adopter confirmation!
                'reopen_count' => 0,
                'history' => [
                    ['at' => now()->subDays(3)->toIso8601String(), 'label' => 'Application approved', 'actor' => 'Admin Staff'],
                    ['at' => now()->subDay()->toIso8601String(), 'label' => 'Marked as released via Lalamove', 'actor' => 'Rina Sarmiento'],
                    ['at' => now()->subHours(6)->toIso8601String(), 'label' => 'Automated reminder sent to confirm receipt', 'actor' => 'System'],
                ],
            ]
        );

        // Handover notifications for Mimi
        HandoverNotification::updateOrCreate(
            ['handover_id' => $handoverMimi->id, 'kind' => 'released'],
            [
                'user_id' => $user->id,
                'title' => 'Mimi is on the way via Lalamove delivery',
                'body' => 'Tracking: LLM-77341902. Please confirm once Mimi has safely arrived at your home.',
                'channels' => ['In-app', 'Email', 'SMS'],
                'action_label' => 'Confirm receipt',
                'action_url' => "/confirm/{$handoverMimi->id}",
                'read' => false,
                'created_at' => now()->subDay(),
            ]
        );

        HandoverNotification::updateOrCreate(
            ['handover_id' => $handoverMimi->id, 'kind' => 'reminder'],
            [
                'user_id' => $user->id,
                'title' => 'Reminder: Please confirm receipt of Mimi',
                'body' => 'Confirming receipt finalizes your adoption paperwork and activates post-adoption care.',
                'channels' => ['In-app', 'SMS'],
                'action_label' => 'Confirm receipt',
                'action_url' => "/confirm/{$handoverMimi->id}",
                'read' => false,
                'created_at' => now()->subHours(6),
            ]
        );

        // ─── Application 3: Buddy — Interview Scheduled ─────────────────────────
        AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Buddy']->id],
            [
                'applicant_first_name' => $user->first_name,
                'applicant_last_name' => $user->last_name,
                'applicant_email' => $user->email,
                'applicant_phone' => '+63 918 985 2149',
                'applicant_region' => 'NCR',
                'applicant_province' => 'Metro Manila',
                'applicant_city_municipality' => 'Quezon City',
                'applicant_barangay' => 'Katipunan',
                'applicant_street_address' => '12 Acacia Street',
                'applicant_zip_code' => '1105',
                'status' => ApplicationStatus::InterviewScheduled,
                'motivation_statement' => 'Buddy matches our active outdoor routine and we would love to welcome him.',
                'housing_type' => 'House with fenced yard',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_uploaded_at' => now()->subDays(2),
                'document_verified_at' => now()->subDay(),
                'knn_score' => 74.0,
                'compatibility_result' => ['overall' => 74, 'energy' => 78, 'space' => 72, 'experience' => 70],
                'interview_date' => now()->addDays(2)->setHour(14)->setMinute(0),
                'conducted_by' => 'Staff Volunteer',
                'interview_notes' => 'Virtual interview scheduled via Google Meet: meet.google.com/paws-buddy-101. Please be on time with a valid government ID.',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDay(),
            ]
        );

        // ─── Application 4: Luna — Document Flagged (Needs Resubmission) ────────
        AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Luna']->id],
            [
                'applicant_first_name' => $user->first_name,
                'applicant_last_name' => $user->last_name,
                'applicant_email' => $user->email,
                'applicant_phone' => '+63 918 985 2149',
                'applicant_region' => 'NCR',
                'applicant_province' => 'Metro Manila',
                'applicant_city_municipality' => 'Quezon City',
                'applicant_barangay' => 'Katipunan',
                'applicant_street_address' => '12 Acacia Street',
                'applicant_zip_code' => '1105',
                'status' => ApplicationStatus::DocumentFlagged,
                'motivation_statement' => 'Looking for an affectionate feline companion for our quiet home.',
                'housing_type' => 'House with fenced yard',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'document_verification_status' => DocumentVerificationStatus::NeedsResubmission,
                'document_verification_reasons' => [
                    'The uploaded government ID was blurry and edge details were truncated.',
                    'Proof of billing does not clearly show applicant address.',
                ],
                'document_reupload_count' => 0,
                'document_uploaded_at' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subHours(12),
            ]
        );

        // ─── Application 5: Max — Rejected ─────────────────────────────────────
        AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Max']->id],
            [
                'applicant_first_name' => $user->first_name,
                'applicant_last_name' => $user->last_name,
                'applicant_email' => $user->email,
                'applicant_phone' => '+63 918 985 2149',
                'applicant_region' => 'NCR',
                'applicant_province' => 'Metro Manila',
                'applicant_city_municipality' => 'Quezon City',
                'applicant_barangay' => 'Katipunan',
                'applicant_street_address' => '12 Acacia Street',
                'applicant_zip_code' => '1105',
                'status' => ApplicationStatus::Rejected,
                'motivation_statement' => 'Interested in adopting Max for our family.',
                'housing_type' => 'House with fenced yard',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'knn_score' => 35.0,
                'compatibility_result' => ['overall' => 35, 'energy' => 30, 'space' => 45, 'experience' => 30],
                'decision_remarks' => 'Thank you for applying. Max has very high exercise requirements and is best suited for a working property or experienced trainer.',
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(15),
            ]
        );

        // ─── Application 6: Coco — Approved with OVERDUE Check-in! ─────────────
        $appCoco = AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Coco']->id],
            [
                'applicant_first_name' => $user->first_name,
                'applicant_last_name' => $user->last_name,
                'applicant_email' => $user->email,
                'applicant_phone' => '+63 918 985 2149',
                'applicant_region' => 'NCR',
                'applicant_province' => 'Metro Manila',
                'applicant_city_municipality' => 'Quezon City',
                'applicant_barangay' => 'Katipunan',
                'applicant_street_address' => '12 Acacia Street',
                'applicant_zip_code' => '1105',
                'status' => ApplicationStatus::Approved,
                'motivation_statement' => 'Coco has been a wonderful light in our family.',
                'housing_type' => 'House with fenced yard',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'knn_score' => 52.0,
                'compatibility_result' => ['overall' => 52, 'energy' => 55, 'space' => 60, 'experience' => 42],
                'adopted_at' => now()->subMonths(2),
                'created_at' => now()->subMonths(2)->subDays(7),
                'updated_at' => now()->subMonths(2),
            ]
        );

        Handover::updateOrCreate(
            ['code' => 'hv-coco-' . $user->id],
            [
                'application_id' => $appCoco->id,
                'pet_id' => $pets['Coco']->id,
                'user_id' => $user->id,
                'adopter_name' => $user->first_name . ' ' . $user->last_name,
                'adopter_phone' => '+63 918 985 2149',
                'adopter_email' => $user->email,
                'adopter_address' => '12 Acacia Street, Katipunan, Quezon City',
                'approved_at' => now()->subMonths(2)->subDays(2),
                'release_method' => 'pickup',
                'release_date' => now()->subMonths(2)->toDateString(),
                'released_at' => now()->subMonths(2),
                'adopter_outcome' => 'received',
                'adopter_confirmed_at' => now()->subMonths(2),
                'history' => [
                    ['at' => now()->subMonths(2)->toIso8601String(), 'label' => 'Adopter confirmed receipt', 'actor' => $user->first_name],
                ],
            ]
        );

        // Overdue post-adoption check-in for Coco
        PostAdoptionLog::updateOrCreate(
            ['application_id' => $appCoco->id, 'milestone' => Milestone::ThreeWeeks->value],
            [
                'scheduled_date' => now()->subDays(6)->toDateString(),
                'submitted_date' => null, // Overdue!
                'reminders_sent' => 2,
                'last_reminder_sent_at' => now()->subDays(2),
                'is_flagged' => false,
            ]
        );

        // ─── Application 7: Bruno — Approved with FLAGGED Welfare Notice! ──────
        $appBruno = AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Bruno']->id],
            [
                'applicant_first_name' => $user->first_name,
                'applicant_last_name' => $user->last_name,
                'applicant_email' => $user->email,
                'applicant_phone' => '+63 918 985 2149',
                'applicant_region' => 'NCR',
                'applicant_province' => 'Metro Manila',
                'applicant_city_municipality' => 'Quezon City',
                'applicant_barangay' => 'Katipunan',
                'applicant_street_address' => '12 Acacia Street',
                'applicant_zip_code' => '1105',
                'status' => ApplicationStatus::Approved,
                'motivation_statement' => 'Dedicated to Bruno rehabilitation and daily care.',
                'housing_type' => 'House with fenced yard',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'adopted_at' => now()->subMonths(3),
                'created_at' => now()->subMonths(3)->subDays(7),
                'updated_at' => now()->subMonths(3),
            ]
        );

        Handover::updateOrCreate(
            ['code' => 'hv-bruno-' . $user->id],
            [
                'application_id' => $appBruno->id,
                'pet_id' => $pets['Bruno']->id,
                'user_id' => $user->id,
                'adopter_name' => $user->first_name . ' ' . $user->last_name,
                'adopter_phone' => '+63 918 985 2149',
                'adopter_email' => $user->email,
                'adopter_address' => '12 Acacia Street, Katipunan, Quezon City',
                'approved_at' => now()->subMonths(3)->subDays(2),
                'release_method' => 'pickup',
                'release_date' => now()->subMonths(3)->toDateString(),
                'released_at' => now()->subMonths(3),
                'adopter_outcome' => 'received',
                'adopter_confirmed_at' => now()->subMonths(3),
                'history' => [
                    ['at' => now()->subMonths(3)->toIso8601String(), 'label' => 'Adopter confirmed receipt', 'actor' => $user->first_name],
                ],
            ]
        );

        // Flagged post-adoption check-in for Bruno
        PostAdoptionLog::updateOrCreate(
            ['application_id' => $appBruno->id, 'milestone' => Milestone::ThreeDays->value],
            [
                'scheduled_date' => now()->subDays(12)->toDateString(),
                'submitted_date' => now()->subDays(11),
                'pet_current_status' => PetCurrentStatus::Fair,
                'behavioral_observations' => 'Bruno seemed unusually hesitant and skipped food on day 2.',
                'living_conditions' => 'Indoor yard and shaded kennel.',
                'eating_habits' => 'Reduced appetite noticed.',
                'concerns' => 'Staff follow-up: Shelter vet flagged report to request a video check-in on feeding routine.',
                'is_flagged' => true,
                'flag_reasons' => ['Reported reduced appetite and lethargy on initial intake.'],
                'resolved_at' => null, // Unresolved -> Appears in "Flagged Welfare Notice"!
            ]
        );
    }
}
