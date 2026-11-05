<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\HandoverEmailOutbox;
use App\Models\Pet;
use App\Services\HandoverMailOutboxService;
use App\Services\HandoverNotificationService;
use App\Services\HandoverReminderService;
use App\Services\HandoverScheduleService;
use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\PreparesVerifiedHandovers;
use Tests\TestCase;

class HandoverReminderReliabilityTest extends TestCase
{
    use BuildsMatchingFixtures;
    use PreparesVerifiedHandovers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mail.default' => 'array', 'mail.staff_alert_address' => null, 'app.display_timezone' => 'Asia/Manila']);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'Asia/Manila')->utc());
    }

    public static function failureEvents(): array
    {
        return [['24h_reminder'], ['delivery_follow_up']];
    }

    #[DataProvider('failureEvents')]
    public function test_queue_failure_is_durable_recoverable_and_not_a_delivery_confirmation(string $kind): void
    {
        [, , $handover] = $this->placement();
        if ($kind === 'delivery_follow_up') {
            $handover->update(['released_at' => now(), 'release_method' => 'delivery', 'receipt_reminder_sent_at' => now()]);
            $this->travelTo($handover->scheduled_end_at->copy()->addHours(3));
        }
        Log::spy();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('PRIVATE https://courier.example.test/secret adopter@example.test'));
        $service = app(HandoverReminderService::class);
        $this->assertSame(1, $service->process());
        $row = HandoverEmailOutbox::where('kind', $kind)->sole();
        $this->assertNull($row->queued_at);
        $this->assertNull($row->transport_accepted_at);
        $this->assertNull($row->enqueue_token);
        $this->assertSame(RuntimeException::class, $row->last_queue_error);
        $this->assertSame(0, $row->delivery_attempts);
        $this->assertSame(0, $service->process());
        $this->assertSame(2, $row->fresh()->queue_attempts);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'Handover email remains pending after queue failure.'
            && $context === ['outbox_id' => $row->id, 'exception' => RuntimeException::class])->twice();

        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertSame(0, $service->process());
        Mail::assertQueuedCount(1);
        $this->assertNotNull($row->fresh()->queued_at);
        $this->assertNull($row->fresh()->transport_accepted_at);
        $this->assertSame(3, $row->fresh()->queue_attempts);
        $this->assertDatabaseCount('handover_email_outbox', 1);
    }

    public function test_event_marker_notice_and_outbox_roll_back_together_without_queueing(): void
    {
        [, , $handover] = $this->placement();
        DB::beginTransaction();
        $this->assertSame(1, app(HandoverReminderService::class)->process());
        $this->assertDatabaseCount('handover_email_outbox', 1);
        Mail::assertNothingQueued();
        DB::rollBack();
        $this->assertNull($handover->fresh()->schedule_24h_reminder_sent_at);
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        Mail::assertNothingQueued();
    }

    public function test_outbox_persistence_failure_does_not_consume_the_reminder_marker(): void
    {
        [, , $handover] = $this->placement();
        Event::listen('eloquent.creating: '.HandoverEmailOutbox::class, fn () => throw new RuntimeException('Synthetic storage failure'));
        try {
            app(HandoverReminderService::class)->process();
            $this->fail('The event must not commit without durable email intent.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic storage failure', $exception->getMessage());
        }
        $this->assertNull($handover->fresh()->schedule_24h_reminder_sent_at);
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        Mail::assertNothingQueued();
    }

    public function test_after_commit_claim_failure_preserves_event_and_does_not_throw_a_false_workflow_failure(): void
    {
        [, , $handover] = $this->placement();
        Event::listen('eloquent.retrieved: '.HandoverEmailOutbox::class, fn () => throw new RuntimeException('Synthetic dispatch claim failure'));
        $this->assertSame(1, app(HandoverReminderService::class)->process());
        Event::forget('eloquent.retrieved: '.HandoverEmailOutbox::class);
        $this->assertNotNull($handover->fresh()->schedule_24h_reminder_sent_at);
        $row = HandoverEmailOutbox::sole();
        $this->assertNull($row->queued_at);
        $this->assertSame(RuntimeException::class, $row->last_queue_error);
        Mail::assertNothingQueued();
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        Mail::assertQueuedCount(1);
    }

    public function test_recovery_inside_a_transaction_defers_queueing_until_commit_and_respects_rollback(): void
    {
        $this->placement();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Synthetic queue outage'));
        app(HandoverReminderService::class)->process();
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        DB::beginTransaction();
        $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
        Mail::assertNothingQueued();
        DB::rollBack();
        Mail::assertNothingQueued();
        $this->assertNull(HandoverEmailOutbox::sole()->queued_at);
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        Mail::assertQueuedCount(1);
    }

    public function test_repeated_scheduler_and_backlogged_queue_do_not_duplicate_successful_queueing(): void
    {
        [, , $handover] = $this->placement();
        $this->assertSame(1, app(HandoverReminderService::class)->process());
        $this->assertSame(0, app(HandoverReminderService::class)->process());
        $row = HandoverEmailOutbox::sole();
        $this->travelTo(now()->addHours(2));
        $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertSame(1, $row->fresh()->queue_attempts);
        Mail::assertQueuedCount(1);
        $mailer = $this->arrayMailer();
        $mail = $this->queuedMail($row);
        $mail->send($mailer);
        $mail->send($mailer);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertSame(1, $row->fresh()->delivery_attempts);
        $this->assertNotNull($row->fresh()->transport_accepted_at);
        $this->assertNull($row->fresh()->suppressed_at);
        $this->assertNotNull($handover->fresh()->schedule_24h_reminder_sent_at);
    }

    public function test_missing_queue_acknowledgement_can_be_recovered_without_sending_the_old_job(): void
    {
        $this->placement();
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        $old = $this->queuedMail($row);
        $key = $old->deliveryIdempotencyKey;
        $row->update(['queued_at' => null, 'dispatch_lease_expires_at' => now()->subSecond()]);
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        $new = Mail::queued(TransactionalMail::class)->last();
        $this->assertSame($key, $new->deliveryIdempotencyKey);
        $this->assertNotSame($old->handoverOutboxToken, $new->handoverOutboxToken);
        $mailer = $this->arrayMailer();
        $old->send($mailer);
        $this->assertNull($row->fresh()->suppressed_at);
        $new->send($mailer);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
    }

    public function test_normal_worker_retry_then_terminal_failure_recovery_preserves_one_logical_event(): void
    {
        $this->placement();
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        $mail = $this->queuedMail($row);
        try {
            app(HandoverMailOutboxService::class)->deliver($row->id, $mail->handoverOutboxToken,
                fn () => throw new TransportException('PRIVATE https://courier.example.test/secret recipient@example.test'));
            $this->fail('Transport failure must reach the worker.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('PRIVATE', $exception->getMessage());
            $this->assertStringNotContainsString('example.test', $exception->getMessage());
        }
        $this->assertSame(1, $row->fresh()->delivery_attempts);
        $this->assertSame(TransportException::class, $row->fresh()->last_transport_error);
        $this->assertNull($row->fresh()->transport_accepted_at);
        $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
        $mail->failed(new RuntimeException('Synthetic final worker failure'));
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        $retry = Mail::queued(TransactionalMail::class)->last();
        $mailer = $this->arrayMailer();
        $mail->send($mailer); // A retried old failed job cannot race the replacement.
        $retry->send($mailer);
        $retry->send($mailer);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertSame(2, $row->fresh()->delivery_attempts);
        $this->assertSame(2, $row->fresh()->queue_attempts);
        $this->assertNotNull($row->fresh()->transport_accepted_at);
    }

    public static function staleEvents(): array
    {
        return [['reschedule'], ['receipt'], ['not_received'], ['reopen'], ['future_revision']];
    }

    #[DataProvider('staleEvents')]
    public function test_obsolete_queued_mail_is_suppressed_at_execution(string $change): void
    {
        [$owner, $staff, $handover] = $this->placement();
        if (in_array($change, ['receipt', 'not_received'], true)) {
            $handover->update(['released_at' => now(), 'release_method' => 'delivery']);
            $this->travelTo($handover->scheduled_end_at->copy()->addHour());
        }
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        $mail = $this->queuedMail($row);
        if ($change === 'reschedule') {
            $this->propose($handover, $staff);
        } elseif (in_array($change, ['receipt', 'not_received'], true)) {
            $handover->update(['adopter_outcome' => $change === 'receipt' ? 'received' : 'not_received', 'received_at' => $change === 'receipt' ? now() : null]);
        } elseif ($change === 'reopen') {
            $handover->update(['released_at' => now()]);
            $this->actingAs($staff)->post(route('admin.handover.reopen', $handover), ['reason' => 'Synthetic new attempt'])
                ->assertSessionHasNoErrors();
        } else {
            $row->update(['schedule_version' => $handover->schedule_version + 1]);
        }
        $mailer = $this->arrayMailer();
        $mail->send($mailer);
        $this->assertNotNull($row->fresh()->suppressed_at);
        $this->assertNull($row->fresh()->transport_accepted_at);
        $this->assertSame(0, $row->fresh()->delivery_attempts);
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
        $this->assertSame(0, app(HandoverReminderService::class)->process());
    }

    public function test_pending_reschedule_does_not_invalidate_a_still_confirmed_agreement(): void
    {
        [$owner, , $handover] = $this->placement();
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), [
            'schedule_version' => $handover->schedule_version,
            'options' => [['date' => ManilaTime::now()->addDays(3)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00']],
        ])->assertSessionHasNoErrors();
        $this->assertGreaterThan($row->schedule_version, $handover->fresh()->schedule_version);
        $mailer = $this->arrayMailer();
        $this->queuedMail($row)->send($mailer);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertNull($row->fresh()->suppressed_at);
    }

    public function test_retry_after_transport_failure_rechecks_a_superseded_schedule(): void
    {
        [, $staff, $handover] = $this->placement();
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        $mail = $this->queuedMail($row);
        try {
            app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token,
                fn () => throw new RuntimeException('Synthetic first transport failure'));
        } catch (RuntimeException) {
            $this->assertSame(1, $row->fresh()->delivery_attempts);
        }
        $this->propose($handover, $staff);
        $mailer = $this->arrayMailer();
        $mail->send($mailer);
        $this->assertSame('superseded_schedule', $row->fresh()->suppression_reason);
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
    }

    public function test_old_24h_mail_is_suppressed_without_losing_the_current_2h_reminder(): void
    {
        [, , $handover] = $this->placement();
        app(HandoverReminderService::class)->process();
        $old = HandoverEmailOutbox::sole();
        $oldMail = $this->queuedMail($old);
        $this->travelTo($handover->scheduled_start_at->copy()->subMinutes(90));
        $this->assertSame(1, app(HandoverReminderService::class)->process());
        $current = HandoverEmailOutbox::where('kind', '2h_reminder')->sole();
        $mailer = $this->arrayMailer();
        $oldMail->send($mailer);
        $this->queuedMail($current)->send($mailer);
        $this->assertSame('event_no_longer_due', $old->fresh()->suppression_reason);
        $this->assertNotNull($current->fresh()->transport_accepted_at);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
    }

    public function test_partial_staff_queue_failure_retries_only_the_unscheduled_recipient(): void
    {
        [, $admin, $handover] = $this->placement();
        $volunteer = $this->matchingUser(Role::Volunteer);
        $fake = Mail::getFacadeRoot();
        $adminPending = $fake->to($admin->email);
        Mail::shouldReceive('to')->with($admin->email)->andReturn($adminPending);
        Mail::shouldReceive('to')->with($volunteer->email)->andThrow(new RuntimeException('Synthetic queue failure'));
        $handover->update(['released_at' => now(), 'receipt_reminder_sent_at' => now()]);
        $this->travelTo($handover->scheduled_end_at->copy()->addHours(3));
        $this->assertSame(1, app(HandoverReminderService::class)->process());
        $fake->assertQueuedCount(1);
        $this->assertDatabaseCount('handover_email_outbox', 2);
        $adminRow = HandoverEmailOutbox::where('recipient_user_id', $admin->id)->sole();
        $volunteerRow = HandoverEmailOutbox::where('recipient_user_id', $volunteer->id)->sole();
        $this->assertNotNull($adminRow->queued_at);
        $this->assertNull($volunteerRow->queued_at);
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        Mail::assertQueuedCount(1);
        Mail::assertQueued(TransactionalMail::class, fn ($mail) => $mail->hasTo($volunteer->email));
        $this->assertSame(1, $adminRow->fresh()->queue_attempts);
        $this->assertSame(2, $volunteerRow->fresh()->queue_attempts);
    }

    public function test_confirmed_receipt_also_suppresses_already_queued_staff_escalation(): void
    {
        [, , $handover] = $this->placement();
        $handover->update(['released_at' => now(), 'release_method' => 'delivery', 'receipt_reminder_sent_at' => now()]);
        $this->travelTo($handover->scheduled_end_at->copy()->addHours(3));
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        $this->assertSame('delivery_follow_up', $row->kind);
        $mail = $this->queuedMail($row);
        $handover->update(['adopter_outcome' => 'received', 'received_at' => now()]);
        $mailer = $this->arrayMailer();
        $mail->send($mailer);
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
        $this->assertSame('receipt_already_recorded', $row->fresh()->suppression_reason);
    }

    public function test_null_transport_result_is_not_recorded_as_accepted_or_delivered(): void
    {
        $this->placement();
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        try {
            app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token, fn () => null);
            $this->fail('A canceled transport must not count as successful acceptance.');
        } catch (RuntimeException) {
            $this->assertNull($row->fresh()->transport_accepted_at);
            $this->assertSame(1, $row->fresh()->delivery_attempts);
        }
    }

    public static function progressingNotices(): array
    {
        return [['reminder'], ['completed']];
    }

    #[DataProvider('progressingNotices')]
    public function test_valid_manual_and_completion_notices_survive_normal_workflow_progression(string $kind): void
    {
        [, , $handover] = $this->placement();
        if ($kind === 'completed') {
            $handover->update(['released_at' => now(), 'adopter_outcome' => 'received', 'received_at' => now()]);
        }
        app(HandoverNotificationService::class)->create($handover, $kind, ['channels' => ['In-app', 'Email']]);
        $row = HandoverEmailOutbox::sole();
        $mailer = $this->arrayMailer();
        $this->queuedMail($row)->send($mailer);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertNotNull($row->fresh()->transport_accepted_at);
        $this->assertNull($row->fresh()->suppressed_at);
    }

    public function test_changed_alert_address_invalidates_a_queued_fixed_inbox_recipient(): void
    {
        [, , $handover] = $this->placement();
        config(['mail.staff_alert_address' => 'old-alert@example.test']);
        $handover->update(['released_at' => now(), 'receipt_reminder_sent_at' => now()]);
        $this->travelTo($handover->scheduled_end_at->copy()->addHours(3));
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::where('audience', 'alert')->sole();
        $mail = $this->queuedMail($row);
        config(['mail.staff_alert_address' => 'new-alert@example.test']);
        $mailer = $this->arrayMailer();
        $mail->send($mailer);
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
        $this->assertSame('recipient_ineligible', $row->fresh()->suppression_reason);
    }

    public static function timingBoundaries(): array
    {
        return [
            ['24h', -1, 0], ['24h', 0, 1],
            ['2h', -1, 1], ['2h', 0, 1],
            ['receipt', -1, 0], ['receipt', 0, 1],
            ['escalation', -1, 0], ['escalation', 0, 1],
            ['pickup', -1, 0], ['pickup', 0, 1],
        ];
    }

    #[DataProvider('timingBoundaries')]
    public function test_original_manila_operational_thresholds_are_unchanged(string $event, int $seconds, int $expected): void
    {
        [, , $handover] = $this->placement($event === 'pickup' ? 'pickup' : 'delivery');
        if (in_array($event, ['receipt', 'escalation'], true)) {
            $handover->update(['released_at' => now(), 'receipt_reminder_sent_at' => $event === 'escalation' ? now() : null]);
        }
        $instant = match ($event) {
            '24h' => $handover->scheduled_start_at->copy()->subHours(24),
            '2h' => $handover->scheduled_start_at->copy()->subHours(2),
            'receipt', 'pickup' => $handover->scheduled_end_at->copy()->addHour(),
            'escalation' => $handover->scheduled_end_at->copy()->addHours(3),
        };
        $this->travelTo($instant->addSeconds($seconds));
        $this->assertSame($expected, app(HandoverReminderService::class)->process());
        $this->assertSame(0, app(HandoverReminderService::class)->process());
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->assertSame('Approved', $handover->application->fresh()->status->value);
        $this->assertNull($handover->fresh()->adopter_outcome);
    }

    public static function recipientChanges(): array
    {
        return [['inactive'], ['unverified'], ['role'], ['email'], ['deleted']];
    }

    #[DataProvider('recipientChanges')]
    public function test_recipient_authorization_is_rechecked_by_worker(string $change): void
    {
        [$owner] = $this->placement();
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        $mail = $this->queuedMail($row);
        match ($change) {
            'inactive' => $owner->update(['is_active' => false]),
            'unverified' => $owner->update(['email_verified_at' => null]),
            'role' => $owner->update(['role' => 'Volunteer']),
            'email' => $owner->update(['email' => 'changed@example.test']),
            'deleted' => $owner->delete(),
        };
        $mailer = $this->arrayMailer();
        $mail->send($mailer);
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
        // Deleting the owner can cascade-delete the handover/outbox; that job is a no-op.
        if ($row->fresh()) {
            $this->assertSame('recipient_ineligible', $row->fresh()->suppression_reason);
        }
    }

    public function test_staff_routing_deduplicates_recipients_and_does_not_claim_sms_delivery(): void
    {
        [$owner, $admin, $handover] = $this->placement();
        $volunteer = $this->matchingUser(Role::Volunteer);
        $inactive = $this->matchingUser(Role::Volunteer);
        $inactive->update(['is_active' => false]);
        config(['mail.staff_alert_address' => $admin->email]);
        $handover->update(['released_at' => now(), 'release_method' => 'delivery', 'receipt_reminder_sent_at' => now()]);
        $this->travelTo($handover->scheduled_end_at->copy()->addHours(3));
        $this->assertSame(1, app(HandoverReminderService::class)->process());
        $this->assertSame(0, app(HandoverReminderService::class)->process());
        Mail::assertQueuedCount(2);
        Mail::assertQueued(TransactionalMail::class, fn ($mail) => $mail->hasTo($admin->email));
        Mail::assertQueued(TransactionalMail::class, fn ($mail) => $mail->hasTo($volunteer->email));
        Mail::assertNotQueued(TransactionalMail::class, fn ($mail) => $mail->hasTo($owner->email) || $mail->hasTo($inactive->email));
        $this->assertSame(1, $admin->inAppNotifications()->where('kind', 'handover_delivery_follow_up')->count());
        $this->assertSame(0, $volunteer->inAppNotifications()->where('kind', 'handover_delivery_follow_up')->count());
        $notice = app(HandoverNotificationService::class)->create($handover, 'released');
        $this->assertSame(['In-app', 'Email'], $notice->channels);
        $row = HandoverEmailOutbox::where('recipient_user_id', $volunteer->id)->sole();
        $volunteer->update(['role' => 'Adopter']);
        $mailer = $this->arrayMailer();
        $this->queuedMail($row)->send($mailer);
        $this->assertCount(0, $mailer->getSymfonyTransport()->messages());
        $this->assertSame('recipient_ineligible', $row->fresh()->suppression_reason);
    }

    public function test_payload_and_job_are_encrypted_and_real_database_worker_can_retry_then_accept(): void
    {
        [$owner] = $this->placement();
        config(['queue.default' => 'database']);
        $manager = new MailManager($this->app);
        Mail::swap($manager);
        $mailer = $manager->mailer('array');
        $failingTransport = \Mockery::mock(TransportInterface::class);
        $failingTransport->shouldReceive('send')->once()->andThrow(new TransportException('PRIVATE recipient@example.test'));
        $mailer->setSymfonyTransport($failingTransport);
        $this->assertSame(1, app(HandoverReminderService::class)->process());
        $row = HandoverEmailOutbox::sole();
        $this->assertNotNull($row->queued_at);
        $this->assertNull($row->transport_accepted_at);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertStringNotContainsString($owner->email, $row->getRawOriginal('payload'));
        $this->assertStringNotContainsString($owner->email, DB::table('jobs')->value('payload'));
        $this->assertArrayNotHasKey('payload', $row->toArray());
        $this->assertArrayNotHasKey('enqueue_token', $row->toArray());
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'emails', '--once' => true, '--tries' => 3])->assertSuccessful();
        $this->assertSame(1, $row->fresh()->delivery_attempts);
        $this->assertNull($row->fresh()->transport_accepted_at);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(1, (int) DB::table('jobs')->value('attempts'));
        $mailer->setSymfonyTransport(new ArrayTransport);
        $this->travelTo(now()->addSeconds(61));
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'emails', '--once' => true, '--tries' => 3])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(2, $row->fresh()->delivery_attempts);
        $this->assertSame(1, $row->fresh()->queue_attempts);
        $this->assertNotNull($row->fresh()->transport_accepted_at);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
    }

    public function test_exhausted_database_worker_job_is_retained_and_outbox_recovers_with_a_new_token(): void
    {
        [$owner] = $this->placement();
        config(['queue.default' => 'database']);
        $manager = new MailManager($this->app);
        Mail::swap($manager);
        $mailer = $manager->mailer('array');
        $transport = \Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->times(3)->andThrow(new TransportException('PRIVATE recipient@example.test'));
        $mailer->setSymfonyTransport($transport);
        app(HandoverReminderService::class)->process();
        $row = HandoverEmailOutbox::sole();
        $oldToken = $row->enqueue_token;
        foreach ([0, 61, 301] as $delay) {
            $this->travelTo(now()->addSeconds($delay));
            $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'emails', '--once' => true, '--tries' => 3])->assertSuccessful();
        }
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame(3, $row->fresh()->delivery_attempts);
        $this->assertNull($row->fresh()->queued_at);
        $this->assertNull($row->fresh()->enqueue_token);
        $this->assertNull($row->fresh()->transport_accepted_at);
        $failed = DB::table('failed_jobs')->first();
        $this->assertStringNotContainsString('PRIVATE', $failed->exception);
        $this->assertStringNotContainsString('recipient@example.test', $failed->exception);
        $this->assertStringNotContainsString($owner->email, $failed->payload);
        $mailer->setSymfonyTransport(new ArrayTransport);
        $this->artisan('handovers:dispatch-pending-email')->assertSuccessful();
        $this->assertNotSame($oldToken, $row->fresh()->enqueue_token);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'emails', '--once' => true, '--tries' => 3])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        $this->assertSame(4, $row->fresh()->delivery_attempts);
        $this->assertNotNull($row->fresh()->transport_accepted_at);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
    }

    private function placement(string $method = 'delivery'): array
    {
        $owner = $this->matchingUser();
        $staff = $this->matchingUser(Role::Administrator);
        $pet = Pet::create(['name' => 'Reminder Test Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now()]);
        $handover = Handover::create([
            'code' => 'MAIL-'.Str::uuid(), 'application_id' => $application->id, 'user_id' => $owner->id,
            'pet_id' => $pet->id, 'adopter_name' => $owner->full_name, 'approved_at' => now(), 'history' => [],
        ]);
        $this->prepareHandover($handover, $staff, $method);

        return [$owner, $staff, $handover];
    }

    private function propose(Handover $handover, $staff): void
    {
        app(HandoverScheduleService::class)->propose($handover, $staff, [
            'method' => 'pickup', 'schedule_version' => $handover->refresh()->schedule_version,
            'date' => ManilaTime::now()->addDays(3)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00',
        ]);
    }

    private function queuedMail(HandoverEmailOutbox $row): TransactionalMail
    {
        return Mail::queued(TransactionalMail::class)->first(fn ($mail) => $mail->handoverOutboxId === $row->id);
    }

    private function arrayMailer(): Mailer
    {
        return (new MailManager($this->app))->mailer('array');
    }
}
