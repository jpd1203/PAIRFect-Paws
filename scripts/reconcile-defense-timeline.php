<?php

/** One-time local repair for defense records seeded before timeline normalization. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\Milestone;
use App\Services\AuditLogService;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

if (! app()->environment('local') || config('database.default') !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'pairfect_paws') {
    throw new RuntimeException('Timeline repair is restricted to the inspected local pairfect_paws database.');
}

$result = DB::transaction(function (): array {
    $branch = DB::table('branches')->where('name', Database\Seeders\DefenseDemoSeeder::BRANCH)->lockForUpdate()->first();
    if ($branch === null) {
        throw new RuntimeException('The named fictional defense branch does not exist.');
    }
    $users = DB::table('users')->where('branch_id', $branch->id)->get();
    $pets = DB::table('pets')->where('branch_id', $branch->id)->get();
    $applications = DB::table('adoption_applications')->whereIn('pet_id', $pets->pluck('id'))->get();
    if ($users->count() !== 9 || $pets->count() !== 8 || $applications->count() !== 9
        || DB::table('assessment_records')->whereIn('pet_id', $pets->pluck('id'))->count() !== 23) {
        throw new RuntimeException('Defense dataset has changed since the audit; no timeline repair was applied.');
    }
    $luna = $pets->firstWhere('name', 'Luna');
    $nala = $pets->firstWhere('name', 'Nala');
    $lunaApplication = $applications->firstWhere('pet_id', $luna?->id);
    $nalaApplication = $applications->firstWhere('pet_id', $nala?->id);
    if (! $luna || ! $nala || ! $lunaApplication || ! $nalaApplication
        || $lunaApplication->status !== 'Approved' || $nalaApplication->status !== 'Approved') {
        throw new RuntimeException('Expected approved defense adoptions are unavailable.');
    }
    $lunaThreeMonth = DB::table('post_adoption_logs')->where('application_id', $lunaApplication->id)
        ->where('milestone', Milestone::ThreeMonths->value)->first();
    if (! $lunaThreeMonth) {
        throw new RuntimeException('Luna is missing the 3-month milestone.');
    }
    if (strtotime($users->firstWhere('email', 'angela.reyes@defense.pairfectpaws.test')->created_at) <= strtotime($lunaApplication->created_at)
        && $lunaThreeMonth->submitted_date !== null
        && $nala->intake_date <= substr($nalaApplication->adopted_at, 0, 10)) {
        return ['normalized' => false];
    }

    $now = CarbonImmutable::now('Asia/Manila')->startOfDay();
    DB::table('branches')->where('id', $branch->id)->update(['created_at' => $now->subDays(200)->utc()]);
    DB::table('users')->where('branch_id', $branch->id)->update(['created_at' => $now->subDays(200)->utc()]);
    DB::table('adopter_profiles')->whereIn('user_id', $users->pluck('id'))->update([
        'created_at' => $now->subDays(180)->utc(), 'bfi_completed_at' => $now->subDays(180)->utc(),
    ]);
    foreach ($pets as $pet) {
        $intake = $now->subDays(in_array($pet->name, ['Luna', 'Nala'], true) ? 150 : 40);
        $assessedAt = $intake->addDay()->setTime(11, 0)->utc();
        DB::table('pets')->where('id', $pet->id)->update([
            'intake_date' => $intake->toDateString(),
            'created_at' => $intake->setTime(9, 0)->utc(),
            'last_assessed_at' => $assessedAt,
        ]);
        DB::table('assessment_records')->where('pet_id', $pet->id)->update([
            'created_at' => $assessedAt, 'updated_at' => $assessedAt,
        ]);
    }

    $adoptedAt = $now->subDays(110)->setTime(15, 0)->utc();
    DB::table('adoption_applications')->where('id', $lunaApplication->id)->update([
        'created_at' => $now->subDays(113)->utc(),
        'document_uploaded_at' => $now->subDays(113)->utc(),
        'interview_date' => $now->subDays(111)->setTime(10, 0)->utc(),
        'adopted_at' => $adoptedAt, 'queue_closed_at' => $adoptedAt,
    ]);

    foreach ([$lunaApplication->id, $nalaApplication->id] as $applicationId) {
        $application = App\Models\AdoptionApplication::findOrFail($applicationId);
        $adoptionDate = app(PostAdoptionScheduleService::class)->adoptionDate($application);
        DB::table('handovers')->where('application_id', $applicationId)->update([
            'created_at' => $application->adopted_at,
            ...($applicationId === $lunaApplication->id ? [
                'approved_at' => $application->adopted_at,
                'release_date' => $adoptionDate->toDateString(),
                'released_at' => $application->adopted_at,
                'adopter_confirmed_at' => $adoptionDate->setTime(16, 0)->utc(),
                'history' => json_encode([[
                    'at' => $application->adopted_at->toIso8601String(),
                    'label' => 'Application approved', 'actor' => 'System',
                ]]),
            ] : []),
        ]);
        foreach (Milestone::cases() as $milestone) {
            $due = app(PostAdoptionScheduleService::class)->targetDate($adoptionDate, $milestone);
            $fields = [
                'scheduled_date' => $due->toDateString(),
                'created_at' => $application->adopted_at,
            ];
            if ($applicationId === $lunaApplication->id) {
                $fields += [
                    'submitted_date' => $due->setTime(11, 0)->utc(),
                    'survey_data' => json_encode([
                        'behavioral_issues' => 'None observed', 'diet' => 'Regular meals',
                        'living_conditions' => 'Safe indoor space',
                    ]),
                    'pet_current_status' => 'Good',
                    'behavioral_observations' => 'Settling in well.',
                    'living_conditions' => 'Safe indoor home.',
                    'eating_habits' => 'Regular meals',
                    'is_flagged' => false,
                    'flag_reasons' => json_encode([]),
                ];
            }
            DB::table('post_adoption_logs')->where('application_id', $applicationId)
                ->where('milestone', $milestone->value)->update($fields);
        }
    }

    AuditLogService::log(null, 'Fictional Defense Timeline Reconciled', 'Branch', $branch->id,
        'Normalized fictional account, intake, assessment, adoption, handover, and monitoring dates; no personal-account records changed.');

    return ['normalized' => true];
});

echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
