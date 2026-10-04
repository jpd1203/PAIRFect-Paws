<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Branch;
use App\Models\Pet;
use App\Models\User;
use App\Services\AdopterHistoryService;
use App\Services\HandoverService;
use App\Services\Matching\AdopterMatchingProfileService;
use App\Services\Matching\ApplicationMatchService;
use App\Services\Matching\BehaviorAssessmentService;
use App\Services\PostAdoptionScheduleService;
use App\Support\ApplicationOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** An opt-in, fictional, reproducible defense snapshot. Never called by DatabaseSeeder. */
final class DefenseDemoSeeder extends Seeder
{
    public const BRANCH = 'PAIRfect Paws Defense Demonstration (Fictional)';
    public const PASSWORD = 'DefenseDemo2026!';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Defense demo data cannot be seeded in production.');
        }

        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Defense demo data may only be seeded in local or testing environments.');
        }
        if (Branch::where('name', self::BRANCH)->exists()) {
            throw new RuntimeException('Defense demo data already exists. Restore your pre-demo database backup before reseeding.');
        }

        $now = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $documentPath = 'defense-demo/fictional-supporting-document.pdf';
        Storage::disk('local')->put($documentPath, $this->demoPdf());

        DB::transaction(function () use ($now, $documentPath): void {
            $branch = Branch::create([
                'name' => self::BRANCH,
                'address' => 'Demo data only, Quezon City, Metro Manila',
                'contact_number' => '0285550100',
            ]);
            DB::table('branches')->where('id', $branch->id)->update([
                'created_at' => $now->subDays(200)->utc(),
            ]);
            $admin = $this->user($branch, 'Evelyn', 'Cruz', Role::Administrator);
            $staff = [
                $admin,
                $this->user($branch, 'Rafael', 'Dizon', Role::Volunteer),
                $this->user($branch, 'Nina', 'Bautista', Role::Volunteer),
            ];
            $adopters = [];
            foreach ([
                'angela' => ['Angela', 'Reyes', 3, 4, 2, 4, false, 'Single Family Home (Fenced Yard)'],
                'miguel' => ['Miguel', 'Santos', 4, 4, 2, 4, false, 'Single Family Home (Fenced Yard)'],
                'patricia' => ['Patricia', 'Mendoza', 3, 4, 2, 3, false, 'Townhouse'],
                'carlo' => ['Carlo', 'Villanueva', 2, 3, 4, 3, false, 'Apartment / Condo'],
                'bea' => ['Bea', 'Navarro', 3, 4, 2, 3, true, 'Single Family Home (Fenced Yard)'],
                'dina' => ['Dina', 'Flores', 4, 4, 2, 4, false, 'Single Family Home (Fenced Yard)'],
            ] as $key => [$first, $last, $e, $c, $n, $o, $children, $housing]) {
                $adopters[$key] = $this->user($branch, $first, $last, Role::Adopter);
                app(AdopterMatchingProfileService::class)->save($adopters[$key], [
                    'physical_activity_level' => ApplicationOptions::PHYSICAL_ACTIVITY_LEVELS[1],
                    'time_availability' => ApplicationOptions::TIME_AVAILABILITY_OPTIONS[2],
                    'prior_pet_experience' => $key === 'angela'
                        ? ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS[3]
                        : ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS[1],
                    'housing_type' => $housing,
                    'household_composition' => $children ? ApplicationOptions::HOUSEHOLD_COMPOSITIONS[2] : ApplicationOptions::HOUSEHOLD_COMPOSITIONS[1],
                    'monthly_income_range' => ApplicationOptions::INCOME_RANGES[3],
                    'has_existing_pets' => false,
                    'has_children' => $children,
                    'bfi_responses' => $this->bfiAnswers([$e, $c, $n, $o]),
                ]);
            }
            DB::table('users')->where('branch_id', $branch->id)->update([
                'created_at' => $now->subDays(200)->utc(),
            ]);
            DB::table('adopter_profiles')->whereIn('user_id', collect($adopters)->pluck('id'))->update([
                'created_at' => $now->subDays(180)->utc(),
                'bfi_completed_at' => $now->subDays(180)->utc(),
            ]);

            $pets = [];
            foreach ([
                'luna' => ['Luna', 'Cat', 2, 3, 2, 3, 'Medium', false, false, 3],
                'nala' => ['Nala', 'Cat', 2, 3, 2, 1, 'Small', false, false, 3],
                'bruno' => ['Bruno', 'Dog', 3, 3, 2, 1, 'Medium', false, false, 3],
                'tala' => ['Tala', 'Dog', 4, 2, 2, 4, 'Large', true, false, 3],
                'milo' => ['Milo', 'Dog', 4, 3, 2, 1, 'Medium', false, false, 3],
                'mochi' => ['Mochi', 'Cat', 2, 3, 2, 1, 'Small', false, false, 2],
                'pepper' => ['Pepper', 'Dog', 3, 3, 2, 1, 'Medium', false, false, 3],
                'coco' => ['Coco', 'Cat', 2, 3, 2, 1, 'Small', false, false, 3],
            ] as $key => [$name, $species, $energy, $trainability, $attachment, $fear, $size, $aggression, $vocal, $observers]) {
                $intake = $now->subDays(in_array($key, ['luna', 'nala'], true) ? 150 : 40);
                $assessed = $intake->addDay()->setTime(11, 0)->utc();
                $pets[$key] = Pet::create([
                    'branch_id' => $branch->id, 'name' => $name, 'species' => $species,
                    'breed' => $species === 'Cat' ? 'Domestic Shorthair' : 'Mixed Breed',
                    'age' => 24, 'sex' => in_array($key, ['bruno', 'milo'], true) ? 'Male' : 'Female',
                    'health_status' => $key === 'luna' ? 'Stable (managed condition)' : 'Healthy',
                    'vaccination_record_status' => 'Up to date',
                    'description' => $key === 'luna'
                        ? 'Fictional shelter cat with a managed chronic condition and a scheduled veterinary follow-up.'
                        : 'Fictional defense demonstration animal; not an actual shelter listing.',
                    'behavioral_notes' => $aggression ? 'Documented aggression history; unsuitable for households with young children.' : 'Observed in routine shelter care.',
                    'availability_status' => AvailabilityStatus::Available->value,
                    'intake_date' => $intake->toDateString(),
                    'physical_size' => $size, 'medical_needs' => $key === 'luna' ? 4 : 1, 'life_stage' => 'adult',
                    'has_aggression_history' => $aggression, 'high_vocalization' => $vocal,
                ]);
                foreach (array_slice($staff, 0, $observers) as $observer) {
                    app(BehaviorAssessmentService::class)->record($pets[$key], $observer, [
                        'responses' => $this->behaviorAnswers(strtolower($species), $energy, $trainability, $attachment, $fear),
                    ]);
                }
                DB::table('assessment_records')->where('pet_id', $pets[$key]->id)->update([
                    'created_at' => $assessed, 'updated_at' => $assessed,
                ]);
                DB::table('pets')->where('id', $pets[$key]->id)->update([
                    'created_at' => $intake->setTime(9, 0)->utc(),
                    'last_assessed_at' => $assessed,
                ]);
            }

            $luna = $this->application($adopters['angela'], $pets['luna'], $now->subDays(113), $documentPath);
            $this->approvedSnapshot($luna, $now->subDays(110), $staff[1]);
            $nala = $this->application($adopters['carlo'], $pets['nala'], $now->subDays(110), $documentPath);
            $this->approvedSnapshot($nala, $now->subDays(105), $staff[2]);

            // Score each applicant with the real algorithm before any reservation. Timestamps fix FCFS ties.
            foreach ([
                ['miguel', 9], ['patricia', 8], ['carlo', 7],
            ] as [$key, $daysAgo]) {
                $this->application($adopters[$key], $pets['bruno'], $now->subDays($daysAgo), $documentPath);
            }
            $safety = $this->application($adopters['bea'], $pets['tala'], $now->subDays(6), $documentPath);
            $safety->update([
                'status' => ApplicationStatus::Rejected->value,
                'decision_remarks' => 'Safety rule: documented aggression history is unsuitable for a home with young children.',
            ]);
            $this->application($adopters['dina'], $pets['milo'], $now->subDays(2), $documentPath);
            $coco = $this->application($adopters['angela'], $pets['coco'], $now->subDays(5), $documentPath);
            $coco->update([
                'status' => ApplicationStatus::UnderReview->value, 'is_primary_candidate' => true,
                'interview_date' => $now->subDays(3)->setTime(14, 0)->utc(),
                'conducted_by' => $staff[2]->full_name,
                'interview_notes' => 'Fictional historical interview completed; final decision pending.',
            ]);
            $pets['coco']->update(['availability_status' => AvailabilityStatus::SoftReserved->value]);
            $pepper = $this->application($adopters['patricia'], $pets['pepper'], $now->subDays(4), $documentPath);
            $pepper->update([
                'status' => ApplicationStatus::InterviewScheduled->value,
                'is_primary_candidate' => true,
                'interview_date' => $now->addDays(2)->setTime(10, 0)->utc(),
                'conducted_by' => $staff[1]->full_name,
            ]);
            $pets['pepper']->update(['availability_status' => AvailabilityStatus::SoftReserved->value]);

            // This is a historical screening snapshot, not a fabricated OCR pass or live video verification.
            $this->checkinSnapshot($luna, '3_days', false);
            $this->checkinSnapshot($luna, '3_weeks', false);
            $this->checkinSnapshot($luna, '3_months', false);
            $this->checkinSnapshot($nala, '3_days', true);
            $this->checkinSnapshot($nala, '3_weeks', false);
            $nala->postAdoptionLogs()->where('milestone', '3_months')->update([
                'is_flagged' => true, 'reminders_sent' => 2,
                'flag_reasons' => json_encode([['code' => 'missed_check_in', 'message' => 'No 3-month report was filed after two reminders.']]),
            ]);

            if (app(AdopterHistoryService::class)->getSummary($adopters['carlo'])['review_status'] === 'no_recorded_concerns') {
                throw new RuntimeException('Defense screening scenario did not produce a history warning.');
            }
        });

        $this->command?->info('Fictional defense scenario seeded. Accounts and walkthrough: DEFENSE_WALKTHROUGH.md');
    }

    private function user(Branch $branch, string $first, string $last, Role $role): User
    {
        return User::create([
            'branch_id' => $branch->id, 'first_name' => $first, 'last_name' => $last,
            'email' => strtolower("{$first}.{$last}@defense.pairfectpaws.test"),
            'password' => self::PASSWORD, 'role' => $role->value,
            'email_verified_at' => now(), 'is_active' => true,
            'region' => 'National Capital Region', 'province' => 'Metro Manila',
            'city_municipality' => 'Quezon City', 'barangay' => 'Bagumbayan',
            'street_address' => '10 Demo Street', 'zip_code' => '1110',
        ]);
    }

    private function bfiAnswers(array $scores): array
    {
        $answers = [];
        foreach (array_combine(array_keys(config('matching.bfi.dimensions')), $scores) as $dimension => $score) {
            foreach (config("matching.bfi.dimensions.{$dimension}") as $key) {
                $answers[$key] = in_array($key, config('matching.bfi.reverse'), true) ? 6 - $score : $score;
            }
        }

        return $answers;
    }

    private function behaviorAnswers(string $species, int $energy, int $trainability, int $attachment, int $fear): array
    {
        $answers = [];
        foreach (config("matching.items.{$species}") as $group => $items) {
            $score = match ($group) {
                'energy' => $energy, 'trainability' => $trainability,
                'attachment', 'sociability', 'attention_seeking' => $attachment,
                default => $fear,
            };
            foreach ($items as $key => $prompt) {
                $answers[$group][$key] = in_array("{$species}.{$group}.{$key}", config('matching.behavior_reverse'), true)
                    ? 4 - $score : $score;
            }
        }

        return $answers;
    }

    private function application(User $user, Pet $pet, CarbonImmutable $submitted, string $documentPath): AdoptionApplication
    {
        $profile = $user->adopterProfile;
        $application = AdoptionApplication::create([
            'user_id' => $user->id, 'pet_id' => $pet->id,
            'applicant_first_name' => $user->first_name, 'applicant_last_name' => $user->last_name,
            'applicant_email' => $user->email, 'applicant_phone' => '09175550142',
            'applicant_region' => $user->region, 'applicant_province' => $user->province,
            'applicant_city_municipality' => $user->city_municipality, 'applicant_barangay' => $user->barangay,
            'applicant_street_address' => $user->street_address, 'applicant_zip_code' => $user->zip_code,
            'status' => ApplicationStatus::Pending->value,
            'motivation_statement' => 'Fictional defense application for a permanent, safe home.',
            'housing_type' => $profile->housing_type, 'income_range' => $profile->monthly_income_range,
            'physical_activity_level' => $profile->physical_activity_level,
            'time_availability' => $profile->time_availability,
            'prior_pet_experience' => $profile->prior_pet_experience,
            'household_composition' => $profile->household_composition,
            'document_disk' => 'local', 'document_path' => $documentPath,
            'document_original_name' => 'fictional-supporting-document.pdf',
            'document_mime_type' => 'application/pdf', 'document_type' => 'proof_of_address',
            'document_verification_status' => DocumentVerificationStatus::LegacyReview->value,
            'document_uploaded_at' => $submitted->utc(),
        ]);
        $application->forceFill(['created_at' => $submitted->utc(), 'updated_at' => $submitted->utc()])->saveQuietly();
        app(ApplicationMatchService::class)->refresh($application);

        return $application->fresh();
    }

    private function approvedSnapshot(AdoptionApplication $application, CarbonImmutable $date, User $interviewer): void
    {
        $application->update([
            'status' => ApplicationStatus::Approved->value,
            'interview_date' => $date->subDay()->setTime(10, 0)->utc(),
            'conducted_by' => $interviewer->full_name,
            'interview_notes' => 'Fictional historical interview completed; household suitability reviewed.',
            'decision_remarks' => 'Fictional historical adoption approved for demonstration.',
            'adopted_at' => $date->setTime(15, 0)->utc(),
            'queue_closed_at' => $date->setTime(15, 0)->utc(),
        ]);
        $application->pet->update(['availability_status' => AvailabilityStatus::Adopted->value]);
        $handover = app(HandoverService::class)->forApprovedApplication($application);
        $handover->update([
            'release_method' => 'pickup', 'release_date' => $date->toDateString(),
            'release_time' => '15:00', 'staff_name' => $interviewer->full_name,
            'released_at' => $date->setTime(15, 0)->utc(),
            'adopter_outcome' => 'received', 'adopter_confirmed_at' => $date->setTime(16, 0)->utc(),
        ]);
        app(PostAdoptionScheduleService::class)->ensureForApplication($application);
        DB::table('handovers')->where('id', $handover->id)->update(['created_at' => $date->setTime(15, 0)->utc()]);
        DB::table('post_adoption_logs')->where('application_id', $application->id)->update([
            'created_at' => $date->setTime(15, 0)->utc(),
        ]);
    }

    private function checkinSnapshot(AdoptionApplication $application, string $milestone, bool $flagged): void
    {
        $log = $application->postAdoptionLogs()->where('milestone', $milestone)->firstOrFail();
        $submitted = CarbonImmutable::parse($log->scheduled_date->toDateString(), 'Asia/Manila');
        $application->postAdoptionLogs()->where('milestone', $milestone)->update([
            'submitted_date' => $submitted->setTime(11, 0)->utc(),
            'survey_data' => json_encode(['behavioral_issues' => $flagged ? 'Poor living conditions reported' : 'None observed', 'diet' => 'Regular meals', 'living_conditions' => $flagged ? 'Needs staff review' : 'Safe indoor space']),
            'pet_current_status' => $flagged ? 'Poor' : 'Good',
            'behavioral_observations' => $flagged ? 'Welfare concern requires follow-up.' : 'Settling in well.',
            'living_conditions' => $flagged ? 'Poor living conditions reported.' : 'Safe indoor home.',
            'eating_habits' => 'Regular meals',
            'is_flagged' => $flagged,
            'flag_reasons' => json_encode($flagged ? [['code' => 'welfare_concern', 'message' => 'Poor living conditions reported.']] : []),
        ]);
    }

    private function demoPdf(): string
    {
        $stream = "BT /F1 18 Tf 50 760 Td (FICTIONAL DEFENSE DOCUMENT - NOT A REAL ID) Tj ET\nBT /F1 12 Tf 50 730 Td (PAIRfect Paws local-only demonstration data.) Tj ET";
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
