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
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AdopterDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Adopter dummy data cannot be seeded in production.');
        }

        $documentPath = 'adoption-documents/demo-government-id.pdf';
        Storage::disk('local')->put($documentPath, $this->demoPdf());

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
            'Oliver' => [
                'species' => Species::Cat,
                'breed' => 'Tabby',
                'age' => 24,
                'sex' => 'Male',
                'health_status' => 'Good',
                'availability_status' => AvailabilityStatus::Available,
                'description' => 'Oliver is an adventurous and affectionate Tabby cat who loves climbing cat trees and purring happily.',
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

        // ─── 3. Seed Fresh Pending Applicant (Carlos Mendoza) ─────────────────
        $carlos = User::updateOrCreate(
            ['email' => 'carlos@example.com'],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Mendoza',
                'password' => Hash::make('password'),
                'role' => Role::Adopter->value,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        AdopterProfile::updateOrCreate(
            ['user_id' => $carlos->id],
            [
                'physical_activity_level' => 'Active (1-2 hours daily outdoor activity)',
                'time_availability' => 'Flexible / remote work (minimal alone time)',
                'prior_pet_experience' => 'Experienced pet parent',
                'housing_type' => 'House with large yard',
                'household_composition' => 'Family with older children',
                'monthly_income_range' => 'PHP 100,000 and above',
            ]
        );

        // Application: Max — Pending (New submission waiting for staff review!)
        AdoptionApplication::updateOrCreate(
            ['user_id' => $carlos->id, 'pet_id' => $pets['Max']->id],
            [
                'applicant_first_name' => 'Carlos',
                'applicant_last_name' => 'Mendoza',
                'applicant_email' => 'carlos@example.com',
                'applicant_phone' => '+63 917 555 0192',
                'applicant_region' => 'NCR',
                'applicant_province' => 'Metro Manila',
                'applicant_city_municipality' => 'Quezon City',
                'applicant_barangay' => 'Diliman',
                'applicant_street_address' => '45 University Ave',
                'applicant_zip_code' => '1101',
                'status' => ApplicationStatus::Pending,
                'is_primary_candidate' => false,
                'physical_activity_level' => 'Active (1-2 hours daily outdoor activity)',
                'time_availability' => 'Flexible / remote work (minimal alone time)',
                'prior_pet_experience' => 'Experienced pet parent',
                'housing_type' => 'House with large yard',
                'household_composition' => 'Family with older children',
                'income_range' => 'PHP 100,000 and above',
                'motivation_statement' => 'We have a spacious house, an active family routine, and love going on weekend morning runs.',
                'document_disk' => 'local',
                'document_path' => 'adoption-documents/demo-government-id.pdf',
                'document_original_name' => 'Philippine_Passport_Carlos_Mendoza.pdf',
                'document_mime_type' => 'application/pdf',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_uploaded_at' => now()->subHours(3),
                'ocr_confidence' => 96.0,
                'document_match_score' => 94.0,
                'document_type' => 'Passport',
                'knn_score' => 88.0,
                'compatibility_result' => [
                    'overall' => 88,
                    'rows' => [
                        ['label' => 'Activity & Exercise Match', 'percent' => 92],
                        ['label' => 'Living Space & Yard', 'percent' => 95],
                        ['label' => 'Prior Pet Experience', 'percent' => 85],
                        ['label' => 'Time Availability', 'percent' => 80],
                    ],
                ],
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ]
        );
    }

    private function seedAdopterFlowForUser(User $user, array $pets): void
    {
        $isJane = ($user->email === 'adopter@example.com');

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
                'physical_activity_level' => 'Moderate (30-60 mins daily walks/play)',
                'time_availability' => 'Part-time away (4-6 hours alone)',
                'prior_pet_experience' => 'Currently own pets',
                'housing_type' => 'House with fenced yard',
                'household_composition' => 'Adults only (2+ adults)',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'motivation_statement' => 'We are excited to provide a calm, caring home for Mochi where she can lounge safely.',
                'document_disk' => 'local',
                'document_path' => 'adoption-documents/demo-government-id.pdf',
                'document_original_name' => 'Philippine_National_ID_' . $user->first_name . '.pdf',
                'document_mime_type' => 'application/pdf',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_uploaded_at' => now()->subMonths(1),
                'document_verified_at' => now()->subMonths(1),
                'document_type' => 'National ID',
                'ocr_confidence' => 97.5,
                'document_match_score' => 96.0,
                'knn_score' => 88.5,
                'compatibility_result' => [
                    'overall' => 89,
                    'rows' => [
                        ['label' => 'Temperament Alignment', 'percent' => 95],
                        ['label' => 'Living Environment', 'percent' => 92],
                        ['label' => 'Care Routine & Schedule', 'percent' => 85],
                        ['label' => 'Financial Readiness', 'percent' => 84],
                    ],
                ],
                'interview_date' => now()->subMonths(1)->subDays(4)->setHour(10)->setMinute(30),
                'conducted_by' => 'Staff Volunteer',
                'interview_notes' => 'Excellent interview. Adopter demonstrated a peaceful home perfectly tailored for Persian cats with proof of cat-safe window screens.',
                'decision_remarks' => 'Approved unanimously by shelter panel. Meets all home environment and safety requirements.',
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
                'physical_activity_level' => 'Low (occasional short walks/indoor play)',
                'time_availability' => 'Flexible / remote work (minimal alone time)',
                'prior_pet_experience' => 'Experienced pet parent',
                'housing_type' => 'Apartment/Condo',
                'household_composition' => 'Adults only (1 adult)',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'motivation_statement' => 'We fell in love with Mimi and have everything prepared for her arrival, including custom cat perches and scratching posts.',
                'document_disk' => 'local',
                'document_path' => 'adoption-documents/demo-government-id.pdf',
                'document_original_name' => 'Passport_' . $user->first_name . '.pdf',
                'document_mime_type' => 'application/pdf',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_uploaded_at' => now()->subDays(4),
                'document_verified_at' => now()->subDays(3),
                'document_type' => 'Passport',
                'ocr_confidence' => 95.0,
                'document_match_score' => 93.0,
                'knn_score' => 86.0,
                'compatibility_result' => [
                    'overall' => 86,
                    'rows' => [
                        ['label' => 'Apartment Suitability', 'percent' => 90],
                        ['label' => 'Quiet Companion Fit', 'percent' => 88],
                        ['label' => 'Health & Grooming Plan', 'percent' => 82],
                        ['label' => 'Time Dedication', 'percent' => 84],
                    ],
                ],
                'interview_date' => now()->subDays(6)->setHour(14)->setMinute(0),
                'conducted_by' => 'Rina Sarmiento',
                'interview_notes' => 'Adopter showed apartment layout via video call. Very clean, quiet building with pet-friendly HOA clearance.',
                'decision_remarks' => 'Application approved for immediate placement. Dispatch scheduled via courier.',
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
        $buddyData = [
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
            'is_primary_candidate' => true,
            'physical_activity_level' => 'Very Active (2+ hours intense exercise/hiking)',
            'time_availability' => 'Part-time away (4-6 hours alone)',
            'prior_pet_experience' => 'Experienced pet parent',
            'housing_type' => 'House with fenced yard',
            'household_composition' => 'Family with older children',
            'income_range' => 'PHP 50,000 - PHP 99,999',
            'motivation_statement' => 'Buddy matches our active outdoor routine and we would love to welcome him on weekend morning hikes.',
            'document_disk' => 'local',
            'document_path' => 'adoption-documents/demo-government-id.pdf',
            'document_original_name' => 'Drivers_License_' . $user->first_name . '.pdf',
            'document_mime_type' => 'application/pdf',
            'document_verification_status' => DocumentVerificationStatus::Verified,
            'document_uploaded_at' => now()->subDays(2),
            'document_verified_at' => now()->subDay(),
            'document_type' => 'Driver License',
            'ocr_confidence' => 98.0,
            'document_match_score' => 97.0,
            'knn_score' => 84.0,
            'compatibility_result' => [
                'overall' => 84,
                'rows' => [
                    ['label' => 'Energy & Outdoor Match', 'percent' => 88],
                    ['label' => 'Yard Space & Safety', 'percent' => 85],
                    ['label' => 'Training Experience', 'percent' => 80],
                    ['label' => 'Schedule Compatibility', 'percent' => 83],
                ],
            ],
            'interview_date' => now()->addDays(2)->setHour(14)->setMinute(0),
            'conducted_by' => 'Staff Volunteer',
            'interview_notes' => 'Candidate passed pre-screening questionnaire. Video interview scheduled with family members.',
            'reschedule_requested_at' => null,
            'reschedule_reason' => null,
            'reschedule_options' => null,
            'reschedule_status' => null,
            'reschedule_reviewed_at' => null,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDay(),
        ];

        AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Buddy']->id],
            $buddyData
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
                'physical_activity_level' => 'Low (occasional short walks/indoor play)',
                'time_availability' => 'Full-time away (8+ hours alone)',
                'prior_pet_experience' => 'First-time pet owner',
                'housing_type' => 'Apartment/Condo',
                'household_composition' => 'Adults only (1 adult)',
                'income_range' => 'PHP 30,000 - PHP 49,999',
                'motivation_statement' => 'Looking for an affectionate feline companion for our quiet home.',
                'document_disk' => 'local',
                'document_path' => 'adoption-documents/demo-government-id.pdf',
                'document_original_name' => 'Postal_ID_' . $user->first_name . '.pdf',
                'document_mime_type' => 'application/pdf',
                'document_verification_status' => DocumentVerificationStatus::NeedsResubmission,
                'document_verification_reasons' => [
                    'The uploaded government ID was blurry and edge details were truncated.',
                    'Proof of billing does not clearly show applicant address.',
                ],
                'document_type' => 'Postal ID',
                'ocr_confidence' => 62.0,
                'document_match_score' => 58.0,
                'knn_score' => 68.0,
                'compatibility_result' => [
                    'overall' => 68,
                    'rows' => [
                        ['label' => 'Cat Companion Match', 'percent' => 72],
                        ['label' => 'Quiet Environment', 'percent' => 70],
                        ['label' => 'Prior Experience', 'percent' => 62],
                        ['label' => 'Financial Readiness', 'percent' => 68],
                    ],
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
                'physical_activity_level' => 'Low (occasional short walks/indoor play)',
                'time_availability' => 'Full-time away (8+ hours alone)',
                'prior_pet_experience' => 'First-time pet owner',
                'housing_type' => 'Apartment/Condo',
                'household_composition' => 'Adults only (1 adult)',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'motivation_statement' => 'Interested in adopting Max for our family.',
                'document_disk' => 'local',
                'document_path' => 'adoption-documents/demo-government-id.pdf',
                'document_original_name' => 'National_ID_' . $user->first_name . '.pdf',
                'document_mime_type' => 'application/pdf',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_type' => 'National ID',
                'ocr_confidence' => 95.0,
                'document_match_score' => 92.0,
                'interview_date' => now()->subDays(18)->setHour(11)->setMinute(0),
                'conducted_by' => 'Staff Volunteer',
                'interview_notes' => 'Candidate was polite and enthusiastic, but works 12-hour shifts away from home. High-energy working dog cannot be left unattended in an apartment.',
                'decision_remarks' => 'Thank you for applying. Max has very high exercise requirements and separation anxiety tendencies, requiring an owner with a large fenced yard and more daily home presence.',
                'knn_score' => 35.0,
                'compatibility_result' => [
                    'overall' => 35,
                    'rows' => [
                        ['label' => 'Daily Exercise Match', 'percent' => 30],
                        ['label' => 'Time at Home', 'percent' => 25],
                        ['label' => 'Space & Fencing', 'percent' => 45],
                        ['label' => 'Breed Experience', 'percent' => 40],
                    ],
                ],
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
                'physical_activity_level' => 'Moderate (30-60 mins daily walks/play)',
                'time_availability' => 'Flexible / remote work (minimal alone time)',
                'prior_pet_experience' => 'Experienced pet parent',
                'housing_type' => 'House with fenced yard',
                'household_composition' => 'Family with older children',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'motivation_statement' => 'Coco has been a wonderful light in our family and gets daily grooming and loving attention.',
                'document_disk' => 'local',
                'document_path' => 'adoption-documents/demo-government-id.pdf',
                'document_original_name' => 'Passport_Coco_' . $user->first_name . '.pdf',
                'document_mime_type' => 'application/pdf',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_type' => 'Passport',
                'ocr_confidence' => 96.0,
                'document_match_score' => 94.0,
                'interview_date' => now()->subMonths(2)->subDays(5)->setHour(15)->setMinute(0),
                'conducted_by' => 'Staff Volunteer',
                'interview_notes' => 'Wonderful home visit. Coco bonded immediately with family members during the interaction.',
                'decision_remarks' => 'Approved for placement. Family provided safe play area and reliable veterinary plan.',
                'knn_score' => 82.0,
                'compatibility_result' => [
                    'overall' => 82,
                    'rows' => [
                        ['label' => 'Lap Dog Companion Match', 'percent' => 88],
                        ['label' => 'Indoor Environment', 'percent' => 85],
                        ['label' => 'Gentle Handling Care', 'percent' => 78],
                        ['label' => 'Lifestyle Alignment', 'percent' => 77],
                    ],
                ],
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
                'physical_activity_level' => 'Active (1-2 hours daily outdoor activity)',
                'time_availability' => 'Part-time away (4-6 hours alone)',
                'prior_pet_experience' => 'Experienced pet parent',
                'housing_type' => 'House with large yard',
                'household_composition' => 'Adults only (2+ adults)',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'motivation_statement' => 'Dedicated to Bruno rehabilitation and daily care with patient positive-reinforcement training.',
                'document_disk' => 'local',
                'document_path' => 'adoption-documents/demo-government-id.pdf',
                'document_original_name' => 'National_ID_Bruno_' . $user->first_name . '.pdf',
                'document_mime_type' => 'application/pdf',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_type' => 'National ID',
                'ocr_confidence' => 95.0,
                'document_match_score' => 93.0,
                'interview_date' => now()->subMonths(3)->subDays(4)->setHour(13)->setMinute(30),
                'conducted_by' => 'Staff Volunteer',
                'interview_notes' => 'Adopter has fenced garden and prior rehabilitation experience with rescue dogs.',
                'decision_remarks' => 'Approved with agreement for 3-month post-placement veterinary checkups.',
                'compatibility_result' => [
                    'overall' => 79,
                    'rows' => [
                        ['label' => 'Rehab Experience Fit', 'percent' => 85],
                        ['label' => 'Secure Yard', 'percent' => 82],
                        ['label' => 'Patience & Training', 'percent' => 75],
                        ['label' => 'Household Dynamics', 'percent' => 74],
                    ],
                ],
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

        // ─── Application 8: Oliver — Under Review ──────────────────────────────
        AdoptionApplication::updateOrCreate(
            ['user_id' => $user->id, 'pet_id' => $pets['Oliver']->id],
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
                'status' => ApplicationStatus::UnderReview,
                'is_primary_candidate' => true,
                'physical_activity_level' => 'Moderate (30-60 mins daily walks/play)',
                'time_availability' => 'Flexible / remote work (minimal alone time)',
                'prior_pet_experience' => 'Currently own pets',
                'housing_type' => 'House with fenced yard',
                'household_composition' => 'Family with older children',
                'income_range' => 'PHP 50,000 - PHP 99,999',
                'motivation_statement' => 'We have a quiet, loving space ready for Oliver with plenty of sunny spots and comfortable resting perches.',
                'document_disk' => 'local',
                'document_path' => 'adoption-documents/demo-government-id.pdf',
                'document_original_name' => 'Philippine_National_ID_' . $user->first_name . '.pdf',
                'document_mime_type' => 'application/pdf',
                'document_verification_status' => DocumentVerificationStatus::Verified,
                'document_uploaded_at' => now()->subHours(6),
                'document_type' => 'National ID',
                'ocr_confidence' => 94.2,
                'document_match_score' => 91.5,
                'knn_score' => 85.0,
                'compatibility_result' => [
                    'overall' => 85,
                    'rows' => [
                        ['label' => 'Indoor Cat Environment', 'percent' => 95],
                        ['label' => 'Enrichment & Play Space', 'percent' => 90],
                        ['label' => 'Diet & Vet Care Plan', 'percent' => 82],
                        ['label' => 'Time Availability', 'percent' => 88],
                    ],
                ],
                'interview_date' => now()->subHours(18)->setHour(16)->setMinute(0),
                'conducted_by' => 'Staff Volunteer',
                'interview_notes' => 'Interview completed smoothly. Adopter presented dedicated cat space with scratching posts, window bed, and veterinary vaccine records.',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subHours(6),
            ]
        );
    }

    private function demoPdf(): string
    {
        $stream = "BT /F1 18 Tf 50 760 Td (PAIRFECT PAWS DEMONSTRATION GOVERNMENT ID) Tj ET\nBT /F1 12 Tf 50 730 Td (Name: Jane Doe | Address: 12 Acacia Street, Katipunan, Quezon City) Tj ET\nBT /F1 12 Tf 50 705 Td (Valid Philippine Government Issued Identification - Local Testing Only) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
