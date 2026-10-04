<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Mail\StatusUpdateMail;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class InterviewTypeTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_online_interview_is_saved_and_only_the_adopter_and_staff_see_the_meet_link(): void
    {
        Mail::fake();
        [$application, $adopter, $staff] = $this->placement();
        $otherAdopter = $this->matchingUser();
        $url = 'https://meet.google.com/abc-defg-hij';

        $this->actingAs($staff)->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'Online',
            'interview_meeting_url' => $url,
        ]))->assertSessionHas('success');

        $application->refresh();
        $this->assertSame(ApplicationStatus::InterviewScheduled, $application->status);
        $this->assertSame(AvailabilityStatus::SoftReserved, $application->pet->fresh()->availability_status);
        $this->assertSame('Online', $application->interview_mode);
        $this->assertSame($url, $application->interview_meeting_url);
        $this->assertNull($application->interview_location);
        $this->assertSame($staff->full_name, $application->conducted_by);

        Mail::assertQueued(StatusUpdateMail::class, function (StatusUpdateMail $mail) use ($adopter, $url): bool {
            $html = $mail->render();

            return $mail->hasTo($adopter->email)
                && $mail->event === 'interview_scheduled'
                && str_contains($html, 'Interview Type')
                && str_contains($html, 'Join Google Meet')
                && str_contains($html, e($url))
                && str_contains($html, 'Conducted By');
        });
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($staff->email) && str_contains(implode(' ', $mail->lines), $url));

        $this->actingAs($adopter)->get(route('application.index'))
            ->assertOk()->assertSee('Interview Type')->assertSee('Join Google Meet')
            ->assertSee('href="'.$url.'"', false);
        $this->actingAs($otherAdopter)->get(route('application.index'))
            ->assertOk()->assertDontSee($url);
        $this->actingAs($staff)->get(route('admin.applications.index'))
            ->assertOk()->assertSee('rInterviewMeetingLink')->assertSee($url)
            ->assertSee('id="scheduleInterviewMode"', false)
            ->assertSee('id="topScheduleMode"', false)
            ->assertSee('id="rRescheduleMode"', false);
        $this->actingAs($adopter)->get(route('admin.applications.index'))
            ->assertRedirect(route('access-denied'));

        $notes = AuditLog::where('entity_name', 'AdoptionApplication')
            ->where('entity_id', $application->id)->pluck('notes')->implode(' ');
        $this->assertStringContainsString('as Online', $notes);
        $this->assertStringNotContainsString($url, $notes);
        $this->assertStringNotContainsString($url, $adopter->inAppNotifications()->pluck('body')->implode(' '));
        $this->assertStringNotContainsString($url, $staff->inAppNotifications()->pluck('body')->implode(' '));
    }

    public function test_online_requires_a_real_https_google_meet_link_and_no_location(): void
    {
        Mail::fake();
        [$application, , $staff] = $this->placement();
        $this->actingAs($staff);

        $this->post(route('admin.applications.schedule'), $this->payload($application, $staff))
            ->assertSessionHasErrors('interview_mode');

        foreach ([
            null,
            'https://example.com/meeting',
            'https://meet.google.com.example.com/test',
            'javascript:alert(1)',
            'http://meet.google.com/abc-defg-hij',
            'https://meet.google.com/',
        ] as $url) {
            $payload = $this->payload($application, $staff, ['interview_mode' => 'Online']);
            if ($url !== null) {
                $payload['interview_meeting_url'] = $url;
            }
            $this->post(route('admin.applications.schedule'), $payload)
                ->assertSessionHasErrors('interview_meeting_url');
        }

        $this->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'Online',
            'interview_meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'interview_location' => 'Do not accept a competing location',
        ]))->assertSessionHasErrors('interview_location');

        $this->assertSame(ApplicationStatus::Pending, $application->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_in_person_requires_location_and_never_sends_a_meet_button(): void
    {
        Mail::fake();
        [$application, $adopter, $staff] = $this->placement();
        $this->actingAs($staff);

        $this->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'InPerson',
        ]))->assertSessionHasErrors('interview_location');
        $this->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'InPerson',
            'interview_location' => str_repeat('x', 1001),
        ]))->assertSessionHasErrors('interview_location');
        $this->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'InPerson',
            'interview_location' => 'PAIRfect Paws, Front Desk',
            'interview_meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]))->assertSessionHasErrors('interview_meeting_url');

        $location = 'PAIRfect Paws shelter, <Front Desk>';
        $this->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'InPerson',
            'interview_location' => $location,
        ]))->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('InPerson', $application->interview_mode);
        $this->assertSame($location, $application->interview_location);
        $this->assertNull($application->interview_meeting_url);
        Mail::assertQueued(StatusUpdateMail::class, function (StatusUpdateMail $mail) use ($location): bool {
            $html = $mail->render();

            return str_contains($html, 'In-person')
                && str_contains($html, e($location))
                && ! str_contains($html, '<Front Desk>')
                && ! str_contains($html, 'Join Google Meet');
        });
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($staff->email) && str_contains(implode(' ', $mail->lines), $location));
        $this->actingAs($adopter)->get(route('application.index'))
            ->assertOk()->assertSee('Interview Location')->assertSee(e($location), false)
            ->assertDontSee('<Front Desk>', false)
            ->assertDontSee('Join Google Meet');
    }

    public function test_rescheduling_switches_types_clears_opposite_field_and_uses_the_current_link(): void
    {
        Mail::fake();
        [$application, $adopter, $staff] = $this->placement();
        $this->actingAs($staff);
        $oldUrl = 'https://meet.google.com/old-link-aaa';
        $newUrl = 'https://meet.google.com/new-link-bbb';

        $this->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'Online', 'interview_meeting_url' => $oldUrl,
        ]))->assertSessionHas('success');

        $this->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'InPerson', 'interview_location' => 'Shelter Room 2',
            'interview_date' => ManilaTime::now()->addDays(4)->format('Y-m-d'),
        ]))->assertSessionHas('success');
        $application->refresh();
        $this->assertSame('InPerson', $application->interview_mode);
        $this->assertNull($application->interview_meeting_url);
        $this->assertSame('Shelter Room 2', $application->interview_location);

        $this->actingAs($adopter)->post(route('applications.reschedule.request', $application), [
            'options' => [[
                'date' => ManilaTime::now()->addDays(6)->format('Y-m-d'), 'time' => '14:30',
            ]],
        ])->assertSessionHasNoErrors();

        Mail::fake();
        $this->actingAs($staff)->post(route('admin.applications.schedule'), $this->payload($application, $staff, [
            'interview_mode' => 'Online', 'interview_meeting_url' => $newUrl,
            'interview_date' => ManilaTime::now()->addDays(6)->format('Y-m-d'),
        ]))->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('Online', $application->interview_mode);
        $this->assertSame($newUrl, $application->interview_meeting_url);
        $this->assertNull($application->interview_location);
        $this->assertSame('approved', $application->reschedule_status);
        Mail::assertQueued(StatusUpdateMail::class, function (StatusUpdateMail $mail) use ($newUrl, $oldUrl): bool {
            $html = $mail->render();

            return $mail->event === 'interview_rescheduled'
                && str_contains($html, $newUrl)
                && ! str_contains($html, $oldUrl)
                && ! str_contains($html, 'Shelter Room 2');
        });
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($staff->email)
            && str_contains(implode(' ', $mail->lines), $newUrl)
            && ! str_contains(implode(' ', $mail->lines), $oldUrl));
        $this->actingAs($adopter)->get(route('application.index'))
            ->assertOk()->assertSee($newUrl)->assertDontSee($oldUrl)->assertDontSee('Shelter Room 2');
    }

    public function test_legacy_interview_without_type_still_renders_without_join_link(): void
    {
        [$application, $adopter, $staff] = $this->placement();
        $application->update([
            'status' => ApplicationStatus::InterviewScheduled->value,
            'interview_date' => ManilaTime::now()->addDays(2)->utc(),
            'conducted_by' => $staff->full_name,
            'interview_mode' => null,
        ]);

        $this->actingAs($adopter)->get(route('application.index'))
            ->assertOk()->assertSee('Interview Schedule')
            ->assertDontSee('Join Google Meet')->assertDontSee('Interview Location');
        $html = (new StatusUpdateMail($application->fresh(), 'interview_scheduled'))->render();
        $this->assertStringContainsString('Interview Date', $html);
        $this->assertStringNotContainsString('Join Google Meet', $html);
    }

    /** @return array{AdoptionApplication, User, User} */
    private function placement(): array
    {
        $adopter = $this->matchingUser();
        $staff = $this->matchingUser(Role::Administrator);
        $this->completeMatchingProfile($adopter);
        $pet = Pet::create([
            'name' => 'Interview Pet', 'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);
        $this->completePetAssessment($pet);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id, 'pet_id' => $pet->id,
            'status' => ApplicationStatus::Pending->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);

        return [$application, $adopter, $staff];
    }

    /** @return array<string, mixed> */
    private function payload(AdoptionApplication $application, User $staff, array $overrides = []): array
    {
        return array_replace([
            'application_id' => $application->id,
            'staff_id' => $staff->id,
            'interview_date' => ManilaTime::now()->addDays(2)->format('Y-m-d'),
            'interview_time' => '14:30',
        ], $overrides);
    }
}
