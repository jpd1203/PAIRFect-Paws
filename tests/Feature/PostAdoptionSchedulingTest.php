<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Mail\CheckInReminderMail;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Services\FlagEvaluationService;
use App\Services\PostAdoptionScheduleService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PostAdoptionSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_reconciliation_migration_preserves_legacy_data_and_normalizes_the_schema(): void
    {
        [, $application] = $this->approvedApplication('2026-01-01 00:00:00');
        $migration = require database_path('migrations/2026_08_19_000000_reconcile_post_adoption_logs_table.php');

        $migration->down();

        DB::table('post_adoption_logs')->insert([
            'adoption_application_id' => $application->id,
            'milestone' => 'ThreeDays',
            'scheduled_date' => '2026-01-04',
            'submitted_date' => '2026-01-04 09:30:00',
            'pet_current_status' => 'Fair',
            'behavioral_observations' => 'Initially anxious but improving.',
            'living_conditions' => 'Indoor home with a secure yard.',
            'eating_habits' => 'Eating twice daily.',
            'vet_visit_details' => null,
            'concerns' => 'Minor adjustment concerns.',
            'photo_path' => 'welfare-photos/legacy.jpg',
            'flagged_for_review' => true,
            'reminders_sent' => 2,
            'resolved_at' => null,
            'resolved_by_user_id' => null,
            'resolution_note' => null,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasColumns('post_adoption_logs', [
            'application_id',
            'survey_data',
            'is_flagged',
            'flag_reasons',
            'last_reminder_sent_at',
            'photo_sha256',
            'c2pa_manifest_sha256',
        ]));
        $this->assertTrue(Schema::hasColumn('adoption_applications', 'adopted_at'));
        $this->assertFalse(Schema::hasColumn('post_adoption_logs', 'adoption_application_id'));
        $this->assertFalse(Schema::hasColumn('post_adoption_logs', 'flagged_for_review'));
        $this->assertTrue(Schema::hasIndex(
            'post_adoption_logs',
            'post_adoption_logs_application_milestone_unique'
        ));

        $log = PostAdoptionLog::firstOrFail();
        $this->assertSame($application->id, $log->application_id);
        $this->assertSame(Milestone::ThreeDays, $log->milestone);
        $this->assertTrue($log->is_flagged);
        $this->assertSame('Fair', $log->survey_data['pet_current_status']);
        $this->assertSame('Initially anxious but improving.', $log->survey_data['behavioral_observations']);
        $this->assertSame('welfare-photos/legacy.jpg', $log->photo_path);
        $this->assertSame(2, $log->reminders_sent);
        $this->assertNotNull($application->fresh()->adopted_at);
    }

    public function test_reconciliation_migration_merges_and_can_restore_legacy_duplicate_milestones(): void
    {
        [, $application] = $this->approvedApplication('2026-01-01 00:00:00');
        $migration = require database_path('migrations/2026_08_19_000000_reconcile_post_adoption_logs_table.php');

        $migration->down();

        try {
            DB::table('post_adoption_logs')->insert([
                [
                    'adoption_application_id' => $application->id,
                    'milestone' => 'ThreeDays',
                    'scheduled_date' => '2026-01-04',
                    'submitted_date' => null,
                    'pet_current_status' => 'Poor',
                    'behavioral_observations' => null,
                    'concerns' => 'The adopter reported a welfare concern.',
                    'flagged_for_review' => true,
                    'reminders_sent' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'adoption_application_id' => $application->id,
                    'milestone' => 'ThreeDays',
                    'scheduled_date' => '2026-01-05',
                    'submitted_date' => '2026-01-05 09:30:00',
                    'pet_current_status' => 'Good',
                    'behavioral_observations' => 'The submitted report must remain authoritative.',
                    'concerns' => null,
                    'flagged_for_review' => false,
                    'reminders_sent' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $migration->up();

            $this->assertSame(1, PostAdoptionLog::where('application_id', $application->id)->count());
            $log = PostAdoptionLog::where('application_id', $application->id)->firstOrFail();
            $this->assertSame('2026-01-05 09:30:00', $log->submitted_date->format('Y-m-d H:i:s'));
            $this->assertSame('Good', $log->survey_data['pet_current_status']);
            $this->assertSame(
                'The submitted report must remain authoritative.',
                $log->survey_data['behavioral_observations']
            );
            $this->assertSame(
                'The adopter reported a welfare concern.',
                $log->survey_data['concerns']
            );
            $this->assertTrue($log->is_flagged);
            $this->assertCount(1, $log->survey_data['_merged_duplicate_logs']);

            $migration->down();

            $legacyRows = DB::table('post_adoption_logs')
                ->where('adoption_application_id', $application->id)
                ->orderBy('id')
                ->get();
            $this->assertCount(2, $legacyRows);
            $this->assertSame(['ThreeDays'], $legacyRows->pluck('milestone')->unique()->values()->all());
        } finally {
            $migration->up();
        }
    }

    public function test_schedule_service_creates_exactly_three_idempotent_logs_from_the_approval_date(): void
    {
        [, $application] = $this->approvedApplication('2026-01-31 02:00:00');
        $service = app(PostAdoptionScheduleService::class);

        $firstRun = $service->ensureForApplication($application);
        $secondRun = $service->ensureForApplication($application->fresh());

        $this->assertCount(3, $firstRun);
        $this->assertCount(3, $secondRun);
        $logs = $application->postAdoptionLogs()->get()->keyBy(
            fn (PostAdoptionLog $log): string => $log->milestone->value
        );
        $this->assertCount(3, $logs);
        $this->assertSame('2026-02-03', $logs[Milestone::ThreeDays->value]->scheduled_date->toDateString());
        $this->assertSame('2026-02-21', $logs[Milestone::ThreeWeeks->value]->scheduled_date->toDateString());
        $this->assertSame('2026-04-30', $logs[Milestone::ThreeMonths->value]->scheduled_date->toDateString());
    }

    public function test_daily_command_backfills_missing_logs_and_is_idempotent(): void
    {
        $this->freezeManilaTime('2026-08-19 08:00:00');
        Mail::fake();
        [, $application] = $this->approvedApplication(null);

        $this->artisan('checkins:send-reminders')->assertSuccessful();
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        $logs = $application->postAdoptionLogs()->get()->keyBy(
            fn (PostAdoptionLog $log): string => $log->milestone->value
        );
        $this->assertCount(3, $logs);
        $this->assertSame('2026-08-22', $logs[Milestone::ThreeDays->value]->scheduled_date->toDateString());
        Mail::assertNothingSent();
    }

    public function test_due_log_is_reminded_at_most_once_per_day_and_flagged_after_the_second_success(): void
    {
        $this->freezeManilaTime('2026-08-19 08:00:00');
        Mail::fake();
        [, $application] = $this->approvedApplication(now()->toDateTimeString());
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-19',
        ]);

        $this->artisan('checkins:send-reminders')->assertSuccessful();
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        Mail::assertSent(CheckInReminderMail::class, 1);
        $this->assertSame(1, $log->refresh()->reminders_sent);
        $this->assertFalse($log->is_flagged);
        $this->assertNotNull($log->last_reminder_sent_at);

        $this->freezeManilaTime('2026-08-20 08:00:00');
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        Mail::assertSent(CheckInReminderMail::class, 2);
        $this->assertSame(2, $log->refresh()->reminders_sent);
        $this->assertTrue($log->is_flagged);
        $this->assertSame('missed_check_in', $log->flag_reasons[0]['code']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Post-Adoption Log Flagged (Missed Submission)',
            'entity_name' => 'PostAdoptionLog',
            'entity_id' => $log->id,
        ]);
    }

    public function test_manually_flagged_log_still_receives_two_reminders_and_keeps_both_reasons(): void
    {
        $this->freezeManilaTime('2026-08-19 08:00:00');
        Mail::fake();
        [, $application] = $this->approvedApplication(now()->toDateTimeString());
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-19',
            'is_flagged' => true,
            'flag_reasons' => [[
                'code' => 'manual_staff_flag',
                'message' => 'Staff requested an early welfare review.',
            ]],
        ]);

        $this->artisan('checkins:send-reminders')->assertSuccessful();
        $this->assertSame(1, $log->refresh()->reminders_sent);
        $this->assertSame(['manual_staff_flag'], collect($log->flag_reasons)->pluck('code')->all());

        $this->freezeManilaTime('2026-08-20 08:00:00');
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        $log->refresh();
        $this->assertSame(2, $log->reminders_sent);
        $this->assertSame(
            ['manual_staff_flag', 'missed_check_in'],
            collect($log->flag_reasons)->pluck('code')->all()
        );

        $this->freezeManilaTime('2026-08-21 08:00:00');
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        Mail::assertSent(CheckInReminderMail::class, 2);
        $this->assertSame(2, $log->refresh()->reminders_sent);
    }

    public function test_unverified_adopter_creates_one_staff_visible_delivery_block_and_resumes_after_verification(): void
    {
        $this->freezeManilaTime('2026-08-19 08:00:00');
        Mail::fake();
        config(['mail.staff_alert_address' => 'staff-alerts@example.test']);

        [$adopter, $application] = $this->approvedApplication(now()->toDateTimeString());
        $adopter->forceFill(['email_verified_at' => null])->save();
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-19',
        ]);

        $this->artisan('checkins:send-reminders')->assertSuccessful();
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        $this->freezeManilaTime('2026-08-20 08:00:00');
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        $log->refresh();
        $deliveryBlocks = collect($log->flag_reasons)
            ->where('code', 'reminder_delivery_blocked')
            ->values();

        Mail::assertNotSent(CheckInReminderMail::class);
        Mail::assertQueued(TransactionalMail::class, 1);
        Mail::assertQueued(
            TransactionalMail::class,
            fn (TransactionalMail $mail): bool => $mail->hasTo('staff-alerts@example.test'),
        );
        $this->assertSame(0, $log->reminders_sent);
        $this->assertTrue($log->is_flagged);
        $this->assertNull($log->resolved_at);
        $this->assertCount(1, $deliveryBlocks);
        $this->assertSame('unverified_email', $deliveryBlocks->first()['block_type']);
        $this->assertNotEmpty($deliveryBlocks->first()['staff_alert_requested_at']);

        $adopter->forceFill(['email_verified_at' => now()])->save();
        $this->freezeManilaTime('2026-08-21 08:00:00');
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        $log->refresh();
        $deliveryBlock = collect($log->flag_reasons)
            ->firstWhere('code', 'reminder_delivery_blocked');

        Mail::assertSent(CheckInReminderMail::class, 1);
        Mail::assertQueued(TransactionalMail::class, 1);
        $this->assertSame(1, $log->reminders_sent);
        $this->assertNotNull($log->last_reminder_sent_at);
        $this->assertNotEmpty($deliveryBlock['delivery_resumed_at']);
        $this->assertTrue($log->is_flagged, 'The resolved delivery block is retained as audit history.');
        $this->assertNotNull($log->resolved_at);

        $this->freezeManilaTime('2026-08-22 08:00:00');
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        $log->refresh();
        $this->assertSame(2, $log->reminders_sent);
        $this->assertNull($log->resolved_at, 'The two-reminder welfare flag must reopen the case.');
        $this->assertSame(
            ['reminder_delivery_blocked', 'missed_check_in'],
            collect($log->flag_reasons)->pluck('code')->all(),
        );
        Mail::assertSent(CheckInReminderMail::class, 2);
        Mail::assertQueued(TransactionalMail::class, 2);
    }

    public function test_resuming_delivery_preserves_an_existing_open_flag_reason(): void
    {
        $this->freezeManilaTime('2026-08-19 08:00:00');
        Mail::fake();
        config(['mail.staff_alert_address' => 'staff-alerts@example.test']);

        [$adopter, $application] = $this->approvedApplication(now()->toDateTimeString());
        $adopter->forceFill(['email_verified_at' => null])->save();
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-19',
            'is_flagged' => true,
            'flag_reasons' => [[
                'code' => 'manual_staff_flag',
                'message' => 'Staff requested a welfare review.',
                'flagged_at' => now()->toIso8601String(),
            ]],
        ]);

        $this->artisan('checkins:send-reminders')->assertSuccessful();

        $adopter->forceFill(['email_verified_at' => now()])->save();
        $this->freezeManilaTime('2026-08-20 08:00:00');
        $this->artisan('checkins:send-reminders')->assertSuccessful();

        $log->refresh();

        $this->assertSame(1, $log->reminders_sent);
        $this->assertTrue($log->is_flagged);
        $this->assertNull($log->resolved_at);
        $this->assertSame(
            ['manual_staff_flag', 'reminder_delivery_blocked'],
            collect($log->flag_reasons)->pluck('code')->all(),
        );
        $this->assertNotEmpty(
            collect($log->flag_reasons)
                ->firstWhere('code', 'reminder_delivery_blocked')['delivery_resumed_at'],
        );
    }

    public function test_failed_reminder_delivery_does_not_increment_or_flag_the_log(): void
    {
        $this->freezeManilaTime('2026-08-19 08:00:00');
        [, $application] = $this->approvedApplication(now()->toDateTimeString());
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-19',
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP is unavailable.'));

        $this->artisan('checkins:send-reminders')->assertFailed();

        $this->assertSame(0, $log->refresh()->reminders_sent);
        $this->assertFalse($log->is_flagged);
        $this->assertNull($log->last_reminder_sent_at);
    }

    public function test_daily_command_fails_when_a_reminder_job_cannot_be_queued(): void
    {
        $this->freezeManilaTime('2026-08-19 08:00:00');
        [, $application] = $this->approvedApplication(now()->toDateTimeString());
        PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-19',
        ]);

        $this->mock(Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatch')
                ->once()
                ->andThrow(new RuntimeException('The queue is unavailable.'));
        });

        $this->artisan('checkins:send-reminders')
            ->expectsOutput('Queued 0 reminder delivery job(s); 1 queue attempt(s) failed; backstop-flagged 0 log(s).')
            ->assertFailed();
    }

    public function test_legacy_missed_submission_scan_flags_only_due_incomplete_logs(): void
    {
        $this->freezeManilaTime('2026-08-19 08:00:00');
        [, $application] = $this->approvedApplication(now()->toDateTimeString());

        $due = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-19',
            'reminders_sent' => 2,
        ]);
        $future = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeWeeks,
            'scheduled_date' => '2026-08-20',
            'reminders_sent' => 2,
        ]);
        $submitted = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeMonths,
            'scheduled_date' => '2026-08-18',
            'submitted_date' => now(),
            'reminders_sent' => 2,
        ]);

        $this->assertSame(1, app(FlagEvaluationService::class)->flagMissedSubmissions());
        $this->assertTrue($due->refresh()->is_flagged);
        $this->assertSame('missed_check_in', $due->flag_reasons[0]['code']);
        $this->assertFalse($future->refresh()->is_flagged);
        $this->assertFalse($submitted->refresh()->is_flagged);
    }

    /** @return array{User, AdoptionApplication} */
    private function approvedApplication(?string $queueClosedAt): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $adopter = User::create([
            'first_name' => 'Post-Adoption',
            'last_name' => 'Tester',
            'email' => "monitoring-{$suffix}@example.test",
            'password' => 'password',
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $pet = Pet::create([
            'name' => "Monitoring Pet {$suffix}",
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Adopted->value,
        ]);
        $applicationAttributes = [
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved->value,
        ];

        if ($queueClosedAt !== null) {
            $applicationAttributes['queue_closed_at'] = $queueClosedAt;
        }

        $application = AdoptionApplication::create($applicationAttributes);

        return [$adopter, $application];
    }

    private function freezeManilaTime(string $dateTime): void
    {
        $now = CarbonImmutable::parse($dateTime, PostAdoptionScheduleService::TIMEZONE);
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }
}
