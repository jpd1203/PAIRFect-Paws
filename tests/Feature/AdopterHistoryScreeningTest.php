<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Services\AdopterHistoryService;
use App\Services\Matching\ApplicantRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class AdopterHistoryScreeningTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_prior_rejection_is_factual_history_not_a_welfare_warning(): void
    {
        $adopter = $this->user(Role::Adopter);
        $past = $this->application($adopter, 'Earlier Pet', ApplicationStatus::Rejected, -20);
        $current = $this->application($adopter, 'Current Pet', ApplicationStatus::Pending, -1);

        $summary = app(AdopterHistoryService::class)->summariesForApplications(collect([$current]))[$current->id];
        $this->assertSame('no_recorded_concerns', $summary['review_status']);
        $this->assertSame(1, $summary['previous_applications']);
        $this->assertSame(1, $summary['rejected_applications']);
        $this->assertSame(0, $summary['flagged_welfare_reports']);
        $this->assertSame(ApplicationStatus::Rejected, $past->refresh()->status);
    }

    public function test_a_prior_flagged_report_appears_for_staff_and_in_account_history(): void
    {
        $admin = $this->user(Role::Administrator);
        $adopter = $this->user(Role::Adopter);
        $past = $this->application($adopter, 'Previous Pet', ApplicationStatus::Approved, -30);
        $current = $this->application($adopter, 'New Pet', ApplicationStatus::Pending, -1);
        $log = $this->log($past, Milestone::ThreeDays, -20, true, false, false);
        $this->assertSame('no_recorded_concerns',
            app(AdopterHistoryService::class)->summariesForApplications(collect([$current]))[$current->id]['review_status']);
        $log->update([
            'is_flagged' => true,
            'flag_reasons' => [['code' => 'survey_welfare_concern', 'message' => 'Documented survey concern']],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.applications.index'))->assertOk()
            ->assertSee('Needs Review')
            ->assertSee('Applicant History')
            ->assertSee('View Full History');
        $summary = $response->viewData('historySummaries')[$current->id];
        $this->assertSame(1, $summary['flagged_welfare_reports']);
        $this->assertSame(1, $summary['approved_placements']);
        $this->assertSame('needs_review', $summary['review_status']);

        $this->get(route('admin.adopter-profiles.history', $adopter))
            ->assertOk()
            ->assertSee('Account-wide Compliance History')
            ->assertSee('Chronological account history')
            ->assertSee('Previous Pet')
            ->assertSee('New Pet')
            ->assertSee('Documented survey concern');
    }

    public function test_repeated_flags_escalate_review_without_changing_knn_rank_or_application_status(): void
    {
        $adopter = $this->user(Role::Adopter);
        $otherAdopter = $this->user(Role::Adopter);
        $past = $this->application($adopter, 'Adopted Pet', ApplicationStatus::Approved, -40);
        $sharedPet = Pet::create(['name' => 'Ranked Pet', 'species' => 'Dog', 'availability_status' => 'Available']);
        $this->completeMatchingProfile($adopter);
        $this->completeMatchingProfile($otherAdopter);
        $this->completePetAssessment($sharedPet);
        $current = $this->application($adopter, 'Ranked Pet', ApplicationStatus::Pending, -1, $sharedPet);
        $other = $this->application($otherAdopter, 'Ranked Pet', ApplicationStatus::Pending, -1, $sharedPet);
        $ranking = app(ApplicantRankingService::class);
        $before = $ranking->rankApplicants($sharedPet)->pluck('id')->all();
        $beforeScore = $current->refresh()->knn_score;
        $this->assertNotNull($beforeScore);

        $this->log($past, Milestone::ThreeDays, -30, true, true, false);
        $this->log($past, Milestone::ThreeWeeks, -20, true, true, true);
        $summary = app(AdopterHistoryService::class)->summariesForApplications(collect([$current]))[$current->id];

        $this->assertSame('administrative_review_required', $summary['review_status']);
        $this->assertSame(2, $summary['flagged_welfare_reports']);
        $this->assertSame(1, $summary['unresolved_monitoring_flags']);
        $this->assertSame($beforeScore, $current->refresh()->knn_score);
        $this->assertSame(ApplicationStatus::Pending, $current->status);
        $this->assertSame($before, $ranking->rankApplicants($sharedPet)->pluck('id')->all());
        $this->assertSame($beforeScore, $current->refresh()->knn_score);
        config()->set('adopter_history.administrative_review_flagged_reports', 3);
        $this->assertSame('needs_review',
            app(AdopterHistoryService::class)->summariesForApplications(collect([$current]))[$current->id]['review_status']);
    }

    public function test_missed_and_late_checkins_are_derived_from_existing_due_and_submission_dates(): void
    {
        $adopter = $this->user(Role::Adopter);
        $past = $this->application($adopter, 'Monitored Pet', ApplicationStatus::Approved, -40);
        $current = $this->application($adopter, 'Future Pet', ApplicationStatus::Pending, -1);
        $this->log($past, Milestone::ThreeDays, -30, false, false, false);
        $this->log($past, Milestone::ThreeWeeks, -20, true, false, false, submittedDaysAfterDue: 3);

        $summary = app(AdopterHistoryService::class)->summariesForApplications(collect([$current]))[$current->id];
        $this->assertSame(1, $summary['missed_checkins']);
        $this->assertSame(1, $summary['late_checkins']);
        $this->assertSame(1, $summary['completed_checkins']);
        $this->assertSame('needs_review', $summary['review_status']);
    }

    public function test_a_missed_submission_flag_is_not_mislabeled_as_a_welfare_report(): void
    {
        $adopter = $this->user(Role::Adopter);
        $past = $this->application($adopter, 'Earlier Pet', ApplicationStatus::Approved, -30);
        $current = $this->application($adopter, 'Current Pet', ApplicationStatus::Pending, -1);
        $log = $this->log($past, Milestone::ThreeDays, -20, true, true, false, submittedDaysAfterDue: 2);
        $log->update(['flag_reasons' => [['code' => 'missed_check_in', 'message' => 'Submitted after two reminders']]]);

        $summary = app(AdopterHistoryService::class)->summariesForApplications(collect([$current]))[$current->id];
        $this->assertSame(0, $summary['flagged_welfare_reports']);
        $this->assertSame(1, $summary['late_checkins']);
        $this->assertSame(1, $summary['unresolved_monitoring_flags']);
        $this->assertSame('needs_review', $summary['review_status']);
    }

    public function test_authorized_staff_can_see_ranked_indicator_but_adopters_and_guests_cannot(): void
    {
        $admin = $this->user(Role::Administrator);
        $volunteer = $this->user(Role::Volunteer);
        $adopter = $this->user(Role::Adopter);
        $past = $this->application($adopter, 'First Pet', ApplicationStatus::Approved, -30);
        $this->application($adopter, 'Next Pet', ApplicationStatus::Pending, -1);
        $this->log($past, Milestone::ThreeDays, -20, true, true, false);

        foreach ([$admin, $volunteer] as $staff) {
            $this->actingAs($staff)->get(route('admin.compatibility.index'))
                ->assertOk()->assertSee('History:')->assertSee('Needs Review');
            $this->get(route('admin.adopter-profiles.history', $adopter))
                ->assertOk()->assertSee('Documented survey concern');
        }
        $this->actingAs($adopter)->get(route('admin.compatibility.index'))
            ->assertRedirect(route('access-denied'));
        $this->get(route('admin.adopter-profiles.history', $adopter))
            ->assertRedirect(route('access-denied'));
        auth()->logout();
        $this->get(route('admin.adopter-profiles.history', $adopter))
            ->assertRedirect(route('login'));
    }

    private function user(Role $role): User
    {
        return User::create([
            'first_name' => 'History', 'last_name' => 'Tester',
            'email' => (string) \Illuminate\Support\Str::uuid().'@example.test',
            'password' => bcrypt('password'), 'role' => $role->value,
            'email_verified_at' => now(), 'is_active' => true,
        ]);
    }

    private function application(User $user, string $petName, ApplicationStatus $status, int $daysAgo, ?Pet $pet = null): AdoptionApplication
    {
        $pet ??= Pet::create(['name' => $petName, 'species' => 'Dog', 'availability_status' => 'Available']);
        $application = AdoptionApplication::create([
            'user_id' => $user->id, 'pet_id' => $pet->id, 'status' => $status->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);
        if ($status === ApplicationStatus::Approved) {
            Handover::create([
                'code' => 'HV-TEST-'.$application->id,
                'application_id' => $application->id,
                'pet_id' => $pet->id,
                'user_id' => $user->id,
                'released_at' => now(),
                'adopter_outcome' => 'received',
                'received_at' => now(),
            ]);
        }
        $application->timestamps = false;
        $application->forceFill([
            'created_at' => now()->addDays($daysAgo),
            'updated_at' => now()->addDays($daysAgo),
        ])->saveQuietly();
        $application->timestamps = true;

        return $application->fresh();
    }

    private function log(
        AdoptionApplication $application,
        Milestone $milestone,
        int $daysAgo,
        bool $submitted,
        bool $flagged,
        bool $resolved,
        int $submittedDaysAfterDue = 0,
    ): PostAdoptionLog {
        return PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => $milestone->value,
            'scheduled_date' => now()->addDays($daysAgo)->toDateString(),
            'submitted_date' => $submitted ? now()->addDays($daysAgo + $submittedDaysAfterDue) : null,
            'is_flagged' => $flagged,
            'flag_reasons' => $flagged ? [['code' => 'survey_welfare_concern', 'message' => 'Documented survey concern']] : null,
            'resolved_at' => $resolved ? now()->addDays($daysAgo + 1) : null,
        ]);
    }

}
