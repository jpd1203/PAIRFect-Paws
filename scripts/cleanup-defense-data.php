<?php

/** Conservative, repeatable repair of confirmed local-only demo inconsistencies. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

if (! app()->environment('local') || config('database.default') !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'pairfect_paws') {
    throw new RuntimeException('Cleanup is restricted to the inspected local pairfect_paws database.');
}

$expectedPets = [38 => 'Lily G', 39 => 'Lucy Z', 40 => 'Lily D'];
$result = DB::transaction(function () use ($expectedPets): array {
    $changed = ['users_removed' => 0, 'pets_repaired' => 0];

    $sample = DB::table('users')->where('id', 3)->lockForUpdate()->first();
    if ($sample !== null) {
        if ($sample->email !== 'adopter@example.com' || $sample->first_name !== 'Jane' || $sample->last_name !== 'Doe') {
            throw new RuntimeException('User #3 is no longer the expected unused sample account.');
        }
        foreach (['adopter_profiles', 'adoption_applications', 'assessment_records' => 'assessor_id',
            'handovers', 'handover_notifications', 'post_adoption_capture_challenges',
            'audit_logs', 'sessions'] as $table => $column) {
            if (is_int($table)) {
                $table = $column;
                $column = 'user_id';
            }
            if (DB::table($table)->where($column, 3)->exists()) {
                throw new RuntimeException("The sample account has a dependent {$table} record; no cleanup was applied.");
            }
        }
        if (DB::table('password_reset_tokens')->where('email', $sample->email)->exists()) {
            throw new RuntimeException('The sample account has a password-reset token; no cleanup was applied.');
        }
        DB::table('users')->where('id', 3)->delete();
        AuditLogService::log(null, 'Unused Demo Account Removed', 'User', 3,
            'Removed the unverified, unlinked default Jane Doe sample account after dependency checks.');
        $changed['users_removed']++;
    }

    foreach ($expectedPets as $id => $name) {
        $pet = DB::table('pets')->where('id', $id)->lockForUpdate()->first();
        if ($pet === null) {
            throw new RuntimeException("Expected legacy pet #{$id} is missing; no cleanup was applied.");
        }
        if ($pet->name !== $name || $pet->branch_id !== null
            || DB::table('adoption_applications')->where('pet_id', $id)->exists()
            || DB::table('handovers')->where('pet_id', $id)->exists()) {
            throw new RuntimeException("Pet #{$id} now has a different identity or transaction; no cleanup was applied.");
        }
        $expectedStatus = $id === 40 ? 'Adopted' : 'Soft-Reserved';
        if ($pet->availability_status === 'Assessing' && (int) $pet->assessment_count === 0) {
            continue;
        }
        if ($pet->availability_status !== $expectedStatus) {
            throw new RuntimeException("Pet #{$id} changed status since audit; no cleanup was applied.");
        }
        $assessments = DB::table('assessment_records')->where('pet_id', $id)->get();
        if ($assessments->count() !== 3
            || $assessments->contains(fn ($record) => $record->assessor_id !== 1 || $record->responses !== null)) {
            throw new RuntimeException("Pet #{$id} gained a valid or changed assessment; no cleanup was applied.");
        }

        DB::table('pets')->where('id', $id)->update([
            'availability_status' => 'Assessing',
            'assessment_count' => 0,
            'description' => $pet->description ?: 'Behavioral assessment and veterinary profile are pending completion.',
            'updated_at' => now(),
        ]);
        AuditLogService::log(null, 'Legacy Demo Pet Status Reconciled', 'Pet', $id,
            "{$name}: unsupported {$expectedStatus} status changed to Assessing; legacy assessment rows and media preserved.");
        $changed['pets_repaired']++;
    }

    return $changed;
});

echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
