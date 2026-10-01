<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Mail\StatusUpdateMail;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\User;
use App\Services\ReservationQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Concerns\BuildsMatchingFixtures;

class ReservationQueueTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_scheduling_soft_reserves_and_no_show_promotes_the_next_compatibility_ranked_application(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        $queue = app(ReservationQueueService::class);

        $queue->schedule($first, now()->addDay(), $staff, $staff->id);

        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
        $this->assertTrue($first->refresh()->is_primary_candidate);
        $this->assertSame(ApplicationStatus::InterviewScheduled, $first->status);
        $this->assertSame(ApplicationStatus::Waitlisted, $second->refresh()->status);
        Mail::assertQueued(StatusUpdateMail::class, fn (StatusUpdateMail $mail): bool => $mail->hasTo($first->user->email) && $mail->event === 'interview_scheduled'
        );
        Mail::assertQueued(StatusUpdateMail::class, fn (StatusUpdateMail $mail): bool => $mail->hasTo($second->user->email) && $mail->event === 'application_waitlisted'
        );

        $promoted = $queue->resolvePrimary(
            $first,
            ApplicationStatus::NoShow,
            'Applicant did not attend the scheduled interview.',
            $staff->id
        );

        $this->assertSame($second->id, $promoted?->id);
        $this->assertSame(ApplicationStatus::NoShow, $first->refresh()->status);
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $second->refresh()->status);
        $this->assertTrue($second->is_primary_candidate);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
        Mail::assertQueued(StatusUpdateMail::class, fn (StatusUpdateMail $mail): bool => $mail->hasTo($second->user->email) && $mail->event === 'queue_promoted'
        );
    }

    public function test_a_scheduled_interview_can_be_rescheduled_with_updated_notifications(): void
    {
        Mail::fake();
        [, $first, , $staff] = $this->queueFixture();
        $queue = app(ReservationQueueService::class);
        $queue->schedule($first, now()->addDay(), $staff, $staff->id);

        Mail::fake();
        $newDate = now()->addDays(3);
        $queue->schedule($first->refresh(), $newDate, $staff, $staff->id);

        $this->assertSame(
            $newDate->format('Y-m-d H:i'),
            $first->refresh()->interview_date->format('Y-m-d H:i'),
        );
        Mail::assertQueued(StatusUpdateMail::class, fn (StatusUpdateMail $mail): bool => $mail->hasTo($first->user->email)
            && $mail->event === 'interview_rescheduled'
            && filled($mail->previousInterviewDate)
        );
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool => $mail->hasTo($staff->email)
            && str_starts_with($mail->subjectLine, 'Interview rescheduled')
        );
    }

    public function test_scheduled_email_offers_an_authenticated_reschedule_link(): void
    {
        Mail::fake();
        [, $application, , $staff] = $this->queueFixture();
        app(ReservationQueueService::class)->schedule($application, now()->addDay(), $staff, $staff->id);

        Mail::assertQueued(StatusUpdateMail::class, function (StatusUpdateMail $mail) use ($application): bool {
            return $mail->event === 'interview_scheduled'
                && str_contains($mail->render(), 'Request Reschedule')
                && str_contains($mail->rescheduleUrl, 'reschedule='.$application->id);
        });
    }

    public function test_adopter_request_stays_pending_without_changing_the_official_schedule_or_queue(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        app(ReservationQueueService::class)->schedule($first, now()->addDays(2), $staff, $staff->id);
        $originalDate = $first->refresh()->interview_date->toISOString();
        $originalScore = $first->knn_score;
        $optionDate = \App\Support\ManilaTime::now()->addDays(4)->format('Y-m-d');

        $this->actingAs($first->user)->post(route('applications.reschedule.request', $first), [
            'reason' => 'Work schedule conflict',
            'options' => [['date' => $optionDate, 'time' => '15:00']],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('pending', $first->refresh()->reschedule_status);
        $this->assertSame($originalDate, $first->interview_date->toISOString());
        $this->assertSame($originalScore, $first->knn_score);
        $this->assertTrue($first->is_primary_candidate);
        $this->assertSame(ApplicationStatus::Waitlisted, $second->refresh()->status);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
        $this->actingAs($staff)->get(route('admin.applications.index'))
            ->assertOk()->assertSee('Reschedule Requested')->assertSee('Work schedule conflict')
            ->assertSee($optionDate);

        $this->actingAs($first->user)->post(route('applications.reschedule.request', $first), [
            'options' => [['date' => $optionDate, 'time' => '16:00']],
        ])->assertSessionHasErrors('application');
    }

    public function test_staff_confirms_an_offered_time_using_the_existing_schedule_action(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        app(ReservationQueueService::class)->schedule($first, now()->addDays(2), $staff, $staff->id);
        $date = \App\Support\ManilaTime::now()->addDays(4)->format('Y-m-d');
        $this->actingAs($first->user)->post(route('applications.reschedule.request', $first), [
            'options' => [['date' => $date, 'time' => '15:00']],
        ])->assertSessionHasNoErrors();
        Mail::fake();

        $this->actingAs($staff)->post(route('admin.applications.schedule'), [
            'application_id' => $first->id,
            'interview_date' => $date,
            'interview_time' => '15:00',
            'staff_id' => $staff->id,
        ])->assertSessionHas('success');

        $this->assertSame('approved', $first->refresh()->reschedule_status);
        $this->assertSame("{$date} 15:00", \App\Support\ManilaTime::format($first->interview_date, 'Y-m-d H:i'));
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
        $this->assertSame(ApplicationStatus::Waitlisted, $second->refresh()->status);
        $this->actingAs($first->user)->get(route('application.index'))
            ->assertOk()
            ->assertSee(\App\Support\ManilaTime::format($first->interview_date, 'F j, Y g:i A'))
            ->assertSee('Your interview was rescheduled');
        Mail::assertQueued(StatusUpdateMail::class, fn (StatusUpdateMail $mail): bool => $mail->event === 'interview_rescheduled'
            && $mail->hasTo($first->user->email)
            && str_contains($mail->render(), 'Request Reschedule'));
    }

    public function test_pending_request_blocks_automatic_and_manual_no_show_until_reviewed(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        app(ReservationQueueService::class)->schedule($first, now()->addDays(2), $staff, $staff->id);
        $date = \App\Support\ManilaTime::now()->addDays(4)->format('Y-m-d');
        $this->actingAs($first->user)->post(route('applications.reschedule.request', $first), [
            'options' => [['date' => $date, 'time' => '10:00']],
        ])->assertSessionHasNoErrors();
        $first->update(['interview_date' => now()->subDays(5), 'admin_review_flagged_at' => now()->subDays(2)]);

        $this->assertSame(['flagged' => 0, 'promoted' => 0], app(ReservationQueueService::class)->processTimeouts());
        $this->actingAs($staff)->post(route('admin.applications.queue-outcome', $first), [
            'outcome' => 'NoShow', 'reason' => 'Missed interview',
        ])->assertSessionHasErrors('outcome');
        $this->assertSame(ApplicationStatus::InterviewScheduled, $first->refresh()->status);
        $this->assertSame(ApplicationStatus::Waitlisted, $second->refresh()->status);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
    }

    public function test_staff_can_decline_a_request_without_moving_the_interview_or_queue(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        app(ReservationQueueService::class)->schedule($first, now()->addDays(2), $staff, $staff->id);
        $originalDate = $first->refresh()->interview_date->toISOString();
        $this->actingAs($first->user)->post(route('applications.reschedule.request', $first), [
            'options' => [['date' => \App\Support\ManilaTime::now()->addDays(4)->format('Y-m-d'), 'time' => '10:00']],
        ])->assertSessionHasNoErrors();
        Mail::fake();

        $this->actingAs($staff)->post(route('admin.applications.reschedule.decline', $first))
            ->assertSessionHas('success');
        $this->assertSame('declined', $first->refresh()->reschedule_status);
        $this->assertSame($originalDate, $first->interview_date->toISOString());
        $this->assertSame(ApplicationStatus::Waitlisted, $second->refresh()->status);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool => $mail->hasTo($first->user->email)
            && str_contains($mail->subjectLine, 'reschedule request update'));
    }

    public function test_reschedule_validation_and_ownership_are_enforced(): void
    {
        Mail::fake();
        [, $first, $second, $staff] = $this->queueFixture();
        app(ReservationQueueService::class)->schedule($first, now()->addDays(2), $staff, $staff->id);

        $this->actingAs($first->user)->post(route('applications.reschedule.request', $first), [
            'options' => [['date' => '2020-01-01', 'time' => '10:00']],
        ])->assertSessionHasErrors('options');
        $this->actingAs($second->user)->post(route('applications.reschedule.request', $first), [
            'options' => [['date' => \App\Support\ManilaTime::now()->addDays(4)->format('Y-m-d'), 'time' => '10:00']],
        ])->assertNotFound();
        $this->assertNull($first->refresh()->reschedule_status);
    }

    public function test_adopter_page_does_not_load_or_render_internal_staff_notes(): void
    {
        Mail::fake();
        [, $first, , $staff] = $this->queueFixture();
        app(ReservationQueueService::class)->schedule($first, now()->addDays(2), $staff, $staff->id);
        $first->update([
            'interview_notes' => 'PRIVATE_INTERVIEW_SENTINEL',
            'decision_remarks' => 'PRIVATE_DECISION_SENTINEL',
            'override_reason' => 'PRIVATE_OVERRIDE_SENTINEL',
        ]);

        $this->actingAs($first->user)->get(route('application.index'))
            ->assertOk()
            ->assertDontSee('PRIVATE_INTERVIEW_SENTINEL')
            ->assertDontSee('PRIVATE_DECISION_SENTINEL')
            ->assertDontSee('PRIVATE_OVERRIDE_SENTINEL')
            ->assertViewHas('applications', function ($applications): bool {
                $application = $applications->first();
                return ! array_key_exists('interview_notes', $application->getAttributes())
                    && ! array_key_exists('decision_remarks', $application->getAttributes())
                    && ! array_key_exists('override_reason', $application->getAttributes());
            });
        $this->actingAs($staff)->get(route('admin.applications.index'))
            ->assertOk()->assertSee('PRIVATE_INTERVIEW_SENTINEL')->assertSee('PRIVATE_DECISION_SENTINEL');
        $this->actingAs($first->user)->get(route('admin.applications.index'))
            ->assertRedirect(route('access-denied'));
    }

    public function test_rejecting_a_verified_non_primary_application_before_scheduling_only_terminates_that_application(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        $queue = app(ReservationQueueService::class);
        $first->update([
            'document_verification_status' => DocumentVerificationStatus::LegacyReview->value,
        ]);

        $promoted = $queue->rejectApplication(
            $first,
            'The housing arrangement is not suitable for this pet.',
            $staff->id
        );

        $this->assertNull($promoted);
        $this->assertSame(ApplicationStatus::Rejected, $first->refresh()->status);
        $this->assertFalse($first->is_primary_candidate);
        $this->assertSame('The housing arrangement is not suitable for this pet.', $first->decision_remarks);
        $this->assertSame(ApplicationStatus::UnderReview, $second->refresh()->status);
        $this->assertFalse($second->is_primary_candidate);
        $this->assertSame(AvailabilityStatus::Available, $pet->refresh()->availability_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Application Rejected Before Interview',
            'entity_id' => $first->id,
            'notes' => 'The housing arrangement is not suitable for this pet.',
        ]);
        Mail::assertQueued(StatusUpdateMail::class, fn (StatusUpdateMail $mail): bool => $mail->hasTo($first->user->email) && $mail->event === 'application_rejected'
        );
    }

    public function test_staff_decision_post_rejects_a_verified_application_before_interview(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();

        $response = $this->actingAs($staff)
            ->from(route('admin.applications.index'))
            ->post(route('admin.applications.decide', $first), [
                'decision' => ApplicationStatus::Rejected->value,
                'decision_remarks' => 'The application does not meet the documented adoption requirements.',
            ]);

        $response
            ->assertRedirect(route('admin.applications.index'))
            ->assertSessionHas('success', 'Application Rejected.');
        $this->assertSame(ApplicationStatus::Rejected, $first->refresh()->status);
        $this->assertFalse($first->is_primary_candidate);
        $this->assertSame(
            'The application does not meet the documented adoption requirements.',
            $first->decision_remarks
        );
        $this->assertSame(ApplicationStatus::UnderReview, $second->refresh()->status);
        $this->assertFalse($second->is_primary_candidate);
        $this->assertSame(AvailabilityStatus::Available, $pet->refresh()->availability_status);
    }

    public function test_staff_can_reject_an_application_requiring_a_document_update(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        $first->update([
            'status' => ApplicationStatus::DocumentFlagged->value,
            'document_verification_status' => DocumentVerificationStatus::NeedsResubmission->value,
        ]);

        $this->actingAs($staff)
            ->from(route('admin.applications.index'))
            ->post(route('admin.applications.decide', $first), [
                'decision' => ApplicationStatus::Rejected->value,
                'decision_remarks' => 'The submitted follow-up document did not meet shelter requirements.',
            ])
            ->assertRedirect(route('admin.applications.index'))
            ->assertSessionHas('success', 'Application Rejected.');

        $this->assertSame(ApplicationStatus::Rejected, $first->refresh()->status);
        $this->assertFalse($first->is_primary_candidate);
        $this->assertFalse($first->canUploadReplacementDocument());
        $this->assertSame(ApplicationStatus::UnderReview, $second->refresh()->status);
        $this->assertSame(AvailabilityStatus::Available, $pet->refresh()->availability_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Application Rejected During Document Update',
            'entity_id' => $first->id,
            'notes' => 'The submitted follow-up document did not meet shelter requirements.',
        ]);
    }

    public function test_application_review_modal_renders_separate_schedule_and_reject_buttons(): void
    {
        [, , , $staff] = $this->queueFixture();

        $response = $this->actingAs($staff)
            ->get(route('admin.applications.index'))
            ->assertOk()
            ->assertSee('class="btn btn-blue" id="rScheduleBtn"', false)
            ->assertSee('id="rRejectBtn"', false);

        $this->assertLessThan(
            strpos($response->getContent(), 'id="rScheduleBtn"'),
            strpos($response->getContent(), 'id="rRejectBtn"'),
            'Reject should appear before Schedule Interview in the modal footer.'
        );
    }

    public function test_rejecting_a_promoted_primary_candidate_advances_then_exhausts_the_queue(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        $queue = app(ReservationQueueService::class);
        $thirdUser = User::create([
            'first_name' => 'Third',
            'last_name' => 'Applicant',
            'email' => 'third@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
        ]);
        $this->completeMatchingProfile($thirdUser);
        $third = AdoptionApplication::create([
            'user_id' => $thirdUser->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::UnderReview->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);

        $queue->schedule($first, now()->addDay(), $staff, $staff->id);
        $queue->resolvePrimary(
            $first,
            ApplicationStatus::Withdrawn,
            'The first applicant withdrew before the interview.',
            $staff->id
        );

        $this->assertSame(ApplicationStatus::PrimaryCandidate, $second->refresh()->status);
        $this->assertTrue($second->is_primary_candidate);

        $promoted = $queue->rejectApplication(
            $second,
            'The promoted applicant did not meet the shelter requirements.',
            $staff->id
        );

        $this->assertSame($third->id, $promoted?->id);
        $this->assertSame(ApplicationStatus::Rejected, $second->refresh()->status);
        $this->assertFalse($second->is_primary_candidate);
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $third->refresh()->status);
        $this->assertTrue($third->is_primary_candidate);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Primary Candidate Rejected',
            'entity_id' => $second->id,
            'notes' => 'The promoted applicant did not meet the shelter requirements.',
        ]);

        $next = $queue->rejectApplication(
            $third,
            'The final applicant also did not meet the shelter requirements.',
            $staff->id
        );

        $this->assertNull($next);
        $this->assertSame(ApplicationStatus::Rejected, $third->refresh()->status);
        $this->assertFalse($third->is_primary_candidate);
        $this->assertSame(AvailabilityStatus::Available, $pet->refresh()->availability_status);
    }

    public function test_rejecting_the_primary_candidate_after_interview_review_promotes_the_waitlist(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        $queue = app(ReservationQueueService::class);

        $queue->schedule($first, now()->addDay(), $staff, $staff->id);
        $queue->recordInterviewNotes(
            $first,
            'The interview identified concerns that require a final decision.',
            $staff->full_name,
            $staff->id
        );

        $promoted = $queue->rejectApplication(
            $first,
            'The concerns documented during the interview could not be resolved.',
            $staff->id
        );

        $this->assertSame($second->id, $promoted?->id);
        $this->assertSame(ApplicationStatus::Rejected, $first->refresh()->status);
        $this->assertFalse($first->is_primary_candidate);
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $second->refresh()->status);
        $this->assertTrue($second->is_primary_candidate);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
    }

    public function test_approval_closes_the_waitlist_and_marks_the_pet_adopted(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        $queue = app(ReservationQueueService::class);

        $queue->schedule($first, now()->addDay(), $staff, $staff->id);
        $queue->recordInterviewNotes($first, 'Suitable home and adopter.', $staff->full_name, $staff->id);
        $closed = $queue->approve($first, 'Approved after review.', $staff->id);

        $this->assertCount(1, $closed);
        $this->assertSame(ApplicationStatus::Approved, $first->refresh()->status);
        $this->assertNotNull($first->adopted_at);
        $this->assertSame(ApplicationStatus::Closed, $second->refresh()->status);
        $this->assertSame(AvailabilityStatus::Adopted, $pet->refresh()->availability_status);
        $this->assertCount(3, $first->postAdoptionLogs()->get());
        Mail::assertQueued(StatusUpdateMail::class, fn (StatusUpdateMail $mail): bool => $mail->hasTo($first->user->email) && $mail->event === 'application_approved'
        );
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool => $mail->hasTo($second->user->email) && $mail->subjectLine === 'Adoption queue closed'
        );
    }

    public function test_equal_scores_use_submission_order_and_admin_can_override_with_a_recorded_reason(): void
    {
        Mail::fake();
        [$pet, $first, $second, $staff] = $this->queueFixture();
        $queue = app(ReservationQueueService::class);

        try {
            $queue->schedule($second, now()->addDay(), $staff, $staff->id);
            $this->fail('An applicant with an equal score bypassed the timestamp tie-breaker.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('application_id', $exception->errors());
        }

        $queue->schedule($first, now()->addDay(), $staff, $staff->id);
        $queue->overridePrimary($second, 'Safety review requires an experienced adopter.', $staff->id);

        $this->assertSame(ApplicationStatus::Waitlisted, $first->refresh()->status);
        $this->assertFalse($first->is_primary_candidate);
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $second->refresh()->status);
        $this->assertTrue($second->is_primary_candidate);
        $this->assertSame('Safety review requires an experienced adopter.', $second->override_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Reservation Queue Administrative Override',
            'entity_id' => $second->id,
        ]);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
    }

    public function test_overdue_interview_is_flagged_before_automatic_promotion(): void
    {
        Mail::fake();
        [, $first, $second, $staff] = $this->queueFixture();
        $queue = app(ReservationQueueService::class);

        $queue->schedule($first, now()->addDay(), $staff, $staff->id);
        $first->update(['interview_date' => now()->subHours(73)]);

        $firstRun = $queue->processTimeouts();
        $this->assertSame(['flagged' => 1, 'promoted' => 0], $firstRun);
        $this->assertNotNull($first->refresh()->admin_review_flagged_at);
        $this->assertSame(ApplicationStatus::Waitlisted, $second->refresh()->status);

        $this->travel(25)->hours();
        $secondRun = $queue->processTimeouts();

        $this->assertSame(['flagged' => 0, 'promoted' => 1], $secondRun);
        $this->assertSame(ApplicationStatus::NoShow, $first->refresh()->status);
        $this->assertSame(ApplicationStatus::PrimaryCandidate, $second->refresh()->status);
    }

    public function test_adopter_application_page_displays_every_application(): void
    {
        $adopter = User::create([
            'first_name' => 'Multiple',
            'last_name' => 'Applicant',
            'email' => 'multiple@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $firstPet = Pet::create([
            'name' => 'Alpha',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);
        $secondPet = Pet::create([
            'name' => 'Bravo',
            'species' => 'Cat',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);
        AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $firstPet->id,
            'status' => ApplicationStatus::Pending->value,
        ]);
        AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $secondPet->id,
            'status' => ApplicationStatus::Rejected->value,
        ]);

        $response = $this->actingAs($adopter)
            ->get(route('application.index'))
            ->assertOk()
            ->assertSee('My Applications')
            ->assertSee('Alpha')
            ->assertSee('Bravo')
            ->assertSee('Pending')
            ->assertSee('Rejected');

        $this->assertLessThan(
            strpos($response->getContent(), 'Alpha'),
            strpos($response->getContent(), 'Bravo'),
            'The newest application should be displayed first.'
        );
    }

    private function queueFixture(): array
    {
        $staff = User::create([
            'first_name' => 'Queue',
            'last_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Administrator->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $firstUser = User::create([
            'first_name' => 'First',
            'last_name' => 'Applicant',
            'email' => 'first@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $secondUser = User::create([
            'first_name' => 'Second',
            'last_name' => 'Applicant',
            'email' => 'second@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $pet = Pet::create([
            'name' => 'Queue Test Pet',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);
        $this->completeMatchingProfile($firstUser);
        $this->completeMatchingProfile($secondUser);
        $this->completePetAssessment($pet);
        $first = AdoptionApplication::create([
            'user_id' => $firstUser->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::UnderReview->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);
        $second = AdoptionApplication::create([
            'user_id' => $secondUser->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::UnderReview->value,
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
        ]);

        return [$pet, $first, $second, $staff];
    }
}
