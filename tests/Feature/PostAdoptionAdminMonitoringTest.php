<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\ResolutionOutcome;
use App\Enums\Role;
use App\Mail\CheckInReminderMail;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PostAdoptionAdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_queue_displays_real_survey_data_and_is_not_available_to_adopters(): void
    {
        [$adopter, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Volunteer, 'staff-queue');

        PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => now()->toDateString(),
            'submitted_date' => now(),
            'survey_data' => [
                'pet_current_status' => 'Fair',
                'behavioral_observations' => 'Settling into the new home.',
                'eating_habits' => 'Eating twice each day.',
                'living_conditions' => 'Secure indoor home.',
                'vet_visit_details' => 'Routine visit completed.',
                'concerns' => 'Mild separation anxiety.',
            ],
            'pet_current_status' => 'Fair',
            'behavioral_observations' => 'Settling into the new home.',
            'eating_habits' => 'Eating twice each day.',
            'living_conditions' => 'Secure indoor home.',
            'vet_visit_details' => 'Routine visit completed.',
            'concerns' => 'Mild separation anxiety.',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee($adopter->full_name)
            ->assertSee('Settling into the new home.')
            ->assertSee('Mild separation anxiety.');

        $this->actingAs($adopter)
            ->get(route('admin.monitoring.index'))
            ->assertRedirect(route('access-denied'));
    }

    public function test_monitoring_metadata_timestamps_are_presented_in_manila_with_safe_legacy_fallbacks(): void
    {
        [, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Administrator, 'staff-timezone');
        $storedTimestamp = '2026-08-24T23:30:00+00:00';

        PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-25',
            'submitted_date' => '2026-08-24 23:30:00',
            'survey_data' => [
                '_verification' => [
                    'challenge_issued_at' => $storedTimestamp,
                    'signed_at' => 'invalid-legacy-timestamp',
                ],
            ],
            'is_flagged' => true,
            'flag_reasons' => [[
                'code' => 'welfare_concern',
                'message' => 'A staff review is required.',
                'flagged_at' => $storedTimestamp,
            ]],
        ]);

        $this->actingAs($staff)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee('Aug 25, 2026 7:30 AM')
            ->assertSee('Not available')
            ->assertDontSee($storedTimestamp)
            ->assertDontSee('invalid-legacy-timestamp');

        $this->get(route('admin.monitoring.flagged'))
            ->assertOk()
            ->assertSee('Recorded Aug 25, 2026 7:30 AM')
            ->assertDontSee($storedTimestamp);
    }

    public function test_private_welfare_photo_is_streamed_only_to_staff(): void
    {
        [$adopter, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Administrator, 'staff-photo');
        Storage::fake('local');
        config()->set('post_adoption.photo_disk', 'local');

        $path = 'post-adoption-photos/'.$application->id.'/verified.jpg';
        Storage::disk('local')->put($path, 'verified-photo-bytes');
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => now()->toDateString(),
            'photo_path' => $path,
        ]);

        $response = $this->actingAs($staff)->get(route('admin.monitoring.photo', $log));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($adopter)
            ->get(route('admin.monitoring.photo', $log))
            ->assertRedirect(route('access-denied'));

        $staff->update(['is_active' => false]);
        $this->actingAs($staff)
            ->get(route('admin.monitoring.photo', $log))
            ->assertRedirect(route('access-denied'));
    }

    public function test_private_welfare_video_is_streamed_only_to_active_staff_and_legacy_photo_still_works(): void
    {
        [$adopter, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Administrator, 'staff-video');
        Storage::fake('local');
        config()->set('post_adoption.video_disk', 'local');
        config()->set('post_adoption.photo_disk', 'local');

        $videoPath = 'post-adoption-videos/'.$application->id.'/verified.webm';
        $photoPath = 'post-adoption-photos/'.$application->id.'/legacy.jpg';
        Storage::disk('local')->put($videoPath, "\x1A\x45\xDF\xA3verified-video-bytes");
        Storage::disk('local')->put($photoPath, 'legacy-photo-bytes');
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => now()->toDateString(),
            'video_path' => $videoPath,
            'video_sha256' => hash('sha256', "\x1A\x45\xDF\xA3verified-video-bytes"),
            'video_mime_type' => 'video/webm',
            'video_duration_ms' => 3000,
            'photo_path' => $photoPath,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.monitoring.video', $log))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/webm')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('admin.monitoring.photo', $log))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($adopter)
            ->get(route('admin.monitoring.video', $log))
            ->assertRedirect(route('access-denied'));

        $staff->update(['is_active' => false]);
        $this->actingAs($staff)
            ->get(route('admin.monitoring.video', $log))
            ->assertRedirect(route('access-denied'));
    }

    public function test_manual_flagging_and_resolution_require_notes_and_are_audited(): void
    {
        [, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Administrator, 'staff-review');
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeWeeks,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->actingAs($staff)
            ->post(route('admin.monitoring.flag', $log), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertFalse($log->refresh()->is_flagged);

        $this->post(route('admin.monitoring.flag', $log), [
            'reason' => 'Adopter could not be reached after a welfare concern.',
        ])->assertSessionHas('toast.type', 'success');

        $log->refresh();
        $this->assertTrue($log->is_flagged);
        $this->assertSame('manual_staff_flag', $log->flag_reasons[0]['code']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Post-Adoption Log Manually Flagged',
            'entity_id' => $log->id,
        ]);

        $this->post(route('admin.monitoring.resolve', $log), ['resolution_outcome' => 'resolved', 'resolution_note' => ''])
            ->assertSessionHasErrors('resolution_note');
        $this->assertNull($log->refresh()->resolved_at);

        $this->post(route('admin.monitoring.resolve', $log), [
            'resolution_outcome' => 'resolved',
            'resolution_note' => 'A home visit confirmed that the pet is safe and receiving veterinary care.',
        ])->assertSessionHas('toast.type', 'success');

        $log->refresh();
        $this->assertNotNull($log->resolved_at);
        $this->assertSame($staff->id, $log->resolved_by_user_id);
        $this->assertSame(ResolutionOutcome::Resolved, $log->resolution_outcome);
        $this->assertTrue($log->is_flagged, 'Historical flag state must be retained after resolution.');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Post-Adoption Flag Resolved',
            'entity_id' => $log->id,
        ]);
    }

    public function test_outcomes_are_validated_and_follow_up_remains_in_the_staff_queue(): void
    {
        [, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Administrator, 'outcomes');
        $log = $this->flaggedLog($application);
        Mail::fake();

        $this->actingAs($staff)->post(route('admin.monitoring.resolve', $log), [
            'resolution_outcome' => 'invented', 'resolution_note' => 'Test note',
        ])->assertSessionHasErrors('resolution_outcome');
        $this->assertNull($log->fresh()->resolved_at);

        $sensitiveNote = 'Private welfare observation: unique-secret-example';
        $this->post(route('admin.monitoring.resolve', $log), [
            'resolution_outcome' => 'follow_up_required', 'resolution_note' => $sensitiveNote,
        ])->assertSessionHas('toast.type', 'success');

        $this->assertSame(ResolutionOutcome::FollowUpRequired, $log->fresh()->resolution_outcome);
        $this->assertNull($log->fresh()->resolved_at);
        $this->get(route('admin.monitoring.flagged'))->assertOk()
            ->assertSee('Follow-up Required')->assertSee($sensitiveNote);
        $this->get(route('admin.monitoring.index'))->assertOk()->assertSee('Follow-up Required');
        $this->actingAs($application->user)->get(route('monitoring.my-checkins'))
            ->assertOk()->assertDontSee($sensitiveNote);
        $this->actingAs($staff);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Post-Adoption Follow-Up Required', 'entity_id' => $log->id]);
        $this->assertDatabaseHas('handover_notifications', ['kind' => 'welfare_resolution_staff_alert', 'entity_id' => $log->id]);
        $this->assertDatabaseMissing('handover_notifications', ['body' => $sensitiveNote]);
        Mail::assertQueued(TransactionalMail::class, function (TransactionalMail $mail) use ($sensitiveNote): bool {
            return ! str_contains(implode(' ', $mail->lines).' '.$mail->heading, $sensitiveNote);
        });

        $this->post(route('admin.monitoring.resolve', $log), [
            'resolution_outcome' => 'veterinary_attention',
            'resolution_note' => 'Recommended a veterinary evaluation; no visit has been confirmed.',
        ])->assertSessionHas('toast.type', 'success');
        $this->assertSame(ResolutionOutcome::VeterinaryAttention, $log->fresh()->resolution_outcome);
        $this->assertNotNull($log->fresh()->resolved_at);
        $this->get(route('admin.monitoring.index'))->assertOk()->assertSee('Veterinary Attention Recommended');
    }

    public function test_return_recommendation_does_not_change_pet_status_and_legacy_resolution_renders(): void
    {
        [, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Administrator, 'recommend-return');
        $log = $this->flaggedLog($application);
        Mail::fake();

        $this->actingAs($staff)->post(route('admin.monitoring.resolve', $log), [
            'resolution_outcome' => 'return_recommended',
            'resolution_note' => 'Recommend discussing a shelter return; no transfer has occurred.',
        ])->assertSessionHas('toast.type', 'success');
        $this->assertSame(AvailabilityStatus::Adopted, $application->pet->fresh()->availability_status);
        $this->assertSame(ResolutionOutcome::ReturnRecommended, $log->fresh()->resolution_outcome);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Return to Shelter Recommended', 'entity_id' => $log->id]);

        $log->update(['resolution_outcome' => null]);
        $this->assertSame('Resolved', $log->fresh()->resolution_outcome_label);
        $this->get(route('admin.monitoring.index'))->assertOk()->assertSee('Resolved');
    }

    public function test_physical_return_requires_complete_fields_and_confirmation(): void
    {
        [, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Administrator, 'return-validation');
        $log = $this->flaggedLog($application);
        $base = ['resolution_outcome' => 'pet_returned', 'resolution_note' => 'Physical return confirmed.'];
        $complete = $base + [
            'return_date' => today('Asia/Manila')->toDateString(),
            'return_reason' => 'Adopter could no longer provide care.',
            'return_condition' => 'Stable on arrival.',
            'return_handled_by_user_id' => $staff->id,
            'confirm_return' => '1',
        ];

        foreach (['return_date', 'return_reason', 'return_condition', 'return_handled_by_user_id', 'confirm_return'] as $field) {
            $input = $complete;
            unset($input[$field]);
            $this->actingAs($staff)->post(route('admin.monitoring.resolve', $log), $input)
                ->assertSessionHasErrors($field);
            $this->assertSame(AvailabilityStatus::Adopted, $application->pet->fresh()->availability_status);
        }

        $input = $complete;
        $input['return_handled_by_user_id'] = $this->user(Role::Adopter, 'invalid-handler')->id;
        $this->post(route('admin.monitoring.resolve', $log), $input)
            ->assertSessionHasErrors('return_handled_by_user_id');
    }

    public function test_physical_return_is_admin_only_preserves_adoption_history_and_prevents_duplicate(): void
    {
        [$adopter, $application] = $this->approvedApplication();
        $admin = $this->user(Role::Administrator, 'return-admin');
        $volunteer = $this->user(Role::Volunteer, 'return-volunteer');
        $log = $this->flaggedLog($application);
        Mail::fake();
        $input = $this->returnInput($admin);

        $this->actingAs($volunteer)->post(route('admin.monitoring.resolve', $log), $input)
            ->assertRedirect(route('access-denied'));
        $this->assertSame(AvailabilityStatus::Adopted, $application->pet->fresh()->availability_status);

        $this->actingAs($admin)->post(route('admin.monitoring.resolve', $log), $input)
            ->assertSessionHas('toast.type', 'success');
        $this->assertSame(AvailabilityStatus::Returned, $application->pet->fresh()->availability_status);
        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);
        $this->assertSame($adopter->id, $application->fresh()->user_id);
        $this->assertSame('received', $application->handover->fresh()->adopter_outcome);
        $this->assertSame('Pet returned to shelter', $application->handover->fresh()->history[0]['label']);
        $this->assertSame(ResolutionOutcome::PetReturned, $log->fresh()->resolution_outcome);
        $this->assertSame($admin->id, $log->fresh()->return_handled_by_user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Pet Returned to Shelter', 'entity_id' => $log->id]);
        $this->get(route('admin.monitoring.index'))->assertOk()->assertSee('Pet Returned to Shelter');
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('activeMonitoring', 0);
        $this->actingAs($adopter)->get(route('monitoring.my-checkins'))
            ->assertOk()->assertSee('Placement ended');
        $this->actingAs($admin);

        $this->post(route('admin.monitoring.resolve', $log), $input)
            ->assertSessionHas('toast.type', 'error');
        $this->post(route('admin.monitoring.flag', $log), ['reason' => 'Reopen returned case'])
            ->assertSessionHasErrors('reason');
        $this->artisan('checkins:send-reminders')->assertSuccessful();
        Mail::assertNotSent(CheckInReminderMail::class);
        $this->post(route('admin.monitoring.reminder', $log))
            ->assertSessionHas('toast.type', 'error');
        $this->assertSame(1, DB::table('post_adoption_logs')->where('resolution_outcome', 'pet_returned')->count());
        $this->assertSame(AvailabilityStatus::Returned, $application->pet->fresh()->availability_status);
    }

    public function test_return_transaction_rolls_back_if_handover_history_cannot_be_saved(): void
    {
        [, $application] = $this->approvedApplication();
        $admin = $this->user(Role::Administrator, 'return-rollback');
        $log = $this->flaggedLog($application);
        Handover::saving(function (): void {
            throw new RuntimeException('History storage failed.');
        });

        $this->actingAs($admin)->post(route('admin.monitoring.resolve', $log), $this->returnInput($admin))
            ->assertStatus(500);
        $this->assertSame(AvailabilityStatus::Adopted, $application->pet->fresh()->availability_status);
        $this->assertNull($log->fresh()->resolution_outcome);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Pet Returned to Shelter', 'entity_id' => $log->id]);
    }

    public function test_same_placement_cannot_be_returned_twice_even_if_pet_status_is_manually_reset(): void
    {
        [, $application] = $this->approvedApplication();
        $admin = $this->user(Role::Administrator, 'duplicate-return');
        $firstLog = $this->flaggedLog($application);
        Mail::fake();

        $this->actingAs($admin)->post(route('admin.monitoring.resolve', $firstLog), $this->returnInput($admin))
            ->assertSessionHas('toast.type', 'success');
        $application->pet->update(['availability_status' => AvailabilityStatus::Adopted]);
        $secondLog = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeWeeks,
            'scheduled_date' => now()->toDateString(),
            'is_flagged' => true,
        ]);

        $this->post(route('admin.monitoring.resolve', $secondLog), $this->returnInput($admin))
            ->assertSessionHasErrors('resolution_outcome');
        $this->assertNull($secondLog->fresh()->resolution_outcome);
        $this->assertSame(1, DB::table('post_adoption_logs')->where('resolution_outcome', 'pet_returned')->count());
    }

    public function test_second_successful_manual_reminder_flags_the_incomplete_log(): void
    {
        [, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Volunteer, 'staff-reminder');
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeMonths,
            'scheduled_date' => now()->subDay()->toDateString(),
            'reminders_sent' => 1,
            'last_reminder_sent_at' => now()->subDay(),
            'is_flagged' => true,
            'flag_reasons' => [[
                'code' => 'manual_staff_flag',
                'message' => 'Staff requested an early welfare review.',
            ]],
        ]);
        Mail::fake();

        $this->actingAs($staff)
            ->post(route('admin.monitoring.reminder', $log), [
                'custom_message' => 'Please submit your overdue check-in.',
            ])
            ->assertSessionHas('toast.type', 'success');

        Mail::assertSent(CheckInReminderMail::class, 1);
        $log->refresh();
        $this->assertSame(2, $log->reminders_sent);
        $this->assertTrue($log->is_flagged);
        $this->assertSame(
            ['manual_staff_flag', 'missed_check_in'],
            collect($log->flag_reasons)->pluck('code')->all()
        );

        $this->post(route('admin.monitoring.reminder', $log))
            ->assertSessionHas('toast', fn (array $toast): bool => $toast === [
                'type' => 'error',
                'message' => 'This check-in has already received the maximum of two reminders.',
            ]);
        Mail::assertSent(CheckInReminderMail::class, 1);
        $this->assertSame(2, $log->refresh()->reminders_sent);
    }

    public function test_failed_manual_reminder_is_not_counted_or_flagged(): void
    {
        [, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Volunteer, 'staff-mail-failure');
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => now()->subDay()->toDateString(),
            'reminders_sent' => 1,
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP credentials are invalid.'));

        $this->actingAs($staff)
            ->post(route('admin.monitoring.reminder', $log))
            ->assertSessionHas('toast.type', 'error');

        $log->refresh();
        $this->assertSame(1, $log->reminders_sent);
        $this->assertFalse($log->is_flagged);
        $this->assertNull($log->last_reminder_sent_at);
    }

    public function test_dashboard_uses_live_monitoring_and_flag_counts(): void
    {
        [, $application] = $this->approvedApplication();
        $staff = $this->user(Role::Administrator, 'staff-dashboard');
        PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => now()->subDay()->toDateString(),
            'is_flagged' => true,
            'flag_reasons' => [[
                'code' => 'welfare_concern',
                'message' => 'Requires a welfare follow-up.',
            ]],
        ]);

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('adoptedThisMonth', 1)
            ->assertViewHas('activeMonitoring', 1)
            ->assertViewHas('flaggedMonitoring', 1)
            ->assertSee('has an unresolved flag');
    }

    /** @return array{User, AdoptionApplication} */
    private function approvedApplication(): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $adopter = $this->user(Role::Adopter, 'adopter-'.$suffix);
        $pet = Pet::create([
            'name' => 'Monitoring Pet '.$suffix,
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Adopted->value,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved->value,
            'queue_closed_at' => now(),
        ]);

        Handover::create([
            'code' => 'HV-TEST-'.$application->id,
            'application_id' => $application->id,
            'pet_id' => $pet->id,
            'user_id' => $adopter->id,
            'released_at' => now(),
            'adopter_outcome' => 'received',
            'received_at' => now(),
        ]);

        return [$adopter, $application];
    }

    private function user(Role $role, string $key): User
    {
        return User::create([
            'first_name' => ucfirst($role->value),
            'last_name' => 'Monitoring Tester',
            'email' => $key.'-'.uniqid().'@example.test',
            'password' => 'password',
            'role' => $role->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function flaggedLog(AdoptionApplication $application): PostAdoptionLog
    {
        return PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => now()->toDateString(),
            'is_flagged' => true,
            'flag_reasons' => [['code' => 'welfare_concern', 'message' => 'Staff review required.']],
        ]);
    }

    /** @return array<string, mixed> */
    private function returnInput(User $staff): array
    {
        return [
            'resolution_outcome' => 'pet_returned',
            'resolution_note' => 'Physical return confirmed by shelter staff.',
            'return_date' => today('Asia/Manila')->toDateString(),
            'return_reason' => 'Adopter could no longer provide care.',
            'return_condition' => 'Stable on arrival.',
            'return_handled_by_user_id' => $staff->id,
            'confirm_return' => '1',
        ];
    }
}
