<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\FundRecord;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibleTableOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_displays_are_newest_first_while_queue_positions_remain_first_come_first_served(): void
    {
        $admin = $this->user(Role::Administrator, 'application-admin');
        $adopter = $this->user(Role::Adopter, 'application-adopter');
        $pet = $this->pet('Application Pet');

        $oldest = $this->application($adopter, $pet, '2026-08-01 09:00:00');
        $newer = $this->application($adopter, $pet, '2026-08-02 09:00:00');
        $newest = $this->application($adopter, $pet, '2026-08-02 09:00:00');
        $expected = [$newest->id, $newer->id, $oldest->id];

        $adopterApplications = $this->actingAs($adopter)
            ->get(route('application.index'))
            ->assertOk()
            ->viewData('applications');

        $this->assertSame($expected, $adopterApplications->pluck('id')->all());

        $adminApplications = $this->actingAs($admin)
            ->get(route('admin.applications.index'))
            ->assertOk()
            ->viewData('applications');

        $this->assertSame($expected, $adminApplications->pluck('id')->all());
        $this->assertSame(1, $adminApplications->firstWhere('id', $oldest->id)->queue_position);
        $this->assertSame(2, $adminApplications->firstWhere('id', $newer->id)->queue_position);
        $this->assertSame(3, $adminApplications->firstWhere('id', $newest->id)->queue_position);

        $dashboardApplications = $this->get(route('admin.dashboard'))
            ->assertOk()
            ->viewData('recentApplications');
        $this->assertSame($expected, $dashboardApplications->pluck('id')->all());

        $profileAdopters = $this->get(route('admin.adopter-profiles.index'))
            ->assertOk()
            ->viewData('adopters')
            ->getCollection();
        $this->assertSame([$adopter->id], $profileAdopters->pluck('id')->all());
        $this->assertSame(
            $expected,
            $profileAdopters->first()->adoptionApplications->pluck('id')->all(),
        );
    }

    public function test_staff_table_is_newest_first_with_deterministic_same_second_ordering(): void
    {
        $admin = $this->timestamp(
            $this->user(Role::Administrator, 'staff-admin'),
            '2026-08-01 09:00:00',
        );
        $older = $this->timestamp(
            $this->user(Role::Volunteer, 'staff-older'),
            '2026-08-02 09:00:00',
        );
        $newer = $this->timestamp(
            $this->user(Role::Volunteer, 'staff-newer'),
            '2026-08-02 09:00:00',
        );

        $volunteers = $this->actingAs($admin)
            ->get(route('admin.volunteers.index'))
            ->assertOk()
            ->viewData('volunteers');

        $this->assertSame(
            [$newer->id, $older->id, $admin->id],
            $volunteers->pluck('id')->all(),
        );
    }

    public function test_animal_and_assessment_tables_use_their_most_recent_dates(): void
    {
        $admin = $this->user(Role::Administrator, 'animal-admin');
        $oldAssessment = $this->timestamp($this->pet('Old Assessment'), '2026-07-01 09:00:00');
        $oldAssessment->update(['last_assessed_at' => '2026-08-01 09:00:00']);

        $newAssessment = $this->timestamp($this->pet('New Assessment'), '2026-07-02 09:00:00');
        $newAssessment->update(['last_assessed_at' => '2026-08-02 09:00:00']);

        $newestAssessment = $this->timestamp($this->pet('Newest Assessment'), '2026-07-02 09:00:00');
        $newestAssessment->update(['last_assessed_at' => '2026-08-02 09:00:00']);

        $newAnimal = $this->timestamp($this->pet('New Unassessed Animal'), '2026-08-03 09:00:00');

        $animals = $this->actingAs($admin)
            ->get(route('admin.animals.index'))
            ->assertOk()
            ->viewData('pets');
        $this->assertSame(
            [$newAnimal->id, $newestAssessment->id, $newAssessment->id, $oldAssessment->id],
            $animals->pluck('id')->all(),
        );

        $assessmentPets = $this->get(route('admin.assessments.record'))
            ->assertOk()
            ->viewData('pets');
        $this->assertSame(
            [$newestAssessment->id, $newAssessment->id, $oldAssessment->id, $newAnimal->id],
            $assessmentPets->pluck('id')->all(),
        );
    }

    public function test_fund_and_audit_tables_are_newest_first_including_same_second_records(): void
    {
        $admin = $this->user(Role::Administrator, 'records-admin');

        $oldFund = $this->fund('Old Fund', '2026-08-01 09:00:00');
        $newerFund = $this->fund('Newer Fund', '2026-08-02 09:00:00');
        $newestFund = $this->fund('Newest Fund', '2026-08-02 09:00:00');
        $expectedFunds = [$newestFund->id, $newerFund->id, $oldFund->id];

        $publicFunds = $this->get(route('community-impact'))
            ->assertOk()
            ->viewData('donations');
        $this->assertSame($expectedFunds, $publicFunds->pluck('id')->all());

        $adminFunds = $this->actingAs($admin)
            ->get(route('admin.funds.index'))
            ->assertOk()
            ->viewData('records');
        $this->assertSame($expectedFunds, $adminFunds->pluck('id')->all());

        $oldLog = $this->auditLog($admin, 'Old Audit', '2026-08-01 09:00:00');
        $newerLog = $this->auditLog($admin, 'Newer Audit', '2026-08-02 09:00:00');
        $newestLog = $this->auditLog($admin, 'Newest Audit', '2026-08-02 09:00:00');
        $expectedLogs = [$newestLog->id, $newerLog->id, $oldLog->id];

        $auditLogs = $this->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->viewData('logs')
            ->getCollection();
        $this->assertSame($expectedLogs, $auditLogs->pluck('id')->all());

        $dashboardActivity = $this->get(route('admin.dashboard'))
            ->assertOk()
            ->viewData('recentActivity');
        $this->assertSame($expectedLogs, $dashboardActivity->pluck('id')->all());
    }

    public function test_adopter_check_ins_follow_milestone_chronology_while_staff_keeps_open_flags_prioritized(): void
    {
        $admin = $this->user(Role::Administrator, 'monitoring-admin');
        $adopter = $this->user(Role::Adopter, 'monitoring-adopter');
        $pet = $this->pet('Monitoring Pet');
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved->value,
        ]);

        $flaggedOldest = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-01',
            'is_flagged' => true,
        ]);
        $newer = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeWeeks,
            'scheduled_date' => '2026-08-20',
        ]);
        $newest = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeMonths,
            'scheduled_date' => '2026-08-20',
        ]);

        $adopterLogs = $this->actingAs($adopter)
            ->get(route('monitoring.my-checkins'))
            ->assertOk()
            ->viewData('logs');
        $this->assertSame(
            [$flaggedOldest->id, $newer->id, $newest->id],
            $adopterLogs->pluck('id')->all(),
        );

        $staffLogs = $this->actingAs($admin)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->viewData('checkIns');
        $this->assertSame(
            [$flaggedOldest->id, $newest->id, $newer->id],
            $staffLogs->pluck('id')->all(),
        );
    }

    private function user(Role $role, string $key): User
    {
        return User::create([
            'first_name' => ucfirst(str_replace('-', ' ', $key)),
            'last_name' => 'Tester',
            'email' => $key.'@example.test',
            'password' => 'password123',
            'role' => $role->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function pet(string $name): Pet
    {
        return Pet::create([
            'name' => $name,
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);
    }

    private function application(User $adopter, Pet $pet, string $createdAt): AdoptionApplication
    {
        return $this->timestamp(AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::UnderReview->value,
        ]), $createdAt);
    }

    private function fund(string $activity, string $createdAt): FundRecord
    {
        return $this->timestamp(FundRecord::create([
            'amount' => 100,
            'transaction_type' => 'Donation',
            'source_or_destination' => $activity,
            'is_public' => true,
        ]), $createdAt);
    }

    private function auditLog(User $user, string $action, string $createdAt): AuditLog
    {
        return $this->timestamp(AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
        ]), $createdAt);
    }

    private function timestamp(Model $model, string $timestamp): Model
    {
        $model->timestamps = false;
        $model->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
        $model->timestamps = true;

        return $model->refresh();
    }
}
