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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdoptionPipelineStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_application_progresses_through_interview_notes_review_and_approval(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-09-02 08:00:00', 'UTC'));

        $staff = User::create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'pipeline.staff@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Administrator->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $adopter = User::create([
            'first_name' => 'Josh',
            'last_name' => 'Oliver',
            'email' => 'pipeline.adopter@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $pet = Pet::create([
            'name' => 'Oreo',
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Pending->value,
            'document_verification_status' => DocumentVerificationStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('adoption_applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Pending->value,
        ]);

        $queue = app(ReservationQueueService::class);
        $scheduledAt = Carbon::parse('2026-09-05 10:30:00', 'Asia/Manila');

        try {
            $queue->schedule($application, $scheduledAt, $staff, $staff->id);
            $this->fail('A Pending, unverified application bypassed document verification.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('application_id', $exception->errors());
        }

        // Successful OCR/manual verification is the required gate between a
        // newly submitted Pending application and interview eligibility.
        $application->update([
            'document_verification_status' => DocumentVerificationStatus::Verified->value,
            'document_verified_at' => now(),
            'status' => ApplicationStatus::UnderReview->value,
        ]);

        $queue->schedule($application->fresh(), $scheduledAt, $staff, $staff->id);
        $application->refresh();

        $this->assertSame(ApplicationStatus::InterviewScheduled, $application->status);
        $this->assertTrue($application->is_primary_candidate);
        $this->assertSame('2026-09-05 02:30:00', $application->getRawOriginal('interview_date'));
        $this->assertSame($staff->full_name, $application->conducted_by);
        $this->assertSame(AvailabilityStatus::SoftReserved, $pet->refresh()->availability_status);
        Mail::assertQueued(
            StatusUpdateMail::class,
            fn (StatusUpdateMail $mail): bool => $mail->hasTo($adopter->email)
                && $mail->event === 'interview_scheduled'
                && $mail->queue === 'emails'
        );
        Mail::assertQueued(
            TransactionalMail::class,
            fn (TransactionalMail $mail): bool => $mail->hasTo($staff->email)
                && $mail->subjectLine === "Interview assigned - application #{$application->id}"
                && $mail->queue === 'emails'
        );

        $queue->recordInterviewNotes(
            $application,
            'The adopter demonstrated a safe home and an appropriate care plan.',
            $staff->full_name,
            $staff->id,
        );
        $application->refresh();

        $this->assertSame(ApplicationStatus::UnderReview, $application->status);
        $this->assertSame(
            'The adopter demonstrated a safe home and an appropriate care plan.',
            $application->interview_notes,
        );
        $this->assertSame($staff->full_name, $application->conducted_by);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staff->id,
            'action' => 'Interview Notes Added',
            'entity_name' => 'AdoptionApplication',
            'entity_id' => $application->id,
        ]);
        Mail::assertQueued(
            StatusUpdateMail::class,
            fn (StatusUpdateMail $mail): bool => $mail->hasTo($adopter->email)
                && $mail->event === 'status_updated'
                && $mail->status === ApplicationStatus::UnderReview->value
        );

        $queue->approve($application, 'Approved after final administrative review.', $staff->id);
        $application->refresh();

        $this->assertSame(ApplicationStatus::Approved, $application->status);
        $this->assertFalse($application->is_primary_candidate);
        $this->assertSame('Approved after final administrative review.', $application->decision_remarks);
        $this->assertNotNull($application->adopted_at);
        $this->assertNotNull($application->queue_closed_at);
        $this->assertTrue($application->adopted_at->equalTo($application->queue_closed_at));
        $this->assertSame(AvailabilityStatus::Adopted, $pet->refresh()->availability_status);
        $this->assertCount(3, $application->postAdoptionLogs()->get());
        Mail::assertQueued(
            StatusUpdateMail::class,
            fn (StatusUpdateMail $mail): bool => $mail->hasTo($adopter->email)
                && $mail->event === 'application_approved'
                && $mail->queue === 'emails'
        );
    }
}
