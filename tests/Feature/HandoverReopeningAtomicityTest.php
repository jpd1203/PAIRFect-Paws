<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\HandoverEmailOutbox;
use App\Models\HandoverNotification;
use App\Models\Pet;
use App\Models\User;
use App\Services\HandoverMailOutboxService;
use App\Services\HandoverNotificationService;
use App\Services\HandoverScheduleService;
use App\Services\HandoverTransportIncidentService;
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
use Symfony\Component\Process\Process;
use Tests\Concerns\PreparesVerifiedHandovers;
use Tests\TestCase;

/** Real commits, deliberately without a wrapping test transaction. */
class HandoverReopeningAtomicityTest extends TestCase
{
    use DatabaseTruncation;
    use PreparesVerifiedHandovers;

    private bool $disposableDatabaseValidated = false;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertTrue(app()->environment('testing'));
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame(':memory:', DB::getDatabaseName());
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
        config(['mail.default' => 'array', 'mail.staff_alert_address' => null, 'filesystems.handover_receipts_disk' => 'local']);
        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:00:00', 'Asia/Manila')->utc());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->disposableDatabaseValidated) {
                DB::purge('reopening_observer');
                $this->truncateTablesForAllConnections();
            }
        } finally {
            parent::tearDown();
        }
    }

    public static function authorizedStates(): array
    {
        return [['Administrator', null], ['Administrator', 'not_received'], ['Volunteer', null], ['Volunteer', 'not_received']];
    }

    #[DataProvider('authorizedStates')]
    public function test_authorized_reopening_commits_one_new_attempt_and_adopter_notification(string $role, ?string $outcome): void
    {
        [$owner, $staff, $handover] = $this->placement(Role::from($role));
        $handover->update(['adopter_outcome' => $outcome]);
        $before = $handover->refresh()->getAttributes();
        $application = $handover->application->getAttributes();
        $pet = $handover->pet->getAttributes();
        $this->reopen($staff, $handover)->assertSessionHas('toast.type', 'success');
        $this->assertReopened($handover, $before);
        $this->assertSame($application, $handover->application->fresh()->getAttributes());
        $this->assertSame($pet, $handover->pet->fresh()->getAttributes());
        $notice = HandoverNotification::sole();
        $row = HandoverEmailOutbox::sole();
        $this->assertSame('reopened', $notice->kind);
        $this->assertSame($owner->id, $notice->user_id);
        $this->assertSame(['In-app', 'Email'], $notice->channels);
        $this->assertSame('reopened', $row->kind);
        $this->assertSame($owner->id, $row->recipient_user_id);
        $this->assertSame('adopter', $row->audience);
        $this->assertSame(1, $row->release_attempt);
        $this->assertSame($owner->email, $row->payload['recipient']);
        $this->assertNotNull($row->queued_at);
        $this->assertNull($row->transport_accepted_at);
        $this->assertSame(1, $this->reopenAudits($handover));
        Storage::disk('local')->assertExists($before['proof_path']);
        Mail::assertQueued(TransactionalMail::class, fn ($mail) => $mail->hasTo($owner->email));
        Mail::assertQueuedCount(1);
        $this->reopen($staff, $handover)->assertSessionHasErrors('reason');
        $this->assertSame(1, $handover->fresh()->reopen_count);
        $this->assertDatabaseCount('handover_notifications', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        Mail::assertQueuedCount(1);
    }

    public static function defaultReasons(): array
    {
        return [[[]], [['reason' => null]], [['reason' => '']], [['reason' => '   ']]];
    }

    #[DataProvider('defaultReasons')]
    public function test_existing_optional_reason_and_default_are_preserved(array $input): void
    {
        [, $staff, $handover] = $this->placement();
        $this->actingAs($staff)->post(route('admin.handover.reopen', $handover), $input)->assertSessionHas('toast.type', 'success');
        $this->assertSame('Handover redone', $handover->fresh()->reopen_reason);
        $this->assertDatabaseCount('handover_email_outbox', 1);
    }

    public static function storageFailures(): array
    {
        return [['bell'], ['outbox'], ['history'], ['audit']];
    }

    #[DataProvider('storageFailures')]
    public function test_storage_failure_rolls_back_original_release_attempt_history_and_intent_then_allows_retry(string $target): void
    {
        [, $staff, $handover] = $this->placement();
        $before = $handover->refresh()->getAttributes();
        $proofHash = hash_file('sha256', Storage::disk('local')->path($before['proof_path']));
        $fail = true;
        $model = match ($target) {
            'bell' => HandoverNotification::class, 'outbox' => HandoverEmailOutbox::class,
            'audit' => AuditLog::class, 'history' => Handover::class,
        };
        $event = $target === 'history' ? 'saving' : 'creating';
        Event::listen('eloquent.'.$event.': '.$model, function ($record) use (&$fail, $target): void {
            if ($fail && ($target !== 'history' || $record->isDirty('history'))) {
                DB::statement('INSERT INTO phase10b_missing_fault_table (id) VALUES (1)');
            }
        });
        $this->reopen($staff, $handover)->assertStatus(500);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, $handover->fresh()->getAttributes());
        $this->assertSame(0, $this->reopenAudits($handover));
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        $this->assertSame($proofHash, hash_file('sha256', Storage::disk('local')->path($before['proof_path'])));
        Mail::assertNothingQueued();

        $fail = false;
        $this->reopen($staff, $handover)->assertSessionHas('toast.type', 'success');
        $this->assertReopened($handover, $before);
        $this->assertSame(1, $this->reopenAudits($handover));
        $this->assertDatabaseCount('handover_notifications', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        Mail::assertQueuedCount(1);
        $this->reopen($staff, $handover)->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('handover_email_outbox', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_queue_failure_after_commit_retains_reopening_and_recoverable_intent(): void
    {
        [, $staff, $handover] = $this->placement();
        $before = $handover->refresh()->getAttributes();
        $attempts = 0;
        Mail::shouldReceive('to')->once()->andReturnUsing(function () use ($handover, &$attempts): never {
            $attempts++;
            $this->assertSame(0, DB::transactionLevel());
            $this->assertNull($handover->fresh()->released_at);
            $this->assertSame(1, $handover->fresh()->reopen_count);
            $this->assertDatabaseCount('handover_notifications', 1);
            $this->assertDatabaseCount('handover_email_outbox', 1);
            throw new RuntimeException('Synthetic queue outage');
        });
        $this->reopen($staff, $handover)->assertSessionHas('toast.type', 'success');
        $this->assertSame(1, $attempts);
        $this->assertReopened($handover, $before);
        $row = HandoverEmailOutbox::sole();
        $key = $row->event_key;
        $this->assertNull($row->queued_at);
        $this->assertNull($row->transport_accepted_at);
        $this->assertSame(RuntimeException::class, $row->last_queue_error);
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertSame($key, $row->fresh()->event_key);
        $this->assertNotNull($row->fresh()->queued_at);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_outer_rollback_discards_nested_intent_and_after_commit_callback(): void
    {
        [, $staff, $handover] = $this->placement();
        $before = $handover->refresh()->getAttributes();
        DB::beginTransaction();
        try {
            $this->reopen($staff, $handover)->assertSessionHas('toast.type', 'success');
            $this->assertDatabaseCount('handover_email_outbox', 1);
            Mail::assertNothingQueued();
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $handover->fresh()->getAttributes());
        $this->assertSame(0, $this->reopenAudits($handover));
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        Mail::assertNothingQueued();
    }

    public function test_incident_evidence_and_original_release_proof_survive_rollback_and_success(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $handover->update(['adopter_outcome' => 'not_received', 'adopter_confirmed_at' => now()]);
        DB::transaction(fn () => app(HandoverTransportIncidentService::class)->reportedNonReceipt($handover, $owner));
        $case = $handover->transportIncidents()->sole();
        $evidence = UploadedFile::fake()->image('incident.png')->store('handover-transport-evidence', 'local');
        $case->forceFill(['evidence_path' => $evidence, 'evidence_sha256' => hash_file('sha256', Storage::disk('local')->path($evidence))])->save();
        $caseBefore = $case->refresh()->getAttributes();
        $releaseProof = $handover->proof_path;
        $fail = true;
        Event::listen('eloquent.creating: '.HandoverEmailOutbox::class, function () use (&$fail): void {
            if ($fail) {
                DB::statement('INSERT INTO phase10b_missing_fault_table (id) VALUES (1)');
            }
        });
        $this->reopen($staff, $handover)->assertStatus(500);
        $this->assertSame($caseBefore, $case->fresh()->getAttributes());
        Storage::disk('local')->assertExists($releaseProof);
        Storage::disk('local')->assertExists($evidence);
        $fail = false;
        $this->reopen($staff, $handover)->assertSessionHas('toast.type', 'success');
        $this->assertSame($caseBefore, $case->fresh()->getAttributes());
        $this->assertSame(0, $case->release_attempt);
        $this->assertSame(1, $handover->fresh()->reopen_count);
        $url = route('admin.handover.incidents.evidence', [$handover, $case]);
        $this->get($url)->assertOk();
        $this->get($url.'?kind=release')->assertOk();
        $this->actingAs($owner)->get($url)->assertRedirect();
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public function test_old_attempt_mail_is_suppressed_and_reopened_mail_is_sent_only_once(): void
    {
        [, $staff, $handover] = $this->placement();
        app(HandoverNotificationService::class)->create($handover, 'released');
        $oldRow = HandoverEmailOutbox::sole();
        $oldMail = Mail::queued(TransactionalMail::class)->sole();
        $this->reopen($staff, $handover)->assertSessionHas('toast.type', 'success');
        $newRow = HandoverEmailOutbox::where('kind', 'reopened')->sole();
        $newMail = Mail::queued(TransactionalMail::class)->first(fn ($mail) => $mail->handoverOutboxId === $newRow->id);
        $mailer = (new MailManager($this->app))->mailer('array');
        $oldMail->send($mailer);
        $this->assertSame('superseded_attempt', $oldRow->fresh()->suppression_reason);
        $newMail->send($mailer);
        $newMail->send($mailer);
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertNotNull($newRow->fresh()->transport_accepted_at);
        $this->assertSame(1, $newRow->fresh()->delivery_attempts);
        $this->assertSame(1, $this->reopenAudits($handover));
    }

    public function test_renewed_release_requires_current_agreement_and_delivery_clearance(): void
    {
        [, $staff, $handover] = $this->placement();
        $oldVersion = $handover->schedule_version;
        $this->reopen($staff, $handover)->assertSessionHas('toast.type', 'success');
        $this->actingAs($staff)->post(route('admin.handover.schedule', $handover), [
            'schedule_version' => $oldVersion, 'method' => 'delivery',
            'date' => now('Asia/Manila')->addDay()->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00',
        ])->assertSessionHasErrors('schedule');
        $payload = ['release_method' => 'delivery', 'release_date' => now('Asia/Manila')->toDateString(), 'release_time' => '09:00',
            'staff_id' => $staff->id, 'courier_provider' => 'pet_to_go_express', 'tracking_number' => 'SYNTHETIC', 'tracking_url' => 'https://courier.example.test/test'];
        $this->post(route('admin.handover.release', $handover), $payload)->assertSessionHasErrors('schedule');
        $this->prepareHandover($handover->refresh(), $staff);
        $handover->forceFill(['delivery_recipient_available' => null])->save();
        $this->post(route('admin.handover.release', $handover), $payload)->assertSessionHasErrors();
        $this->assertNull($handover->fresh()->released_at);
        $this->prepareDeliveryClearance($handover, $staff);
        $this->post(route('admin.handover.release', $handover), $payload)->assertSessionHas('toast.type', 'success');
        $this->assertSame(1, $handover->fresh()->reopen_count);
        $this->assertSame(1, AuditLog::where('action', 'handover.released')->count());
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public function test_completed_receipt_and_proof_cannot_be_reset_by_reopening(): void
    {
        [, $staff, $handover] = $this->placement();
        $receipt = UploadedFile::fake()->image('receipt.png')->store('handover-receipts', 'local');
        $handover->forceFill(['received_at' => now(), 'adopter_confirmed_at' => now(), 'adopter_outcome' => 'received',
            'receipt_proof_disk' => 'local', 'receipt_proof_path' => $receipt, 'receipt_proof_hash' => hash_file('sha256', Storage::disk('local')->path($receipt))])->save();
        $before = $handover->refresh()->getAttributes();
        $this->reopen($staff, $handover)->assertSessionHasErrors('reason');
        $this->assertSame($before, $handover->fresh()->getAttributes());
        Storage::disk('local')->assertExists($receipt);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public static function deniedActors(): array
    {
        return [['Adopter', true, true], ['Administrator', false, true], ['Volunteer', false, true], ['Volunteer', true, false]];
    }

    #[DataProvider('deniedActors')]
    public function test_ineligible_actor_cannot_reopen(string $role, bool $active, bool $verified): void
    {
        [, , $handover] = $this->placement();
        $actor = User::factory()->create(['role' => $role, 'is_active' => $active, 'email_verified_at' => $verified ? now() : null]);
        $before = $handover->refresh()->getAttributes();
        $this->reopen($actor, $handover)->assertRedirect();
        $this->assertSame($before, $handover->fresh()->getAttributes());
        $this->assertDatabaseCount('handover_email_outbox', 0);
        Mail::assertNothingQueued();
    }

    public static function invalidReasons(): array
    {
        return [[['array']], [str_repeat('x', 501)], [true]];
    }

    #[DataProvider('invalidReasons')]
    public function test_existing_reason_type_and_length_validation_are_preserved(mixed $reason): void
    {
        [, $staff, $handover] = $this->placement();
        $before = $handover->refresh()->getAttributes();
        $this->reopen($staff, $handover, ['reason' => $reason])->assertSessionHasErrors('reason');
        $this->assertSame($before, $handover->fresh()->getAttributes());
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public function test_independent_mysql_connection_cannot_observe_partial_reopening(): void
    {
        $this->requireMysql();
        [, $staff, $handover] = $this->placement();
        $observer = $this->observer();
        $this->assertNotSame(DB::getPdo(), $observer->getPdo());
        $stageObserved = false;
        Event::listen('eloquent.creating: '.HandoverEmailOutbox::class, function () use ($observer, $handover, &$stageObserved): void {
            $this->assertGreaterThan(0, DB::transactionLevel());
            $this->assertNotNull($observer->table('handovers')->where('id', $handover->id)->value('released_at'));
            $this->assertSame(0, $observer->table('handovers')->where('id', $handover->id)->value('reopen_count'));
            $this->assertSame(0, $observer->table('handover_notifications')->count());
            $this->assertSame(0, $observer->table('handover_email_outbox')->count());
            $this->assertSame(0, $observer->table('audit_logs')->where('action', 'handover.reopened')->count());
            $stageObserved = true;
        });
        Mail::shouldReceive('to')->once()->andReturnUsing(function () use ($observer, $handover): never {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertNull($observer->table('handovers')->where('id', $handover->id)->value('released_at'));
            $this->assertSame(1, $observer->table('handover_notifications')->count());
            $this->assertSame(1, $observer->table('handover_email_outbox')->count());
            throw new RuntimeException('Synthetic post-commit queue failure');
        });
        $this->reopen($staff, $handover)->assertSessionHas('toast.type', 'success');
        $this->assertTrue($stageObserved);
    }

    public static function concurrentRequests(): array
    {
        return [['reopen', false], ['reopen', true], ['receipt', false], ['receipt', true]];
    }

    #[DataProvider('concurrentRequests')]
    public function test_real_mysql_requests_serialize_reopening_or_receipt_against_commit_and_rollback(string $secondAction, bool $rollback): void
    {
        $this->requireMysql();
        [$owner, $staff, $handover] = $this->placement();
        $directory = sys_get_temp_dir().'/pairfect-reopen-'.Str::uuid();
        mkdir($directory);
        $receipt = UploadedFile::fake()->image('process-receipt.jpg')->store('process-fixtures', 'local');
        $common = ['handover_id' => $handover->id, 'clock' => now()->toIso8601String(), 'storage_root' => Storage::disk('local')->path('')];
        $first = $this->process($common + ['action' => 'reopen', 'user_id' => $staff->id, 'pause_directory' => $directory, 'fail_outbox' => $rollback]);
        $second = $this->process($common + ['action' => $secondAction, 'user_id' => $secondAction === 'receipt' ? $owner->id : $staff->id,
            'receipt_path' => Storage::disk('local')->path($receipt), 'started_file' => $directory.'/second-started']);
        try {
            $first->start();
            $this->waitFor(fn () => is_file($directory.'/ready') || ! $first->isRunning());
            $this->assertFileExists($directory.'/ready', $first->getErrorOutput().$first->getOutput());
            $this->assertNotNull($this->observer()->table('handovers')->where('id', $handover->id)->value('released_at'));
            $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
            $this->assertDatabaseCount('jobs', 0);
            $second->start();
            $this->waitFor(fn () => is_file($directory.'/second-started') || ! $second->isRunning());
            $this->assertFileExists($directory.'/second-started', $second->getErrorOutput().$second->getOutput());
            $this->waitFor(fn () => $this->observer()->table('performance_schema.data_lock_waits')->count() > 0);
            touch($directory.'/continue');
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
            $a = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $b = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($rollback ? 500 : 302, $a['status']);
            $this->assertSame($rollback ? 302 : 422, $b['status']);
            $this->assertSame(0, $a['transaction_level']);
            $this->assertSame(0, $b['transaction_level']);
            $receiptWins = $rollback && $secondAction === 'receipt';
            $this->assertSame($receiptWins ? 0 : 1, $handover->fresh()->reopen_count);
            $this->assertSame($receiptWins ? 0 : 1, $this->reopenAudits($handover));
            $this->assertSame($receiptWins ? 'received' : null, $handover->fresh()->adopter_outcome);
            $this->assertDatabaseCount('post_adoption_logs', $receiptWins ? 3 : 0);
            $this->assertDatabaseCount('handover_email_outbox', 1);
            $this->assertSame($receiptWins ? 'completed' : 'reopened', HandoverEmailOutbox::sole()->kind);
            $this->assertSame(1, HandoverNotification::where('kind', $receiptWins ? 'completed' : 'reopened')->count());
            $this->assertDatabaseCount('jobs', 1);
            Mail::swap(new MailManager($this->app));
            $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'emails', '--once' => true])->assertSuccessful();
            $this->assertDatabaseCount('jobs', 0);
            $this->assertNotNull(HandoverEmailOutbox::sole()->transport_accepted_at);
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

    private function placement(Role $role = Role::Administrator): array
    {
        $owner = User::factory()->create(['role' => Role::Adopter, 'is_active' => true, 'email_verified_at' => now()]);
        $staff = User::factory()->create(['role' => $role, 'is_active' => true, 'email_verified_at' => now()]);
        $pet = Pet::create(['name' => 'Synthetic Reopening Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now()]);
        $handover = Handover::create(['code' => 'REOPEN-'.Str::uuid(), 'application_id' => $application->id, 'user_id' => $owner->id, 'pet_id' => $pet->id, 'approved_at' => now(), 'history' => []]);
        $this->prepareHandover($handover, $staff);
        $proof = UploadedFile::fake()->image('original-release.jpg')->store('handover-proofs', 'local');
        $handover->forceFill(['released_at' => now()->subHour(), 'release_method' => 'delivery', 'release_date' => now('Asia/Manila')->toDateString(),
            'release_time' => '08:00', 'courier' => 'Pet To Go Express', 'tracking_number' => 'SYNTHETIC-ORIGINAL',
            'tracking_url' => 'https://courier.example.test/original', 'proof_path' => $proof, 'proof_name' => 'original-release.jpg',
            'release_recorded_by_user_id' => $staff->id, 'release_handled_by_user_id' => $staff->id, 'staff_name' => $staff->full_name,
            'history' => [['at' => now()->subHour()->toIso8601String(), 'label' => 'Original authorized release', 'actor' => $staff->full_name]],
            'reminders' => [['channel' => 'Email', 'at' => now()->subMinutes(30)->toIso8601String()]],
            ...array_fill_keys(HandoverScheduleService::MARKERS, now()->subMinutes(30))])->save();

        return [$owner, $staff, $handover->refresh()];
    }

    private function reopen(User $staff, Handover $handover, array $input = ['reason' => 'Synthetic authorized new attempt'])
    {
        return $this->actingAs($staff)->post(route('admin.handover.reopen', $handover), $input);
    }

    private function assertReopened(Handover $handover, array $before): void
    {
        $handover->refresh();
        $this->assertSame(0, DB::transactionLevel());
        $this->assertNull($handover->released_at);
        $this->assertNull($handover->release_method);
        $this->assertNull($handover->tracking_url);
        $this->assertNull($handover->received_at);
        $this->assertNull($handover->adopter_outcome);
        $this->assertSame((int) $before['reopen_count'] + 1, $handover->reopen_count);
        $this->assertSame((int) $before['schedule_version'] + 1, $handover->schedule_version);
        $this->assertSame('unscheduled', $handover->schedule_status);
        $this->assertNull($handover->schedule_confirmed_at);
        $this->assertSame([], $handover->reminders);
        foreach (HandoverScheduleService::MARKERS as $marker) {
            $this->assertNull($handover->{$marker});
        }
        $this->assertCount(count(json_decode($before['history'], true)) + 2, $handover->history);
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    private function reopenAudits(Handover $handover): int
    {
        return AuditLog::where('action', 'handover.reopened')->where('entity_id', $handover->id)->count();
    }

    private function requireMysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Independent visibility/contention is tested only on disposable MySQL, not SQLite.');
        }
    }

    private function observer()
    {
        config(['database.connections.reopening_observer' => config('database.connections.mysql')]);

        return DB::connection('reopening_observer');
    }

    private function process(array $input): Process
    {
        return new Process([PHP_BINARY, base_path('tests/Support/handover-reopening-process.php'), base64_encode(json_encode($input, JSON_THROW_ON_ERROR))], base_path(), timeout: 45);
    }

    private function waitFor(callable $condition): void
    {
        $deadline = microtime(true) + 15;
        do {
            if ($condition()) {
                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);
        $this->fail('Timed out waiting for the real MySQL request/barrier/lock contention.');
    }
}
