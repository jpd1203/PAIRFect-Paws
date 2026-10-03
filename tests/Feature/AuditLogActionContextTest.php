<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogActionContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_view_or_export_audit_logs(): void
    {
        $admin = $this->user('Audit', 'Admin', 'audit-admin@example.test', Role::Administrator);
        $volunteer = $this->user('Audit', 'Volunteer', 'audit-volunteer@example.test', Role::Volunteer);

        $this->actingAs($volunteer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Audit Logs');

        foreach (['admin.audit-logs.index', 'admin.audit-logs.export'] as $route) {
            $this->actingAs($volunteer)->get(route($route))->assertRedirect(route('access-denied'));
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_audit_actions_identify_their_pet_adopter_and_monitoring_milestone(): void
    {
        $admin = $this->user('Audit', 'Admin', 'audit-admin@example.test', Role::Administrator);
        $adopter = $this->user('Josh', 'Oliver', 'audit-adopter@example.test', Role::Adopter);
        $pet = Pet::create([
            'name' => 'Oreo',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Adopted->value,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved->value,
        ]);
        $checkIn = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays->value,
            'scheduled_date' => '2026-08-28',
        ]);

        $assessmentLog = AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'Pet Assessed',
            'entity_name' => 'Pet',
            'entity_id' => $pet->id,
            'notes' => 'Behavioral assessment completed.',
        ]);
        DB::table('audit_logs')->where('id', $assessmentLog->id)->update([
            'created_at' => '2026-08-24 23:30:00',
            'updated_at' => '2026-08-24 23:30:00',
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'Interview Scheduled',
            'entity_name' => 'AdoptionApplication',
            'entity_id' => $application->id,
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'Post-Adoption Reminder Sent',
            'entity_name' => 'PostAdoptionLog',
            'entity_id' => $checkIn->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Pet Assessed - Oreo')
            ->assertSee('Interview Scheduled - Oreo / Josh Oliver')
            ->assertSee('Post-Adoption Reminder Sent - Oreo / Josh Oliver - 3-Day')
            ->assertSee('Behavioral assessment completed.')
            ->assertSee('Aug 25, 2026 7:30 AM');
    }

    private function user(
        string $firstName,
        string $lastName,
        string $email,
        Role $role,
    ): User {
        return User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => 'password123',
            'role' => $role->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
