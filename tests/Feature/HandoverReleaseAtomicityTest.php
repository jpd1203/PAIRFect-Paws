<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\HandoverEmailOutbox;
use App\Models\HandoverNotification;
use App\Models\IdentityVerification;
use App\Models\Pet;
use App\Models\User;
use App\Services\HandoverMailOutboxService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Process\Process;
use Tests\Concerns\PreparesVerifiedHandovers;
use Tests\TestCase;

/** Real commits: deliberately no RefreshDatabase/DatabaseTransactions wrapper. */
class HandoverReleaseAtomicityTest extends TestCase
{
    use DatabaseTruncation;
    use PreparesVerifiedHandovers;

    private bool $disposableDatabaseValidated = false;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame(':memory:', DB::getDatabaseName());
            // Each application has a new in-memory connection, unlike a wrapped test.
            RefreshDatabaseState::$migrated = false;
        } else {
            $this->assertSame('mysql', DB::getDriverName());
            $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
            $this->assertNotEmpty(env('PAIRFECT_DISPOSABLE_MYSQL_DB'));
            $this->assertSame(env('PAIRFECT_DISPOSABLE_MYSQL_DB'), DB::getDatabaseName());
        }
        $this->disposableDatabaseValidated = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(0, DB::transactionLevel());
        Mail::fake();
        Storage::fake('local');
        config(['mail.default' => 'array', 'mail.staff_alert_address' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:00:00', 'Asia/Manila')->utc());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->disposableDatabaseValidated) {
                DB::purge('release_observer');
                // Leave no committed fixture rows for following RefreshDatabase test classes.
                $this->truncateTablesForAllConnections();
            }
        } finally {
            parent::tearDown();
        }
    }

    public static function methods(): array
    {
        return [['pickup'], ['delivery']];
    }

    #[DataProvider('methods')]
    public function test_authorized_release_commits_one_adopter_notice_and_durable_email_intent(string $method): void
    {
        [$owner, $staff, $handover] = $this->placement($method);
        $connection = DB::connection()->getName();
        foreach ([new Handover, new AdoptionApplication, new AuditLog, new HandoverNotification, new HandoverEmailOutbox] as $model) {
            $this->assertSame($connection, $model->getConnection()->getName());
        }

        $this->release($staff, $handover, $method)->assertSessionHas('toast.type', 'success');
        $this->assertSame(0, DB::transactionLevel());
        $handover->refresh();
        $this->assertNotNull($handover->released_at);
        $this->assertTrue($handover->released_at->equalTo(now()));
        $this->assertSame($method, $handover->release_method);
        $this->assertCount(1, $handover->history);
        $this->assertDatabaseCount('handover_notifications', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        $notice = HandoverNotification::sole();
        $row = HandoverEmailOutbox::sole();
        $this->assertSame($owner->id, $notice->user_id);
        $this->assertSame('released', $notice->kind);
        $this->assertSame(['In-app', 'Email'], $notice->channels);
        $this->assertSame($owner->id, $row->recipient_user_id);
        $this->assertSame('adopter', $row->audience);
        $this->assertSame($owner->email, $row->payload['recipient']);
        $this->assertNotNull($row->queued_at);
        $this->assertNull($row->transport_accepted_at);
        $this->assertSame(1, $this->releaseAudits($handover));
        Mail::assertQueued(TransactionalMail::class, fn ($mail) => $mail->hasTo($owner->email));
        Mail::assertQueuedCount(1);
        $this->assertNull($handover->received_at);
        $this->assertNull($handover->adopter_outcome);
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public static function persistenceFailures(): array
    {
        return [['outbox', 'pickup'], ['outbox', 'delivery'], ['bell', 'delivery'], ['audit', 'pickup']];
    }

    #[DataProvider('persistenceFailures')]
    public function test_database_failure_rolls_back_release_history_audit_notice_and_proof_then_allows_one_retry(string $target, string $method): void
    {
        [, $staff, $handover] = $this->placement($method);
        $before = $handover->refresh()->getAttributes();
        $fail = true;
        $model = match ($target) {
            'outbox' => HandoverEmailOutbox::class,
            'bell' => HandoverNotification::class,
            'audit' => AuditLog::class,
        };
        Event::listen('eloquent.creating: '.$model, function () use (&$fail): void {
            if ($fail) {
                // A real QueryException from the disposable database, not a mail failure.
                DB::statement('INSERT INTO phase10_missing_fault_table (id) VALUES (1)');
            }
        });

        $this->release($staff, $handover, $method, ['proof' => UploadedFile::fake()->image('synthetic.jpg')])->assertStatus(500);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, $handover->fresh()->getAttributes());
        $this->assertSame(0, $this->releaseAudits($handover));
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('handover-proofs'));
        Mail::assertNothingQueued();

        $fail = false;
        $this->release($staff, $handover, $method, ['proof' => UploadedFile::fake()->image('retry.jpg')])->assertSessionHas('toast.type', 'success');
        $handover->refresh();
        $this->assertNotNull($handover->released_at);
        Storage::disk('local')->assertExists($handover->proof_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('handover-proofs'));
        $this->assertCount(1, $handover->history);
        $this->assertSame(1, $this->releaseAudits($handover));
        $this->assertDatabaseCount('handover_notifications', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        Mail::assertQueuedCount(1);
        $this->release($staff, $handover, $method)->assertSessionHasErrors('release_method');
        $this->assertDatabaseCount('handover_notifications', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_enqueue_failure_occurs_after_real_commit_and_recovery_keeps_one_logical_event(): void
    {
        [, $staff, $handover] = $this->placement('delivery');
        $attempts = 0;
        Mail::shouldReceive('to')->once()->andReturnUsing(function () use (&$attempts, $handover): never {
            $attempts++;
            $this->assertSame(0, DB::transactionLevel());
            $this->assertNotNull($handover->fresh()->released_at);
            $this->assertDatabaseCount('handover_notifications', 1);
            $this->assertDatabaseCount('handover_email_outbox', 1);
            throw new RuntimeException('Synthetic queue outage');
        });
        $this->release($staff, $handover, 'delivery', ['proof' => UploadedFile::fake()->image('queued-release.jpg')])->assertSessionHas('toast.type', 'success');
        $this->assertSame(1, $attempts);
        Storage::disk('local')->assertExists($handover->fresh()->proof_path);
        $row = HandoverEmailOutbox::sole();
        $this->assertNull($row->queued_at);
        $this->assertNull($row->transport_accepted_at);
        $this->assertSame(RuntimeException::class, $row->last_queue_error);
        $key = $row->event_key;

        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertSame($key, $row->fresh()->event_key);
        $this->assertNotNull($row->fresh()->queued_at);
        $this->assertNull($row->fresh()->transport_accepted_at);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        $this->assertDatabaseCount('handover_notifications', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_transport_failure_after_commit_does_not_undo_release_and_duplicate_job_does_not_resend(): void
    {
        [, $staff, $handover] = $this->placement('pickup');
        $this->release($staff, $handover, 'pickup')->assertSessionHas('toast.type', 'success');
        $row = HandoverEmailOutbox::sole();
        $mail = Mail::queued(TransactionalMail::class)->sole();
        try {
            app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token,
                fn () => throw new TransportException('Synthetic transport outage'));
            $this->fail('Transport error must reach the worker.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Handover email transport failed', $exception->getMessage());
        }
        $this->assertNotNull($handover->fresh()->released_at);
        $this->assertSame(1, $row->fresh()->delivery_attempts);
        $this->assertNull($row->fresh()->transport_accepted_at);
        $mailer = (new MailManager($this->app))->mailer('array');
        $mail->send($mailer);
        $mail->send($mailer);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertNotNull($row->fresh()->transport_accepted_at);
        $this->assertSame(2, $row->fresh()->delivery_attempts);
        $this->assertSame(1, $this->releaseAudits($handover));
    }

    public function test_outer_rollback_removes_nested_notification_intent_and_discards_after_commit_dispatch(): void
    {
        [, $staff, $handover] = $this->placement('pickup');
        DB::beginTransaction();
        try {
            $this->release($staff, $handover, 'pickup')->assertSessionHas('toast.type', 'success');
            $this->assertDatabaseCount('handover_email_outbox', 1);
            Mail::assertNothingQueued();
        } finally {
            DB::rollBack();
        }
        $this->assertNull($handover->fresh()->released_at);
        $this->assertSame([], $handover->fresh()->history);
        $this->assertSame(0, $this->releaseAudits($handover));
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        Mail::assertNothingQueued();
    }

    public static function releaseBoundaries(): array
    {
        $cases = [];
        foreach (['pickup', 'delivery'] as $method) {
            foreach (['early', 'within', 'exact_end', 'expired'] as $boundary) {
                $cases[$method.' '.$boundary] = [$method, $boundary];
            }
        }

        return $cases;
    }

    #[DataProvider('releaseBoundaries')]
    public function test_authoritative_server_time_preserves_early_exact_end_and_expired_rules(string $method, string $boundary): void
    {
        [, $staff, $handover] = $this->placement($method);
        $clock = match ($boundary) {
            'early' => $handover->scheduled_start_at->copy()->subHour(),
            'within' => $handover->scheduled_start_at->copy()->addMinute(),
            'exact_end' => $handover->scheduled_end_at,
            'expired' => $handover->scheduled_end_at->copy()->addSecond(),
        };
        $this->travelTo($clock);
        $response = $this->release($staff, $handover, $method, ['release_date' => '2020-01-01', 'release_time' => '00:00']);
        if ($boundary === 'expired') {
            $response->assertSessionHasErrors('schedule');
            $this->assertNull($handover->fresh()->released_at);
            $this->assertDatabaseCount('handover_email_outbox', 0);
            Mail::assertNothingQueued();
        } else {
            $response->assertSessionHas('toast.type', 'success');
            $this->assertTrue($handover->fresh()->released_at->equalTo($clock));
            $this->assertDatabaseCount('handover_email_outbox', 1);
        }
    }

    #[DataProvider('methods')]
    public function test_stale_release_form_cannot_override_a_different_current_agreement(string $oldMethod): void
    {
        [, $staff, $handover] = $this->placement($oldMethod);
        $this->prepareHandover($handover, $staff, $oldMethod === 'pickup' ? 'delivery' : 'pickup');
        $this->release($staff, $handover, $oldMethod)->assertSessionHasErrors('release_method');
        $this->assertNull($handover->fresh()->released_at);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        Mail::assertNothingQueued();
    }

    public static function safeguards(): array
    {
        return [['destination'], ['readiness'], ['interview'], ['pickup_identity'], ['courier'], ['https'], ['schedule'], ['approval'], ['inactive_staff']];
    }

    #[DataProvider('safeguards')]
    public function test_release_guards_still_block_state_and_notification_writes(string $guard): void
    {
        $method = $guard === 'pickup_identity' ? 'pickup' : 'delivery';
        [, $staff, $handover] = $this->placement($method);
        $extra = [];
        match ($guard) {
            'destination' => $handover->forceFill(['delivery_address_status' => null])->save(),
            'readiness' => $handover->forceFill(['delivery_recipient_available' => null])->save(),
            'interview' => IdentityVerification::where('application_id', $handover->application_id)->where('stage', 'interview')->delete(),
            'pickup_identity' => IdentityVerification::where('application_id', $handover->application_id)->where('stage', 'pickup_handover')->delete(),
            'courier' => $extra = ['courier_provider' => 'unapproved'],
            'https' => $extra = ['tracking_url' => 'http://courier.example.test/qa'],
            'schedule' => $handover->update(['schedule_status' => 'proposed']),
            'approval' => $handover->application->update(['status' => 'Rejected']),
            'inactive_staff' => $staff->update(['is_active' => false]),
        };
        $response = $this->release($staff, $handover, $method, $extra);
        $this->assertFalse($response->isServerError());
        $this->assertNull($handover->fresh()->released_at);
        $this->assertSame(0, $this->releaseAudits($handover));
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        Mail::assertNothingQueued();
    }

    public function test_independent_mysql_connection_sees_no_partial_release_and_queueing_observes_committed_state(): void
    {
        $this->requireDisposableMysql();
        [, $staff, $handover] = $this->placement('delivery');
        $observer = $this->observer();
        $this->assertNotSame(DB::getPdo(), $observer->getPdo());
        $observedUncommitted = false;
        Event::listen('eloquent.creating: '.HandoverEmailOutbox::class, function () use ($observer, $handover, &$observedUncommitted): void {
            $this->assertGreaterThan(0, DB::transactionLevel());
            $this->assertNull($observer->table('handovers')->where('id', $handover->id)->value('released_at'));
            $this->assertSame(0, $observer->table('handover_notifications')->count());
            $this->assertSame(0, $observer->table('handover_email_outbox')->count());
            $this->assertSame(0, $observer->table('audit_logs')->where('action', 'handover.released')->count());
            $observedUncommitted = true;
        });
        Mail::shouldReceive('to')->once()->andReturnUsing(function () use ($observer, $handover): never {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertNotNull($observer->table('handovers')->where('id', $handover->id)->value('released_at'));
            $this->assertSame(1, $observer->table('handover_notifications')->count());
            $this->assertSame(1, $observer->table('handover_email_outbox')->count());
            throw new RuntimeException('Synthetic queue failure after independent visibility check');
        });
        $this->release($staff, $handover, 'delivery')->assertSessionHas('toast.type', 'success');
        $this->assertTrue($observedUncommitted);
    }

    public static function concurrentOutcomes(): array
    {
        return [['commit'], ['rollback']];
    }

    #[DataProvider('concurrentOutcomes')]
    public function test_real_mysql_release_requests_serialize_and_only_committed_intent_reaches_the_worker(string $firstOutcome): void
    {
        $this->requireDisposableMysql();
        [, $staff, $handover] = $this->placement('delivery');
        $directory = sys_get_temp_dir().'/pairfect-release-'.Str::uuid();
        mkdir($directory);
        $first = $this->releaseProcess($staff, $handover, ['pause_directory' => $directory, 'fail_outbox' => $firstOutcome === 'rollback']);
        $second = $this->releaseProcess($staff, $handover, ['started_file' => $directory.'/second-started']);
        try {
            $first->start();
            $this->waitFor(fn () => is_file($directory.'/ready') || ! $first->isRunning(), 'First release did not reach outbox staging.');
            $this->assertFileExists($directory.'/ready', $first->getErrorOutput().$first->getOutput());
            $this->assertNull($this->observer()->table('handovers')->where('id', $handover->id)->value('released_at'));
            $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
            $this->assertDatabaseCount('jobs', 0);
            $second->start();
            $this->waitFor(fn () => is_file($directory.'/second-started'), 'Second release did not start.');
            $this->waitFor(fn () => $this->observer()->table('performance_schema.data_lock_waits')->count() > 0, 'Expected a real InnoDB lock wait between independent release processes.');
            touch($directory.'/continue');
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
            $result1 = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $result2 = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($firstOutcome === 'commit' ? 302 : 500, $result1['status']);
            $this->assertSame($firstOutcome === 'commit' ? 422 : 302, $result2['status']);
            $this->assertSame(0, $result1['transaction_level']);
            $this->assertSame(0, $result2['transaction_level']);
            $this->assertNotNull($handover->fresh()->released_at);
            $this->assertCount(1, $handover->fresh()->history);
            $this->assertSame(1, $this->releaseAudits($handover));
            $this->assertDatabaseCount('handover_notifications', 1);
            $this->assertDatabaseCount('handover_email_outbox', 1);
            $this->assertDatabaseCount('jobs', 1);
            $this->assertNull(HandoverEmailOutbox::sole()->transport_accepted_at);
            Mail::swap(new MailManager($this->app));
            $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'emails', '--once' => true])->assertSuccessful();
            $this->assertDatabaseCount('jobs', 0);
            $this->assertNotNull(HandoverEmailOutbox::sole()->transport_accepted_at);
            $this->assertDatabaseCount('post_adoption_logs', 0);
        } finally {
            $first->stop(0);
            $second->stop(0);
            foreach (['ready', 'continue', 'second-started'] as $file) {
                if (is_file($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                }
            }
            rmdir($directory);
        }
    }

    private function placement(string $method): array
    {
        $owner = User::factory()->create(['role' => Role::Adopter, 'email_verified_at' => now(), 'is_active' => true]);
        $staff = User::factory()->create(['role' => Role::Administrator, 'email_verified_at' => now(), 'is_active' => true]);
        $pet = Pet::create(['name' => 'Synthetic Atomic Release Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now()]);
        $handover = Handover::create(['code' => 'ATOMIC-'.Str::uuid(), 'application_id' => $application->id, 'pet_id' => $pet->id, 'user_id' => $owner->id, 'adopter_name' => $owner->full_name, 'approved_at' => now(), 'history' => [], 'reminders' => []]);
        $this->prepareHandover($handover, $staff, $method);

        return [$owner, $staff, $handover];
    }

    private function releaseData(User $staff, string $method): array
    {
        return ['release_method' => $method, 'release_date' => now('Asia/Manila')->toDateString(), 'release_time' => '09:00', 'staff_id' => $staff->id, 'courier_provider' => 'pet_to_go_express', 'tracking_number' => 'SYNTHETIC-ATOMIC', 'tracking_url' => 'https://courier.example.test/synthetic'];
    }

    private function release(User $staff, Handover $handover, string $method, array $extra = [])
    {
        return $this->actingAs($staff)->post(route('admin.handover.release', $handover), [...$this->releaseData($staff, $method), ...$extra]);
    }

    private function releaseAudits(Handover $handover): int
    {
        return AuditLog::where('action', 'handover.released')->where('entity_id', $handover->id)->count();
    }

    private function requireDisposableMysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Independent connections/processes require the explicitly disposable MySQL run, not SQLite.');
        }
        $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
        $this->assertSame(env('PAIRFECT_DISPOSABLE_MYSQL_DB'), DB::getDatabaseName());
    }

    private function observer()
    {
        config(['database.connections.release_observer' => config('database.connections.mysql')]);

        return DB::connection('release_observer');
    }

    private function releaseProcess(User $staff, Handover $handover, array $options): Process
    {
        return new Process([PHP_BINARY, base_path('tests/Support/handover-release-process.php'), base64_encode(json_encode([
            'staff_id' => $staff->id, 'handover_id' => $handover->id, 'data' => $this->releaseData($staff, 'delivery'),
            'clock' => now()->toIso8601String(), ...$options,
        ], JSON_THROW_ON_ERROR))], base_path(), timeout: 40);
    }

    private function waitFor(callable $condition, string $message): void
    {
        $deadline = microtime(true) + 15;
        do {
            if ($condition()) {
                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);
        $this->fail($message);
    }
}
