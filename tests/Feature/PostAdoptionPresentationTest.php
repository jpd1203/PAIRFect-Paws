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
use App\Models\User;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PostAdoptionPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_my_check_ins_follow_the_three_day_three_week_three_month_sequence(): void
    {
        $adopter = $this->user(Role::Adopter, 'sequence-adopter');
        $application = $this->approvedApplication($adopter, 'Sequence Pet');

        // Insert out of order to prove presentation is driven by the schedule,
        // not insertion order or a reverse timestamp sort.
        $this->log($application, Milestone::ThreeMonths, '2026-11-25');
        $this->log($application, Milestone::ThreeDays, '2026-08-28');
        $this->log($application, Milestone::ThreeWeeks, '2026-09-15');

        $response = $this->actingAs($adopter)
            ->get(route('monitoring.my-checkins'))
            ->assertOk()
            ->assertSeeInOrder(['3-Day', '3-Week', '3-Month']);

        $this->assertSame(
            [Milestone::ThreeDays, Milestone::ThreeWeeks, Milestone::ThreeMonths],
            $response->viewData('logs')->pluck('milestone')->all(),
        );
    }

    public function test_utc_timestamps_are_presented_as_asia_manila_without_shifting_schedule_dates(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Asia/Manila', ManilaTime::timezone());

        $adopter = $this->user(Role::Adopter, 'timezone-adopter');
        $application = $this->approvedApplication($adopter, 'Timezone Pet');
        $application->forceFill([
            'status' => ApplicationStatus::InterviewScheduled,
            'interview_date' => '2026-08-24 23:30:00',
        ])->save();
        DB::table('adoption_applications')->where('id', $application->id)->update([
            'created_at' => '2026-08-24 23:30:00',
            'updated_at' => '2026-08-24 23:30:00',
        ]);

        $log = $this->log($application, Milestone::ThreeDays, '2026-08-25');
        $log->forceFill(['submitted_date' => '2026-08-24 23:30:00'])->save();

        $this->actingAs($adopter)
            ->get(route('application.index'))
            ->assertOk()
            ->assertSee('Submitted August 25, 2026')
            ->assertSee('August 25, 2026 7:30 AM');

        $receipt = view('mail.welfare-receipt', ['log' => $log->refresh()])->render();
        $this->assertStringContainsString('August 25, 2026 at 7:30 AM', $receipt);

        // scheduled_date is a calendar date in Asia/Manila, not a timestamp.
        $this->assertSame('2026-08-25', $log->refresh()->scheduled_date->toDateString());
    }

    public function test_manila_interview_input_is_normalized_to_utc_before_storage(): void
    {
        $now = CarbonImmutable::parse('2026-08-24 00:00:00', 'UTC');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        Mail::fake();

        $adopter = $this->user(Role::Adopter, 'interview-adopter');
        $staff = $this->user(Role::Administrator, 'interview-admin');
        $application = $this->approvedApplication(
            $adopter,
            'Interview Pet',
            ApplicationStatus::UnderReview,
        );
        $application->update([
            'document_verification_status' => DocumentVerificationStatus::Verified,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.applications.schedule'), [
                'application_id' => $application->id,
                'interview_date' => '2026-08-25',
                'interview_time' => '09:30',
                'staff_id' => $staff->id,
            ])
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('2026-08-25 01:30:00', $application->getRawOriginal('interview_date'));
        $this->assertSame('August 25, 2026', $application->interview_date_display);
        $this->assertSame('9:30 AM', $application->interview_time_display);
    }

    private function user(Role $role, string $identity): User
    {
        return User::create([
            'first_name' => 'Presentation',
            'last_name' => 'Tester',
            'email' => $identity.'@example.test',
            'password' => 'password123',
            'role' => $role->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function approvedApplication(
        User $adopter,
        string $petName,
        ApplicationStatus $status = ApplicationStatus::Approved,
    ): AdoptionApplication {
        $pet = Pet::create([
            'name' => $petName,
            'species' => 'Dog',
            'availability_status' => $status === ApplicationStatus::Approved
                ? AvailabilityStatus::Adopted->value
                : AvailabilityStatus::Available->value,
        ]);

        return AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => $status->value,
            'adopted_at' => '2026-08-25 00:00:00',
        ]);
    }

    private function log(
        AdoptionApplication $application,
        Milestone $milestone,
        string $scheduledDate,
    ): PostAdoptionLog {
        return PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => $milestone,
            'scheduled_date' => $scheduledDate,
        ]);
    }
}
