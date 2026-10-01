<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class VolunteerRbacTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_volunteer_can_review_applications_and_private_documents_without_final_decision_controls(): void
    {
        Storage::fake('local');
        $volunteer = $this->matchingUser(Role::Volunteer);
        $adopter = $this->matchingUser();
        $pet = Pet::create(['name' => 'Review Pet', 'species' => 'Dog', 'availability_status' => 'Available']);
        Storage::disk('local')->put('adoption-documents/review.jpg', 'private document bytes');
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Pending->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
            'document_disk' => 'local',
            'document_path' => 'adoption-documents/review.jpg',
        ]);

        $this->actingAs($volunteer)
            ->get(route('admin.applications.index'))
            ->assertOk()
            ->assertSee('Schedule Interview')
            ->assertDontSee('id="rApproveBtn"', false)
            ->assertDontSee('id="rRejectBtn"', false)
            ->assertDontSee('id="rOverrideBtn"', false)
            ->assertDontSee('id="makeDecisionModal"', false)
            ->assertDontSee('>Volunteers</a>', false);
        $this->get(route('admin.applications.document-verification', $application))->assertOk();
        $this->get(route('admin.applications.document', $application))->assertOk();
    }

    public function test_volunteer_can_schedule_eligible_interview_and_soft_reserve_pet(): void
    {
        Mail::fake();
        $volunteer = $this->matchingUser(Role::Volunteer);
        $adopter = $this->matchingUser();
        $this->completeMatchingProfile($adopter);
        $pet = Pet::create(['name' => 'Schedule Pet', 'species' => 'Dog', 'availability_status' => 'Available']);
        $this->completePetAssessment($pet);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Pending->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);
        $scheduledAt = \App\Support\ManilaTime::now()->addDay();

        $this->actingAs($volunteer)->post(route('admin.applications.schedule'), [
            'application_id' => $application->id,
            'staff_id' => $volunteer->id,
            'interview_date' => $scheduledAt->format('Y-m-d'),
            'interview_time' => $scheduledAt->format('H:i'),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(ApplicationStatus::InterviewScheduled, $application->refresh()->status);
        $this->assertTrue($application->is_primary_candidate);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Interview Scheduled — Pet Soft-Reserved']);
    }

    public function test_volunteer_cannot_make_final_decisions_override_ranking_or_manage_staff(): void
    {
        $volunteer = $this->matchingUser(Role::Volunteer);
        $managedVolunteer = $this->matchingUser(Role::Volunteer);
        $adopter = $this->matchingUser();
        $pet = Pet::create(['name' => 'Decision Pet', 'species' => 'Dog', 'availability_status' => 'Soft-Reserved']);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::UnderReview->value,
            'is_primary_candidate' => true,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);

        $this->actingAs($volunteer);
        foreach (['Approved', 'Rejected'] as $decision) {
            $this->post(route('admin.applications.decide', $application), ['decision' => $decision])
                ->assertRedirect(route('access-denied'));
        }
        $this->post(route('admin.applications.override', $application), [
            'override_reason' => 'Attempted unauthorized override.',
        ])->assertRedirect(route('access-denied'));
        $this->get(route('admin.volunteers.index'))->assertRedirect(route('access-denied'));
        $this->get(route('admin.volunteers.create'))->assertRedirect(route('access-denied'));
        $this->post(route('admin.volunteers.store'), [
            'first_name' => 'Unauthorized', 'last_name' => 'Account',
            'email' => 'unauthorized@example.test', 'role' => Role::Administrator->value,
        ])->assertRedirect(route('access-denied'));
        $this->patch(route('admin.volunteers.update', $managedVolunteer), [
            'role' => Role::Administrator->value, 'is_active' => false,
        ])->assertRedirect(route('access-denied'));
        $this->delete('/admin/volunteers/'.$managedVolunteer->id)->assertStatus(405);

        $this->assertSame(ApplicationStatus::UnderReview, $application->refresh()->status);
        $this->assertTrue($application->is_primary_candidate);
        $this->assertSame(Role::Volunteer, $managedVolunteer->refresh()->role);
        $this->assertTrue($managedVolunteer->is_active);
    }

    public function test_administrator_retains_final_approval_and_rejection(): void
    {
        Mail::fake();
        $admin = $this->matchingUser(Role::Administrator);
        $this->actingAs($admin);

        foreach (['Approved', 'Rejected'] as $decision) {
            $pet = Pet::create([
                'name' => 'Decision '.$decision,
                'species' => 'Dog',
                'availability_status' => AvailabilityStatus::SoftReserved->value,
            ]);
            $application = AdoptionApplication::create([
                'user_id' => $this->matchingUser()->id,
                'pet_id' => $pet->id,
                'status' => ApplicationStatus::UnderReview->value,
                'is_primary_candidate' => true,
                'document_verification_status' => DocumentVerificationStatus::Verified->value,
            ]);

            $this->post(route('admin.applications.decide', $application), [
                'decision' => $decision,
                'decision_remarks' => 'Administrator reviewed the case.',
            ])->assertRedirect()->assertSessionHas('success');

            $this->assertSame(ApplicationStatus::from($decision), $application->refresh()->status);
            $this->assertSame(
                $decision === 'Approved' ? AvailabilityStatus::Adopted : AvailabilityStatus::Available,
                $pet->refresh()->availability_status,
            );
        }
    }

    public function test_volunteer_can_review_and_flag_welfare_but_only_admin_can_resolve(): void
    {
        Mail::fake();
        $volunteer = $this->matchingUser(Role::Volunteer);
        $admin = $this->matchingUser(Role::Administrator);
        $adopter = $this->matchingUser();
        $pet = Pet::create(['name' => 'Welfare Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved->value,
        ]);
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->actingAs($volunteer)
            ->get(route('admin.monitoring.index'))
            ->assertOk();
        $this->post(route('admin.monitoring.flag', $log), [
            'reason' => 'A welfare concern needs administrator review.',
        ])->assertSessionHas('toast.type', 'success');
        $this->get(route('admin.monitoring.flagged'))
            ->assertOk()
            ->assertDontSee('Mark Resolved');
        $this->post(route('admin.monitoring.resolve', $log), [
            'resolution_note' => 'Unauthorized final intervention.',
        ])->assertRedirect(route('access-denied'));
        $this->assertNull($log->refresh()->resolved_at);

        $this->actingAs($admin)
            ->get(route('admin.monitoring.flagged'))
            ->assertOk()
            ->assertSee('Mark Resolved');
        $this->post(route('admin.monitoring.resolve', $log), [
            'resolution_note' => 'Reviewed the case and recorded the final intervention.',
        ])->assertSessionHas('toast.type', 'success');
        $this->assertNotNull($log->refresh()->resolved_at);
    }

    public function test_volunteer_can_create_update_and_assess_pets_but_not_archive_them(): void
    {
        $volunteer = $this->matchingUser(Role::Volunteer);
        $this->actingAs($volunteer)->post(route('admin.animals.store'), [
            'name' => 'Volunteer Pet', 'species' => 'Dog', 'status' => 'Assessing',
        ])->assertRedirect(route('admin.animals.index'));
        $pet = Pet::where('name', 'Volunteer Pet')->sole();

        $this->put(route('admin.animals.update', $pet), [
            'name' => 'Volunteer Pet Updated', 'species' => 'Dog',
            'status' => 'Assessing', 'version' => $pet->version,
        ])->assertRedirect(route('admin.animals.index'));
        $this->post(route('admin.assessments.store', $pet), [
            'responses' => $this->behaviorResponses('dog', 2),
        ])->assertRedirect(route('admin.assessments.record'));
        $this->assertSame('Volunteer Pet Updated', $pet->refresh()->name);
        $this->assertSame(1, $pet->assessmentRecords()->count());
        $this->post(route('admin.animals.archive', $pet))
            ->assertForbidden();
        $this->assertFalse($pet->refresh()->is_archived);
    }
}
