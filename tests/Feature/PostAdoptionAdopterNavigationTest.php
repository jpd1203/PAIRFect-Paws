<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Services\PostAdoptionScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PostAdoptionAdopterNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_adopter_can_access_restored_sidebar_pages_with_the_correct_active_link(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00', PostAdoptionScheduleService::TIMEZONE));

        $adopter = $this->user(Role::Adopter, 'sidebar-adopter');
        $dueLog = $this->logFor($adopter, 'Sidebar Pet', '2026-08-25');
        Handover::create([
            'code' => 'sidebar-received',
            'application_id' => $dueLog->application_id,
            'pet_id' => $dueLog->adoptionApplication->pet_id,
            'user_id' => $adopter->id,
            'adopter_outcome' => 'received',
            'adopter_confirmed_at' => now(),
        ]);

        $submitPage = $this->actingAs($adopter)
            ->get(route('monitoring.submit-report'))
            ->assertOk()
            ->assertSee('Submit Report')
            ->assertSee(route('monitoring.submit-report'), false)
            ->assertSee('Overdue Notice')
            ->assertSee(route('monitoring.overdue-notice'), false)
            ->assertSee('Flagged Notice')
            ->assertSee(route('monitoring.flagged-notice'), false);
        $this->assertSidebarLinkActive($submitPage, 'monitoring.submit-report', 'Submit Report');
        $this->assertSidebarLinkInactive($submitPage, 'monitoring.my-checkins', 'My Check-ins');

        $overduePage = $this->get(route('monitoring.overdue-notice'))->assertOk();
        $this->assertSidebarLinkActive($overduePage, 'monitoring.overdue-notice', 'Overdue Notice');

        $flaggedPage = $this->get(route('monitoring.flagged-notice'))->assertOk();
        $this->assertSidebarLinkActive($flaggedPage, 'monitoring.flagged-notice', 'Flagged Notice');

        $reportForm = $this->get(route('monitoring.create', $dueLog))->assertOk();
        $this->assertSidebarLinkActive($reportForm, 'monitoring.submit-report', 'Submit Report');
        $this->assertSidebarLinkInactive($reportForm, 'monitoring.my-checkins', 'My Check-ins');

        $unverifiedAdopter = $this->user(Role::Adopter, 'unverified-sidebar-adopter', verified: false);

        foreach ([
            'monitoring.submit-report',
            'monitoring.overdue-notice',
            'monitoring.flagged-notice',
        ] as $routeName) {
            $this->actingAs($unverifiedAdopter)
                ->get(route($routeName))
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_submit_report_page_contains_only_the_adopters_due_incomplete_approved_logs(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00', PostAdoptionScheduleService::TIMEZONE));

        $adopter = $this->user(Role::Adopter, 'due-owner');
        $otherAdopter = $this->user(Role::Adopter, 'due-other-owner');

        $overdue = $this->logFor($adopter, 'Owned Overdue Pet', '2026-08-23');
        $dueToday = $this->logFor($adopter, 'Owned Due Pet', '2026-08-25');
        $future = $this->logFor($adopter, 'Owned Future Pet', '2026-08-26');
        $submitted = $this->logFor($adopter, 'Owned Submitted Pet', '2026-08-24', [
            'submitted_date' => '2026-08-24 14:00:00',
        ]);
        $foreign = $this->logFor($otherAdopter, 'Foreign Due Pet', '2026-08-25');
        $notApproved = $this->logFor(
            $adopter,
            'Rejected Application Pet',
            '2026-08-25',
            applicationStatus: ApplicationStatus::Rejected,
        );

        $this->actingAs($adopter)
            ->get(route('monitoring.submit-report'))
            ->assertOk()
            ->assertViewHas(
                'logs',
                fn (Collection $logs): bool => $logs->pluck('id')->all() === [
                    $overdue->id,
                    $dueToday->id,
                ],
            )
            ->assertSee('Owned Overdue Pet')
            ->assertSee('Owned Due Pet')
            ->assertSee(route('monitoring.create', $overdue), false)
            ->assertSee(route('monitoring.create', $dueToday), false)
            ->assertDontSee('Owned Future Pet')
            ->assertDontSee('Owned Submitted Pet')
            ->assertDontSee('Foreign Due Pet')
            ->assertDontSee('Rejected Application Pet')
            ->assertDontSee(route('monitoring.create', $future), false)
            ->assertDontSee(route('monitoring.create', $submitted), false)
            ->assertDontSee(route('monitoring.create', $foreign), false)
            ->assertDontSee(route('monitoring.create', $notApproved), false);
    }

    public function test_overdue_notice_contains_only_the_adopters_past_due_incomplete_approved_logs(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00', PostAdoptionScheduleService::TIMEZONE));

        $adopter = $this->user(Role::Adopter, 'overdue-owner');
        $otherAdopter = $this->user(Role::Adopter, 'overdue-other-owner');

        $overdue = $this->logFor($adopter, 'Owned Overdue Notice Pet', '2026-08-24');
        $dueToday = $this->logFor($adopter, 'Due Today Notice Pet', '2026-08-25');
        $submitted = $this->logFor($adopter, 'Submitted Overdue Notice Pet', '2026-08-23', [
            'submitted_date' => '2026-08-24 09:00:00',
        ]);
        $foreign = $this->logFor($otherAdopter, 'Foreign Overdue Notice Pet', '2026-08-23');
        $notApproved = $this->logFor(
            $adopter,
            'Unapproved Overdue Notice Pet',
            '2026-08-23',
            applicationStatus: ApplicationStatus::Rejected,
        );

        $this->actingAs($adopter)
            ->get(route('monitoring.overdue-notice'))
            ->assertOk()
            ->assertViewHas(
                'overdueLogs',
                fn (Collection $logs): bool => $logs->pluck('id')->all() === [$overdue->id],
            )
            ->assertSee('Owned Overdue Notice Pet')
            ->assertSee(route('monitoring.create', $overdue), false)
            ->assertDontSee('Due Today Notice Pet')
            ->assertDontSee('Submitted Overdue Notice Pet')
            ->assertDontSee('Foreign Overdue Notice Pet')
            ->assertDontSee('Unapproved Overdue Notice Pet')
            ->assertDontSee(route('monitoring.create', $dueToday), false)
            ->assertDontSee(route('monitoring.create', $submitted), false)
            ->assertDontSee(route('monitoring.create', $foreign), false)
            ->assertDontSee(route('monitoring.create', $notApproved), false);
    }

    public function test_flagged_notice_contains_only_the_adopters_unresolved_flagged_approved_logs_without_staff_only_reason_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-25 12:00:00', PostAdoptionScheduleService::TIMEZONE));

        $adopter = $this->user(Role::Adopter, 'flagged-owner');
        $otherAdopter = $this->user(Role::Adopter, 'flagged-other-owner');

        $unresolved = $this->logFor($adopter, 'Owned Flagged Notice Pet', '2026-08-23', [
            'submitted_date' => '2026-08-23 16:00:00',
            'is_flagged' => true,
            'flag_reasons' => [[
                'code' => 'private_staff_rule',
                'message' => 'PRIVATE STAFF-ONLY FLAG REASON',
            ]],
            'photo_sha256' => str_repeat('a', 64),
            'video_path' => 'post-adoption-videos/private.webm',
            'video_sha256' => str_repeat('b', 64),
            'video_mime_type' => 'video/webm',
            'video_duration_ms' => 3000,
        ]);
        $resolved = $this->logFor($adopter, 'Resolved Flagged Notice Pet', '2026-08-22', [
            'submitted_date' => '2026-08-22 12:00:00',
            'is_flagged' => true,
            'resolved_at' => '2026-08-24 12:00:00',
        ]);
        $unflagged = $this->logFor($adopter, 'Unflagged Notice Pet', '2026-08-21', [
            'submitted_date' => '2026-08-21 12:00:00',
        ]);
        $foreign = $this->logFor($otherAdopter, 'Foreign Flagged Notice Pet', '2026-08-20', [
            'is_flagged' => true,
        ]);
        $notApproved = $this->logFor(
            $adopter,
            'Unapproved Flagged Notice Pet',
            '2026-08-19',
            ['is_flagged' => true],
            ApplicationStatus::Rejected,
        );

        $this->actingAs($adopter)
            ->get(route('monitoring.flagged-notice'))
            ->assertOk()
            ->assertViewHas('flaggedLogs', function (Collection $logs) use ($unresolved): bool {
                if ($logs->pluck('id')->all() !== [$unresolved->id]) {
                    return false;
                }

                $attributes = $logs->firstOrFail()->getAttributes();

                return ! array_key_exists('flag_reasons', $attributes)
                    && ! array_key_exists('photo_sha256', $attributes)
                    && ! array_key_exists('c2pa_manifest_sha256', $attributes)
                    && ! array_key_exists('video_path', $attributes)
                    && ! array_key_exists('video_sha256', $attributes)
                    && ! array_key_exists('video_mime_type', $attributes)
                    && ! array_key_exists('video_duration_ms', $attributes);
            })
            ->assertSee('Owned Flagged Notice Pet')
            ->assertSee('Under staff review')
            ->assertDontSee('PRIVATE STAFF-ONLY FLAG REASON')
            ->assertDontSee('Resolved Flagged Notice Pet')
            ->assertDontSee('Unflagged Notice Pet')
            ->assertDontSee('Foreign Flagged Notice Pet')
            ->assertDontSee('Unapproved Flagged Notice Pet');

        $this->assertTrue($resolved->is_flagged);
        $this->assertFalse($unflagged->is_flagged);
        $this->assertTrue($foreign->is_flagged);
        $this->assertTrue($notApproved->is_flagged);
    }

    /** @param array<string, mixed> $attributes */
    private function logFor(
        User $adopter,
        string $petName,
        string $scheduledDate,
        array $attributes = [],
        ApplicationStatus $applicationStatus = ApplicationStatus::Approved,
    ): PostAdoptionLog {
        $pet = Pet::create([
            'name' => $petName,
            'species' => 'Dog',
            'availability_status' => $applicationStatus === ApplicationStatus::Approved
                ? AvailabilityStatus::Adopted->value
                : AvailabilityStatus::Available->value,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => $applicationStatus->value,
            'adopted_at' => CarbonImmutable::parse(
                $scheduledDate,
                PostAdoptionScheduleService::TIMEZONE,
            )->subDays(3),
        ]);

        return PostAdoptionLog::create(array_merge([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays->value,
            'scheduled_date' => $scheduledDate,
            'reminders_sent' => 0,
            'is_flagged' => false,
            'version' => 1,
        ], $attributes));
    }

    private function user(Role $role, string $key, bool $verified = true): User
    {
        return User::create([
            'first_name' => 'Feature',
            'last_name' => 'Adopter',
            'email' => $key.'@example.test',
            'password' => 'password123',
            'role' => $role->value,
            'is_active' => true,
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    private function assertSidebarLinkActive(
        TestResponse $response,
        string $routeName,
        string $label,
    ): void {
        $this->assertMatchesRegularExpression(
            $this->sidebarLinkPattern($routeName, $label, active: true),
            $response->getContent(),
        );
    }

    private function assertSidebarLinkInactive(
        TestResponse $response,
        string $routeName,
        string $label,
    ): void {
        $this->assertMatchesRegularExpression(
            $this->sidebarLinkPattern($routeName, $label, active: false),
            $response->getContent(),
        );
    }

    private function sidebarLinkPattern(string $routeName, string $label, bool $active): string
    {
        $url = preg_quote(htmlspecialchars(route($routeName), ENT_QUOTES), '~');
        $label = preg_quote($label, '~');
        $classPattern = $active
            ? '[^"\']*\bactive\b[^"\']*'
            : '(?:(?!\bactive\b)[^"\'])*';

        return '~<a(?=[^>]*href=["\']'.$url.'["\'])'
            .'(?=[^>]*class=["\']'.$classPattern.'["\'])'
            .'[^>]*>.*?'.$label.'.*?</a>~is';
    }
}
