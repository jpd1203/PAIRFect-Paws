<?php

/** Read-only local data inventory for defense rehearsal. Never mutates records. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('Defense data audit is restricted to local/testing environments.');
}

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$tables = [
    'pets', 'users', 'adopter_profiles', 'adoption_applications',
    'assessment_records', 'handovers', 'post_adoption_logs',
    'post_adoption_capture_challenges', 'handover_notifications', 'audit_logs',
];
$report = ['counts' => []];
foreach ($tables as $table) {
    $report['counts'][$table] = DB::table($table)->count();
}
$report['counts']['adopters'] = DB::table('users')->where('role', 'Adopter')->count();
$report['counts']['matching_snapshots'] = DB::table('adoption_applications')->whereNotNull('knn_computed_at')->count();
$report['counts']['interviews'] = DB::table('adoption_applications')->whereNotNull('interview_date')->count();
$report['counts']['submitted_checkins'] = DB::table('post_adoption_logs')->whereNotNull('submitted_date')->count();

$report['users'] = DB::table('users')->orderBy('id')->get(['id', 'first_name', 'last_name', 'email', 'role', 'is_active', 'branch_id'])->all();
$report['pets'] = DB::table('pets')->orderBy('id')->get([
    'id', 'name', 'species', 'breed', 'availability_status', 'branch_id', 'is_archived',
    'assessment_count', 'physical_size', 'medical_needs', 'life_stage',
])->all();
$report['applications'] = DB::table('adoption_applications')->orderBy('id')->get([
    'id', 'user_id', 'pet_id', 'status', 'is_primary_candidate', 'interview_date',
    'adopted_at', 'queue_closed_at', 'knn_score', 'knn_algorithm_version',
    'knn_computed_at', 'document_path', 'document_disk', 'created_at',
])->all();
$report['logs'] = DB::table('post_adoption_logs')->orderBy('id')->get([
    'id', 'application_id', 'milestone', 'scheduled_date', 'submitted_date', 'is_flagged',
    'reminders_sent', 'photo_path', 'video_path',
])->all();

$report['orphans'] = [
    'applications_missing_user' => DB::table('adoption_applications as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->whereNull('u.id')->pluck('a.id')->all(),
    'applications_missing_pet' => DB::table('adoption_applications as a')->leftJoin('pets as p', 'p.id', '=', 'a.pet_id')->whereNull('p.id')->pluck('a.id')->all(),
    'profiles_missing_user' => DB::table('adopter_profiles as ap')->leftJoin('users as u', 'u.id', '=', 'ap.user_id')->whereNull('u.id')->pluck('ap.id')->all(),
    'assessments_missing_pet' => DB::table('assessment_records as ar')->leftJoin('pets as p', 'p.id', '=', 'ar.pet_id')->whereNull('p.id')->pluck('ar.id')->all(),
    'assessments_missing_assessor' => DB::table('assessment_records as ar')->leftJoin('users as u', 'u.id', '=', 'ar.assessor_id')->whereNull('u.id')->pluck('ar.id')->all(),
    'logs_missing_application' => DB::table('post_adoption_logs as l')->leftJoin('adoption_applications as a', 'a.id', '=', 'l.application_id')->whereNull('a.id')->pluck('l.id')->all(),
    'logs_nonapproved_application' => DB::table('post_adoption_logs as l')->join('adoption_applications as a', 'a.id', '=', 'l.application_id')->where('a.status', '!=', 'Approved')->pluck('l.id')->all(),
    'handovers_missing_application' => DB::table('handovers as h')->leftJoin('adoption_applications as a', 'a.id', '=', 'h.application_id')->whereNull('a.id')->pluck('h.id')->all(),
    'handovers_missing_pet' => DB::table('handovers as h')->leftJoin('pets as p', 'p.id', '=', 'h.pet_id')->whereNull('p.id')->pluck('h.id')->all(),
    'handovers_missing_user' => DB::table('handovers as h')->leftJoin('users as u', 'u.id', '=', 'h.user_id')->whereNull('u.id')->pluck('h.id')->all(),
    'logs_missing_pet' => DB::table('post_adoption_logs as l')->join('adoption_applications as a', 'a.id', '=', 'l.application_id')->leftJoin('pets as p', 'p.id', '=', 'a.pet_id')->whereNull('p.id')->pluck('l.id')->all(),
    'logs_missing_adopter' => DB::table('post_adoption_logs as l')->join('adoption_applications as a', 'a.id', '=', 'l.application_id')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->whereNull('u.id')->pluck('l.id')->all(),
    'challenges_missing_log' => DB::table('post_adoption_capture_challenges as c')->leftJoin('post_adoption_logs as l', 'l.id', '=', 'c.post_adoption_log_id')->whereNull('l.id')->pluck('c.id')->all(),
    'notifications_missing_handover' => DB::table('handover_notifications as n')->leftJoin('handovers as h', 'h.id', '=', 'n.handover_id')->whereNull('h.id')->pluck('n.id')->all(),
];
$report['duplicates'] = [
    'user_email' => DB::table('users')->select('email')->selectRaw('COUNT(*) as n')->groupBy('email')->havingRaw('COUNT(*) > 1')->get()->all(),
    'application_user_pet' => DB::table('adoption_applications')->select('user_id', 'pet_id')->selectRaw('COUNT(*) as n')->groupBy('user_id', 'pet_id')->havingRaw('COUNT(*) > 1')->get()->all(),
    'assessment_pet_assessor' => DB::table('assessment_records')->select('pet_id', 'assessor_id')->selectRaw('COUNT(*) as n')->groupBy('pet_id', 'assessor_id')->havingRaw('COUNT(*) > 1')->get()->all(),
    'log_milestone' => DB::table('post_adoption_logs')->select('application_id', 'milestone')->selectRaw('COUNT(*) as n')->groupBy('application_id', 'milestone')->havingRaw('COUNT(*) > 1')->get()->all(),
];
$report['inconsistencies'] = [
    'adopted_without_approval' => DB::table('pets as p')->where('availability_status', 'Adopted')->whereNotExists(
        fn ($query) => $query->selectRaw('1')->from('adoption_applications as a')->whereColumn('a.pet_id', 'p.id')->where('a.status', 'Approved')
    )->pluck('p.id')->all(),
    'available_with_approval' => DB::table('pets as p')->where('availability_status', 'Available')->whereExists(
        fn ($query) => $query->selectRaw('1')->from('adoption_applications as a')->whereColumn('a.pet_id', 'p.id')->where('a.status', 'Approved')
    )->pluck('p.id')->all(),
    'soft_reserved_primary_count' => DB::table('pets as p')->where('availability_status', 'Soft-Reserved')->get(['p.id'])->mapWithKeys(
        fn ($pet) => [$pet->id => DB::table('adoption_applications')->where('pet_id', $pet->id)->where('is_primary_candidate', true)->whereIn('status', ['InterviewScheduled', 'UnderReview', 'PrimaryCandidate'])->count()]
    )->filter(fn ($count) => $count !== 1)->all(),
    'scheduled_without_date' => DB::table('adoption_applications')->where('status', 'InterviewScheduled')->whereNull('interview_date')->pluck('id')->all(),
    'approved_without_date' => DB::table('adoption_applications')->where('status', 'Approved')->whereNull('adopted_at')->pluck('id')->all(),
    'nonterminal_without_current_match' => DB::table('adoption_applications')->whereNotIn('status', ['Approved', 'Rejected', 'Withdrawn', 'NoShow', 'Closed'])->whereNull('knn_computed_at')->pluck('id')->all(),
    'interview_before_application' => DB::table('adoption_applications')->whereNotNull('interview_date')->whereColumn('interview_date', '<', 'created_at')->pluck('id')->all(),
    'adoption_before_application' => DB::table('adoption_applications')->whereNotNull('adopted_at')->whereColumn('adopted_at', '<', 'created_at')->pluck('id')->all(),
    'approved_pet_not_adopted' => DB::table('adoption_applications as a')->join('pets as p', 'p.id', '=', 'a.pet_id')->where('a.status', 'Approved')->where('p.availability_status', '!=', 'Adopted')->pluck('a.id')->all(),
];

$defenseBranch = DB::table('branches')->where('name', Database\Seeders\DefenseDemoSeeder::BRANCH)->value('id');
$report['defense'] = $defenseBranch === null ? null : [
    'users' => DB::table('users')->where('branch_id', $defenseBranch)->count(),
    'pets' => DB::table('pets')->where('branch_id', $defenseBranch)->count(),
    'applications' => DB::table('adoption_applications as a')->join('pets as p', 'p.id', '=', 'a.pet_id')->where('p.branch_id', $defenseBranch)->count(),
    'application_before_user' => DB::table('adoption_applications as a')->join('pets as p', 'p.id', '=', 'a.pet_id')->join('users as u', 'u.id', '=', 'a.user_id')->where('p.branch_id', $defenseBranch)->whereColumn('a.created_at', '<', 'u.created_at')->pluck('a.id')->all(),
    'application_before_pet' => DB::table('adoption_applications as a')->join('pets as p', 'p.id', '=', 'a.pet_id')->where('p.branch_id', $defenseBranch)->whereColumn('a.created_at', '<', 'p.created_at')->pluck('a.id')->all(),
    'adoption_before_intake' => DB::table('adoption_applications as a')->join('pets as p', 'p.id', '=', 'a.pet_id')->where('p.branch_id', $defenseBranch)->whereNotNull('a.adopted_at')->whereRaw('DATE(a.adopted_at) < p.intake_date')->pluck('a.id')->all(),
];

$report['missing_media'] = [
    'pet_photos' => DB::table('pets')->whereNotNull('photo_path')->get(['id', 'photo_path'])
        ->reject(fn ($pet) => Storage::disk('public')->exists($pet->photo_path))->pluck('id')->all(),
    'application_documents' => DB::table('adoption_applications')->whereNotNull('document_path')->get(['id', 'document_path', 'document_disk'])
        ->reject(fn ($application) => Storage::disk($application->document_disk ?: 'local')->exists($application->document_path))->pluck('id')->all(),
    'checkin_photos' => DB::table('post_adoption_logs')->whereNotNull('photo_path')->get(['id', 'photo_path'])
        ->reject(fn ($log) => Storage::disk('local')->exists($log->photo_path))->pluck('id')->all(),
    'checkin_videos' => DB::table('post_adoption_logs')->whereNotNull('video_path')->get(['id', 'video_path'])
        ->reject(fn ($log) => Storage::disk('local')->exists($log->video_path))->pluck('id')->all(),
];

if (in_array('--summary', $argv, true)) {
    unset($report['users'], $report['pets'], $report['applications'], $report['logs']);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), PHP_EOL;
