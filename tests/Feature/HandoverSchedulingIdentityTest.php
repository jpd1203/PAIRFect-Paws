<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\IdentityVerification;
use App\Models\Pet;
use App\Models\User;
use App\Services\HandoverReminderService;
use App\Services\IdentityVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparesVerifiedHandovers;
use Tests\TestCase;

class HandoverSchedulingIdentityTest extends TestCase
{
    use PreparesVerifiedHandovers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mail.staff_alert_address' => null]);
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(0, 0));
    }

    private function placement(): array
    {
        $owner = User::create(['first_name' => 'Owner', 'last_name' => 'QA', 'email' => 'owner@example.test', 'password' => 'password', 'role' => Role::Adopter->value, 'email_verified_at' => now(), 'is_active' => true]);
        $staff = User::create(['first_name' => 'Staff', 'last_name' => 'QA', 'email' => 'staff@example.test', 'password' => 'password', 'role' => Role::Administrator->value, 'email_verified_at' => now(), 'is_active' => true]);
        $pet = Pet::create(['name' => 'Test Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => ApplicationStatus::Approved->value, 'document_verification_status' => DocumentVerificationStatus::Verified->value, 'adopted_at' => now()]);
        $handover = Handover::create(['code' => 'HV-QA', 'application_id' => $application->id, 'pet_id' => $pet->id, 'user_id' => $owner->id, 'adopter_name' => $owner->full_name, 'approved_at' => now(), 'history' => []]);

        return [$owner, $staff, $application, $handover];
    }

    private function slot(int $days = 2): array
    {
        return ['date' => now('Asia/Manila')->addDays($days)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00'];
    }

    private function propose(User $staff, Handover $handover, string $method = 'delivery'): void
    {
        $this->actingAs($staff)->post(route('admin.handover.schedule', $handover), [
            'method' => $method, 'schedule_version' => $handover->refresh()->schedule_version, ...$this->slot(),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $handover->refresh();
    }

    private function confirm(User $owner, Handover $handover): void
    {
        $this->actingAs($owner)->post(route('adopter.handover.schedule.confirm', $handover), ['schedule_version' => $handover->refresh()->schedule_version])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $handover->refresh();
    }

    private function release(User $staff, string $method = 'delivery'): array
    {
        return ['release_method' => $method, 'release_date' => today()->toDateString(), 'release_time' => '10:00', 'staff_id' => $staff->id,
            'courier_provider' => 'pet_to_go_express', 'tracking_number' => 'PTG-QA', 'tracking_url' => 'https://example.test/track/qa'];
    }

    public function test_identity_is_recorded_by_staff_and_cannot_be_spoofed_or_recorded_by_adopter(): void
    {
        [$owner, $staff, $application] = $this->placement();
        $application->update(['status' => ApplicationStatus::UnderReview, 'is_primary_candidate' => true]);
        $data = ['stage' => 'interview', 'verification_method' => 'video_interview', 'status' => 'verified', ...array_fill_keys(IdentityVerification::CHECKS, 1), 'verified_by_user_id' => $owner->id, 'verified_at' => '1990-01-01'];
        $this->actingAs($staff)->get(route('admin.applications.identity', $application))->assertOk()->assertSee('no biometrics');
        $this->post(route('admin.applications.identity.store', $application), $data)->assertSessionHasNoErrors();
        $event = $application->identityVerifications()->sole();
        $this->assertSame($staff->id, $event->verified_by_user_id);
        $this->assertTrue($event->verified_at->equalTo(now()));
        $this->assertArrayNotHasKey('document_fingerprint', $event->toArray());
        $this->actingAs($owner)->post(route('admin.applications.identity.store', $application), $data)->assertRedirect(route('access-denied'));
        $this->assertDatabaseCount('identity_verifications', 1);
    }

    #[DataProvider('identityStates')]
    public function test_approval_requires_the_current_identity_event_and_all_checks(?string $state): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $handover->delete();
        $application->update(['status' => ApplicationStatus::UnderReview, 'is_primary_candidate' => true]);
        if ($state) {
            app(IdentityVerificationService::class)->record($application, $staff, [
                'stage' => 'interview', 'verification_method' => 'in_person', 'status' => $state,
                'discrepancy_note' => $state !== 'verified' ? 'Human review required.' : null,
                ...array_fill_keys(IdentityVerification::CHECKS, $state === 'verified'),
            ]);
        }
        $response = $this->actingAs($staff)->post(route('admin.applications.decide', $application), ['decision' => 'Approved']);
        if ($state === 'verified') {
            $response->assertSessionHasNoErrors();
            $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);
        } else {
            $response->assertSessionHasErrors('identity');
            $this->assertSame(ApplicationStatus::UnderReview, $application->fresh()->status);
        }
    }

    public static function identityStates(): array
    {
        return [[null], ['needs_review'], ['failed'], ['verified']];
    }

    public function test_missing_check_rejects_verified_and_replacement_document_invalidates_old_verification(): void
    {
        [$owner, $staff, $application] = $this->placement();
        $data = ['stage' => 'interview', 'verification_method' => 'in_person', 'status' => 'verified', ...array_fill_keys(IdentityVerification::CHECKS, 1), 'name_matches_application' => 0];
        $this->actingAs($staff)->post(route('admin.applications.identity.store', $application), $data)->assertSessionHasErrors('identity');
        $this->assertDatabaseCount('identity_verifications', 0);
        $this->verifiedIdentity($application, $staff);
        $application->update(['document_path' => 'adoption-documents/new-private-id.jpg']);
        $this->assertFalse(app(IdentityVerificationService::class)->isVerified($application));
    }

    public function test_later_failed_identity_supersedes_verified_and_document_gate_remains(): void
    {
        [$owner, $staff, $application] = $this->placement();
        $application->update(['status' => ApplicationStatus::UnderReview, 'is_primary_candidate' => true]);
        $this->verifiedIdentity($application, $staff);
        app(IdentityVerificationService::class)->record($application, $staff, ['stage' => 'interview', 'verification_method' => 'in_person', 'status' => 'failed', 'discrepancy_note' => 'Discrepancy observed.']);
        $this->actingAs($staff)->post(route('admin.applications.decide', $application), ['decision' => 'Approved'])->assertSessionHasErrors('identity');
        $this->verifiedIdentity($application, $staff);
        $application->update(['document_verification_status' => DocumentVerificationStatus::NeedsResubmission]);
        $this->post(route('admin.applications.decide', $application), ['decision' => 'Approved'])->assertSessionHasErrors('decision');
    }

    public function test_proposal_confirmation_are_separate_from_actual_release_and_owner_protected(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover);
        $this->assertSame('02:00', $handover->scheduled_start_at->utc()->format('H:i'));
        $this->assertNull($handover->released_at);
        $this->assertNull($handover->release_date);
        $this->actingAs($owner)->get(route('adopter.handover.status', $handover))->assertOk()->assertSee('Confirm Schedule')->assertDontSee('Release Details');
        $other = User::create(['first_name' => 'Other', 'last_name' => 'QA', 'email' => 'other@example.test', 'password' => 'password', 'role' => Role::Adopter->value, 'email_verified_at' => now(), 'is_active' => true]);
        $this->actingAs($other)->post(route('adopter.handover.schedule.confirm', $handover), ['schedule_version' => $handover->schedule_version])->assertForbidden();
        $this->actingAs($other)->post(route('adopter.handover.reschedule', $handover), ['schedule_version' => $handover->schedule_version, 'options' => [$this->slot()]])->assertForbidden();
        $this->confirm($owner, $handover);
        $this->assertSame($owner->id, $handover->schedule_confirmed_by_user_id);
    }

    public function test_past_reversed_and_stale_proposals_are_rejected(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        foreach ([['date' => '2026-01-01', 'start_time' => '10:00', 'end_time' => '11:00'], [...$this->slot(), 'end_time' => '09:00']] as $slot) {
            $this->actingAs($staff)->post(route('admin.handover.schedule', $handover), ['method' => 'pickup', 'schedule_version' => 0, ...$slot])->assertSessionHasErrors('options');
        }
        $this->propose($staff, $handover);
        $this->actingAs($owner)->post(route('adopter.handover.schedule.confirm', $handover), ['schedule_version' => 0])->assertSessionHasErrors('schedule');
    }

    #[DataProvider('optionCounts')]
    public function test_reschedule_accepts_one_to_three_windows_and_keeps_confirmed_schedule_active(int $count): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover);
        $this->confirm($owner, $handover);
        $previous = $handover->scheduled_start_at->toIso8601String();
        $options = array_map(fn ($index) => $this->slot($index + 3), range(0, $count - 1));
        $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), ['schedule_version' => $handover->schedule_version, 'options' => $options])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $handover->fresh()->schedule_status);
        $this->assertSame($previous, $handover->fresh()->scheduled_start_at->toIso8601String());
        $this->actingAs($staff)->post(route('admin.handover.reschedule.review', $handover), ['schedule_version' => $handover->refresh()->schedule_version, 'decision' => 'approved', 'option_index' => $count - 1])->assertSessionHasNoErrors();
        $this->assertSame('approved', $handover->fresh()->reschedule_status);
        $this->assertNotSame($previous, $handover->fresh()->scheduled_start_at->toIso8601String());
        $this->assertSame('confirmed', $handover->fresh()->schedule_status);
    }

    public static function optionCounts(): array
    {
        return [[1], [2], [3]];
    }

    public function test_invalid_reschedule_windows_and_duplicate_pending_requests_are_rejected(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover);
        foreach ([array_fill(0, 4, $this->slot()), [$this->slot(), $this->slot()], [['date' => '2026-10-15']], [$this->slot(-1)], [['date' => null, 'start_time' => null, 'end_time' => null]]] as $options) {
            $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), ['schedule_version' => $handover->schedule_version, 'options' => $options])->assertSessionHasErrors('options');
        }
        $request = ['schedule_version' => $handover->schedule_version, 'options' => [$this->slot(3), ['date' => null, 'start_time' => null, 'end_time' => null]]];
        $this->post(route('adopter.handover.reschedule', $handover), $request)->assertSessionHasNoErrors();
        $this->post(route('adopter.handover.reschedule', $handover), $request)->assertSessionHasErrors('schedule');
        $this->assertCount(1, $handover->fresh()->reschedule_options);
    }

    #[DataProvider('confirmedStates')]
    public function test_decline_preserves_confirmed_schedule_but_not_unconfirmed_agreement(bool $confirmed): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover);
        if ($confirmed) {
            $this->confirm($owner, $handover);
        }
        $original = $handover->scheduled_start_at;
        $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), ['schedule_version' => $handover->schedule_version, 'options' => [$this->slot(3)]])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('admin.handover.reschedule.review', $handover), ['schedule_version' => $handover->refresh()->schedule_version, 'decision' => 'declined'])->assertSessionHasNoErrors();
        $this->assertSame($confirmed ? 'confirmed' : 'unscheduled', $handover->fresh()->schedule_status);
        $this->assertEquals($confirmed ? $original : null, $handover->fresh()->scheduled_start_at);
    }

    public static function confirmedStates(): array
    {
        return [[true], [false]];
    }

    public function test_delivery_release_requires_schedule_and_interview_identity_but_no_courier_identity_work(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->release($staff))->assertSessionHasErrors('schedule');
        $this->propose($staff, $handover);
        $this->confirm($owner, $handover);
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->release($staff))->assertSessionHasErrors('identity');
        $this->verifiedIdentity($application, $staff);
        $this->post(route('admin.handover.release', $handover), $this->release($staff))->assertSessionHasNoErrors()->assertSessionHas('toast.type', 'success');
        $this->assertNotNull($handover->fresh()->released_at);
        $this->assertDatabaseCount('identity_verifications', 1);
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public function test_pickup_requires_final_in_person_recheck_and_reopen_resets_gates_and_markers(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover, 'pickup');
        $this->confirm($owner, $handover);
        $this->verifiedIdentity($application, $staff);
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->release($staff, 'pickup'))->assertSessionHasErrors('identity');
        $this->verifiedIdentity($application, $staff, 'pickup_handover');
        $this->post(route('admin.handover.release', $handover), $this->release($staff, 'pickup'))->assertSessionHasNoErrors();
        $this->post(route('admin.handover.reopen', $handover), ['reason' => 'Arrange another attempt'])->assertSessionHasNoErrors();
        $handover->refresh();
        $this->assertNull($handover->scheduled_start_at);
        $this->assertSame('unscheduled', $handover->schedule_status);
        $this->assertFalse(app(IdentityVerificationService::class)->isVerified($application, 'pickup_handover', $handover->reopen_count));
    }

    public function test_pickup_identity_recheck_remains_valid_until_the_agreed_window_changes(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->prepareHandover($handover, $staff, 'pickup');
        $application->refresh();
        $identity = app(IdentityVerificationService::class);
        $this->assertTrue($identity->isVerified($application, 'pickup_handover', $handover->reopen_count));
        $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), ['schedule_version' => $handover->schedule_version, 'options' => [$this->slot(4)]])->assertSessionHasNoErrors();
        $this->assertTrue($identity->isVerified($application, 'pickup_handover', $handover->reopen_count));
        $this->actingAs($staff)->post(route('admin.handover.reschedule.review', $handover), ['schedule_version' => $handover->refresh()->schedule_version, 'decision' => 'approved', 'option_index' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($identity->isVerified($application, 'pickup_handover', $handover->reopen_count));
        $this->post(route('admin.handover.release', $handover), $this->release($staff, 'pickup'))->assertSessionHasErrors('identity');
        $this->actingAs($owner)->get(route('adopter.handover.status', $handover))->assertOk()->assertSee('Schedule confirmed')->assertDontSee('waiting on a handover schedule');
    }

    public function test_delivery_reminders_and_escalation_are_schedule_based_and_idempotent(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->prepareHandover($handover, $staff);
        $service = app(HandoverReminderService::class);
        $this->travelTo($handover->scheduled_start_at->copy()->subHours(24));
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $this->travelTo($handover->scheduled_start_at->copy()->subHours(2));
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $handover->update(['released_at' => now(), 'release_method' => 'delivery']);
        $this->travelTo($handover->scheduled_end_at->copy()->addHour());
        $this->artisan('handovers:process-schedule-reminders')->assertSuccessful();
        $this->assertSame(0, $service->process());
        $this->assertNotNull($handover->fresh()->receipt_reminder_sent_at);
        $this->travelTo($handover->scheduled_end_at->copy()->addHours(3));
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $this->assertNotNull($handover->fresh()->follow_up_flagged_at);
        $this->assertSame(1, $staff->inAppNotifications()->where('kind', 'handover_delivery_follow_up')->count());
        $this->assertNull($handover->fresh()->adopter_outcome);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        Mail::assertQueuedCount(4);
    }

    public function test_missed_pickup_notifies_once_without_release_or_monitoring(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->prepareHandover($handover, $staff, 'pickup');
        $this->travelTo($handover->scheduled_end_at->copy()->addHour());
        $service = app(HandoverReminderService::class);
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $this->assertNull($handover->fresh()->released_at);
        $this->assertNotNull($handover->fresh()->missed_pickup_notified_at);
        $this->assertSame(1, $staff->inAppNotifications()->where('kind', 'handover_missed_pickup')->count());
        $this->assertDatabaseCount('post_adoption_logs', 0);
        Mail::assertQueuedCount(2);
    }

    public function test_received_and_legacy_unscheduled_handovers_receive_no_automatic_reminders(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->assertSame(0, app(HandoverReminderService::class)->process());
        $this->actingAs($owner)->get(route('adopter.handover.status', $handover))->assertOk();
        $this->prepareHandover($handover, $staff);
        $handover->update(['released_at' => now(), 'adopter_outcome' => 'received', 'received_at' => now()]);
        $this->travelTo(now()->addDays(3));
        $this->assertSame(0, app(HandoverReminderService::class)->process());
        Mail::assertNothingQueued();
    }

    public function test_rescheduling_resets_old_markers_and_only_new_schedule_can_send(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->prepareHandover($handover, $staff);
        $handover->update(['schedule_24h_reminder_sent_at' => now(), 'receipt_reminder_sent_at' => now(), 'follow_up_flagged_at' => now()]);
        $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), ['schedule_version' => $handover->schedule_version, 'options' => [$this->slot(4)]])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('admin.handover.reschedule.review', $handover), ['schedule_version' => $handover->refresh()->schedule_version, 'decision' => 'approved', 'option_index' => 0])->assertSessionHasNoErrors();
        $this->assertNull($handover->fresh()->receipt_reminder_sent_at);
        $this->assertNull($handover->fresh()->follow_up_flagged_at);
        $this->assertNull($handover->fresh()->schedule_24h_reminder_sent_at);
        $this->assertSame(0, app(HandoverReminderService::class)->process());
    }
}
