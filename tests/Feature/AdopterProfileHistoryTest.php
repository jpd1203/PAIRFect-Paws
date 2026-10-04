<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\User;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdopterProfileHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_profiles_page_groups_all_application_attempts_under_one_adopter_account(): void
    {
        $staff = $this->user(Role::Administrator, 'profile-admin');
        $repeatAdopter = $this->user(Role::Adopter, 'repeat-adopter', 'Jamie', 'Santos');
        $otherAdopter = $this->user(Role::Adopter, 'other-adopter', 'Morgan', 'Reyes');

        $oldest = $this->application(
            $repeatAdopter,
            $this->pet('First Friend'),
            ApplicationStatus::Approved,
            '2026-08-01 09:00:00',
            ['applicant_first_name' => 'Jamie', 'applicant_last_name' => 'Santos'],
        );
        $newest = $this->application(
            $repeatAdopter,
            $this->pet('Newest Hope'),
            ApplicationStatus::Waitlisted,
            '2026-08-20 09:00:00',
            ['applicant_first_name' => 'Jamie Updated', 'applicant_last_name' => 'Santos'],
        );
        $middle = $this->application(
            $repeatAdopter,
            $this->pet('Second Chance'),
            ApplicationStatus::Rejected,
            '2026-08-10 09:00:00',
        );
        $otherApplication = $this->application(
            $otherAdopter,
            $this->pet('Another Account Pet'),
            ApplicationStatus::UnderReview,
            '2026-08-15 09:00:00',
        );

        $response = $this->actingAs($staff)
            ->get(route('admin.adopter-profiles.index'))
            ->assertOk()
            ->assertSee('Jamie Santos')
            ->assertSee('Morgan Reyes')
            ->assertSee('Newest Hope')
            ->assertSee(route('admin.adopter-profiles.history', $repeatAdopter), false)
            ->assertSee(route('admin.adopter-profiles.history', $otherAdopter), false);

        $adopters = $response->viewData('adopters');
        $accounts = $adopters->getCollection();

        $this->assertCount(2, $accounts);
        $this->assertCount(2, $accounts->pluck('id')->unique());
        $this->assertEqualsCanonicalizing(
            [$repeatAdopter->id, $otherAdopter->id],
            $accounts->pluck('id')->all(),
        );

        $repeatProfile = $accounts->firstWhere('id', $repeatAdopter->id);
        $this->assertNotNull($repeatProfile);
        $this->assertSame(
            [$newest->id, $middle->id, $oldest->id],
            $repeatProfile->adoptionApplications->pluck('id')->all(),
        );

        $otherProfile = $accounts->firstWhere('id', $otherAdopter->id);
        $this->assertNotNull($otherProfile);
        $this->assertSame(
            [$otherApplication->id],
            $otherProfile->adoptionApplications->pluck('id')->all(),
        );
    }

    public function test_history_tracks_the_selected_placement_with_official_date_metrics_timeline_and_simultaneous_report_states(): void
    {
        $staff = $this->user(Role::Volunteer, 'history-volunteer');
        $adopter = $this->user(Role::Adopter, 'history-adopter', 'Alex', 'Dela Cruz');
        $application = $this->application(
            $adopter,
            $this->pet('Mimi'),
            ApplicationStatus::Approved,
            '2025-12-01 09:00:00',
            [
                'queue_closed_at' => '2026-01-10 12:00:00',
                'adopted_at' => '2026-01-15 12:00:00',
            ],
        );

        $logs = app(PostAdoptionScheduleService::class)->ensureForApplication($application);
        $logs->firstWhere('milestone', Milestone::ThreeDays)?->update([
            'submitted_date' => '2026-01-18 16:30:00',
        ]);
        $application->postAdoptionLogs()
            ->where('milestone', Milestone::ThreeMonths->value)
            ->delete();

        $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00', PostAdoptionScheduleService::TIMEZONE));

        $this->actingAs($staff)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('admin.adopter-profiles.history', $adopter))
            ->assertOk()
            ->assertSee('Adoption History')
            ->assertSee('Alex Dela Cruz')
            ->assertSee('Mimi')
            ->assertSee('Adopted on')
            ->assertSee('January 15, 2026')
            ->assertDontSee('January 10, 2026')
            ->assertSee('Monitoring')
            ->assertSee('In Progress')
            ->assertViewHas(
                'monitoringMetrics',
                fn (array $metrics): bool => $metrics['overall_status'] === 'in_progress',
            )
            ->assertSee('Reports filed')
            ->assertSee('1/3')
            ->assertSee('data-selected-application-id="'.$application->id.'"', false)
            ->assertSeeInOrder([
                '3-Day',
                'Jan 18, 2026',
                '3-Week',
                'Feb 5, 2026',
                '3-Month',
                'Apr 15, 2026',
            ])
            ->assertSee('data-milestone="3_days"', false)
            ->assertSee('data-report-status="completed"', false)
            ->assertSee('data-milestone="3_weeks"', false)
            ->assertSee('data-report-status="pending"', false)
            ->assertSee('data-milestone="3_months"', false)
            ->assertSee('data-report-status="upcoming"', false)
            ->assertSee('Completed')
            ->assertSee('Pending')
            ->assertSee('Upcoming');
    }

    public function test_overall_monitoring_status_handles_pending_completed_and_overdue_placements(): void
    {
        $staff = $this->user(Role::Administrator, 'status-admin');
        $adopter = $this->user(Role::Adopter, 'status-adopter', 'Sam', 'Rivera');

        $pending = $this->approvedPlacement($adopter, 'Pending Pal', '2026-02-10 12:00:00');
        $completed = $this->approvedPlacement($adopter, 'Completed Companion', '2025-10-01 12:00:00');
        $overdue = $this->approvedPlacement($adopter, 'Overdue Friend', '2026-01-15 12:00:00');

        $completed->postAdoptionLogs()->update(['submitted_date' => '2026-01-02 10:00:00']);
        $overdue->postAdoptionLogs()
            ->where('milestone', Milestone::ThreeDays->value)
            ->update(['submitted_date' => '2026-01-18 10:00:00']);

        $this->travelTo(CarbonImmutable::parse('2026-02-10 12:00:00', PostAdoptionScheduleService::TIMEZONE));

        $this->actingAs($staff)
            ->get($this->historyUrl($adopter, $pending))
            ->assertOk()
            ->assertSee('data-selected-application-id="'.$pending->id.'"', false)
            ->assertViewHas(
                'monitoringMetrics',
                fn (array $metrics): bool => $metrics['overall_status'] === 'pending',
            )
            ->assertSee('Pending')
            ->assertSee('0/3');

        $this->get($this->historyUrl($adopter, $completed))
            ->assertOk()
            ->assertSee('data-selected-application-id="'.$completed->id.'"', false)
            ->assertViewHas(
                'monitoringMetrics',
                fn (array $metrics): bool => $metrics['overall_status'] === 'completed',
            )
            ->assertSee('Completed')
            ->assertSee('3/3');

        $this->get($this->historyUrl($adopter, $overdue))
            ->assertOk()
            ->assertSee('data-selected-application-id="'.$overdue->id.'"', false)
            ->assertViewHas(
                'monitoringMetrics',
                fn (array $metrics): bool => $metrics['overall_status'] === 'overdue',
            )
            ->assertSee('Overdue')
            ->assertSee('data-report-status="overdue"', false)
            ->assertSee('1/3');
    }

    public function test_multiple_approved_placements_can_be_switched_without_accepting_another_accounts_or_a_nonapproved_application(): void
    {
        $staff = $this->user(Role::Volunteer, 'placement-volunteer');
        $adopter = $this->user(Role::Adopter, 'placement-adopter', 'Jamie', 'Santos');
        $otherAdopter = $this->user(Role::Adopter, 'foreign-placement-adopter', 'Taylor', 'Garcia');

        $older = $this->approvedPlacement($adopter, 'Mimi', '2025-01-15 12:00:00');
        $newer = $this->approvedPlacement($adopter, 'Luna', '2026-04-14 12:00:00');
        $foreign = $this->approvedPlacement($otherAdopter, 'Private Pet', '2026-05-01 12:00:00');
        $rejected = $this->application(
            $adopter,
            $this->pet('Rejected Pet'),
            ApplicationStatus::Rejected,
            '2026-06-01 12:00:00',
        );

        $defaultHistory = $this->actingAs($staff)
            ->get(route('admin.adopter-profiles.history', $adopter))
            ->assertOk()
            ->assertSee('data-selected-application-id="'.$newer->id.'"', false)
            ->assertSee('data-placement-application-id="'.$newer->id.'"', false)
            ->assertSee('data-placement-application-id="'.$older->id.'"', false)
            ->assertDontSee('data-placement-application-id="'.$foreign->id.'"', false)
            ->assertDontSee('Private Pet')
            ->assertDontSee('Taylor Garcia')
            ->assertSee($this->historyUrl($adopter, $older), false)
            ->assertSee($this->historyUrl($adopter, $newer), false)
            ->assertDontSee($this->historyUrl($adopter, $foreign), false);

        $this->assertStringNotContainsString('Private Pet', $defaultHistory->getContent());

        $this->get($this->historyUrl($adopter, $older))
            ->assertOk()
            ->assertSee('data-selected-application-id="'.$older->id.'"', false)
            ->assertSee('Applicant:')
            ->assertSee('Jamie Santos')
            ->assertSee('Pet:')
            ->assertSee('Mimi')
            ->assertSee('January 15, 2025');

        $this->get($this->historyUrl($adopter, $foreign))
            ->assertNotFound()
            ->assertDontSee('Private Pet');

        $this->get($this->historyUrl($adopter, $rejected))
            ->assertNotFound()
            ->assertDontSee('Rejected Pet');
    }

    public function test_history_has_a_clear_empty_state_without_an_approved_placement_and_unknown_profiles_still_404(): void
    {
        $staff = $this->user(Role::Administrator, 'empty-history-admin');
        $adopter = $this->user(Role::Adopter, 'no-placement-adopter', 'Casey', 'Lim');
        $emptyAccount = $this->user(Role::Adopter, 'empty-account-adopter', 'No', 'Applications');

        $this->application(
            $adopter,
            $this->pet('Unsuccessful Attempt'),
            ApplicationStatus::Rejected,
            '2026-07-01 12:00:00',
        );

        $this->actingAs($staff)
            ->get(route('admin.adopter-profiles.history', $adopter))
            ->assertOk()
            ->assertSee('Casey Lim')
            ->assertSee('No')
            ->assertSee('adoption placement')
            ->assertDontSee('Reports filed')
            ->assertDontSee('data-selected-application-id', false);

        $this->get(route('admin.adopter-profiles.history', $emptyAccount))
            ->assertNotFound();
    }

    public function test_submitted_flagged_reports_count_as_filed_while_remaining_visible_for_review_and_all_future_monitoring_is_scheduled(): void
    {
        $staff = $this->user(Role::Administrator, 'flagged-history-admin');
        $adopter = $this->user(Role::Adopter, 'flagged-history-adopter', 'Robin', 'Flores');
        $flaggedPlacement = $this->approvedPlacement(
            $adopter,
            'Flagged Friend',
            '2026-01-15 12:00:00',
        );
        $futurePlacement = $this->approvedPlacement(
            $adopter,
            'Future Friend',
            '2026-03-01 12:00:00',
        );

        $flaggedPlacement->postAdoptionLogs()
            ->where('milestone', Milestone::ThreeDays->value)
            ->firstOrFail()
            ->update([
                'submitted_date' => '2026-01-18 14:00:00',
                'is_flagged' => true,
                'flag_reasons' => [[
                    'code' => 'welfare_concern',
                    'message' => 'The submitted report requires staff review.',
                ]],
            ]);

        $this->travelTo(CarbonImmutable::parse('2026-02-02 12:00:00', PostAdoptionScheduleService::TIMEZONE));

        $this->actingAs($staff)
            ->get($this->historyUrl($adopter, $flaggedPlacement))
            ->assertOk()
            ->assertViewHas(
                'monitoringMetrics',
                fn (array $metrics): bool => $metrics['overall_status'] === 'needs_review'
                    && $metrics['filed_reports'] === 1
                    && $metrics['reports_fraction'] === '1/3',
            )
            ->assertSee('data-monitoring-status="needs_review"', false)
            ->assertSee('1/3')
            ->assertSeeInOrder([
                'data-milestone="3_days"',
                'data-report-status="completed"',
                'data-has-unresolved-flag="true"',
            ], false)
            ->assertSee('Completed')
            ->assertSee('Flagged');

        $this->get($this->historyUrl($adopter, $futurePlacement))
            ->assertOk()
            ->assertViewHas(
                'monitoringMetrics',
                fn (array $metrics): bool => $metrics['overall_status'] === 'scheduled'
                    && $metrics['filed_reports'] === 0
                    && $metrics['reports_fraction'] === '0/3',
            )
            ->assertSee('data-monitoring-status="scheduled"', false)
            ->assertSee('Scheduled')
            ->assertSee('0/3');
    }

    public function test_profile_listing_and_account_history_are_available_only_to_verified_active_staff(): void
    {
        $adopter = $this->user(Role::Adopter, 'security-adopter');
        $application = $this->approvedPlacement(
            $adopter,
            'Security Pet',
            '2026-08-20 09:00:00',
        );
        $historyUrl = route('admin.adopter-profiles.history', $adopter);

        $this->get(route('admin.adopter-profiles.index'))
            ->assertRedirect(route('login'));
        $this->get($historyUrl)
            ->assertRedirect(route('login'));

        $this->actingAs($adopter)
            ->get(route('admin.adopter-profiles.index'))
            ->assertRedirect(route('access-denied'));
        $this->get($historyUrl)
            ->assertRedirect(route('access-denied'));

        $unverifiedVolunteer = $this->user(
            Role::Volunteer,
            'unverified-history-volunteer',
            verified: false,
        );
        $this->actingAs($unverifiedVolunteer)
            ->get(route('admin.adopter-profiles.index'))
            ->assertRedirect(route('verification.notice'));
        $this->get($historyUrl)
            ->assertRedirect(route('verification.notice'));

        $inactiveAdministrator = $this->user(Role::Administrator, 'inactive-history-admin');
        $inactiveAdministrator->update(['is_active' => false]);
        $this->actingAs($inactiveAdministrator)
            ->get(route('admin.adopter-profiles.index'))
            ->assertRedirect(route('access-denied'));
        $this->get($historyUrl)
            ->assertRedirect(route('access-denied'));

        $volunteer = $this->user(Role::Volunteer, 'active-history-volunteer');
        $this->actingAs($volunteer)
            ->get(route('admin.adopter-profiles.index'))
            ->assertOk();
        $this->get($historyUrl)
            ->assertOk()
            ->assertSee('Security Pet');

        $administrator = $this->user(Role::Administrator, 'active-history-admin');
        $this->actingAs($administrator)
            ->get(route('admin.adopter-profiles.index'))
            ->assertOk();
        $this->get($historyUrl)
            ->assertOk()
            ->assertSee('Security Pet');

        $this->assertSame($adopter->id, $application->user_id);
    }

    /** @param array<string, mixed> $attributes */
    private function user(
        Role $role,
        string $key,
        string $firstName = 'Feature',
        string $lastName = 'Tester',
        bool $verified = true,
        array $attributes = [],
    ): User {
        return User::create(array_merge([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $key.'@example.test',
            'password' => 'password123',
            'role' => $role->value,
            'is_active' => true,
            'email_verified_at' => $verified ? now() : null,
        ], $attributes));
    }

    private function pet(string $name): Pet
    {
        return Pet::create([
            'name' => $name,
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function application(
        User $adopter,
        Pet $pet,
        ApplicationStatus $status,
        string $createdAt,
        array $attributes = [],
    ): AdoptionApplication {
        $application = AdoptionApplication::create(array_merge([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => $status->value,
            'physical_activity_level' => 'Active',
            'time_availability' => 'Flexible',
            'prior_pet_experience' => 'Experienced',
            'housing_type' => 'House',
            'household_composition' => 'Two adults',
            'income_range' => 'PHP 25,000-PHP 50,000',
        ], $attributes));

        if ($status === ApplicationStatus::Approved) {
            Handover::create([
                'code' => 'HV-TEST-'.$application->id,
                'application_id' => $application->id,
                'pet_id' => $pet->id,
                'user_id' => $adopter->id,
                'released_at' => now(),
                'adopter_outcome' => 'received',
                'received_at' => now(),
            ]);
        }

        return $this->timestamp($application, $createdAt);
    }

    private function approvedPlacement(User $adopter, string $petName, string $adoptedAt): AdoptionApplication
    {
        $pet = $this->pet($petName);
        $pet->update(['availability_status' => AvailabilityStatus::Adopted->value]);

        $application = $this->application(
            $adopter,
            $pet,
            ApplicationStatus::Approved,
            $adoptedAt,
            [
                'queue_closed_at' => $adoptedAt,
                'adopted_at' => $adoptedAt,
            ],
        );

        app(PostAdoptionScheduleService::class)->ensureForApplication($application);

        return $application->refresh();
    }

    private function historyUrl(User $adopter, AdoptionApplication $application): string
    {
        return route('admin.adopter-profiles.history', [
            'user' => $adopter,
            'application' => $application->id,
        ]);
    }

    private function timestamp(Model $model, string $timestamp): AdoptionApplication
    {
        $model->timestamps = false;
        $model->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
        $model->timestamps = true;

        /** @var AdoptionApplication $model */
        return $model->refresh();
    }
}
