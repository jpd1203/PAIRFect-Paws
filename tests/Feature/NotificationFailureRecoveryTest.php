<?php

namespace Tests\Feature;

use App\Jobs\RecoverFailedAuthNotification;
use App\Jobs\RecoverFailedMail;
use App\Mail\TransactionalMail;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class NotificationFailureRecoveryTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    private function recovery(): object
    {
        $payload = json_decode(DB::table('failed_jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);

        return unserialize(Crypt::decrypt($payload['data']['command']));
    }

    private function restoreMail(): void
    {
        Mail::swap(new MailManager($this->app));
        Mail::fake();
    }

    private function restoreNotifications(): void
    {
        Notification::swap(new ChannelManager($this->app));
        Notification::fake();
    }

    public function test_non_handover_queue_failure_has_encrypted_durable_manual_recovery(): void
    {
        $user = $this->matchingUser();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('PRIVATE recipient@example.test'));
        app(EmailNotificationService::class)->user($user, 'Synthetic subject', 'Synthetic heading', ['Synthetic body']);
        $this->assertDatabaseCount('failed_jobs', 1);
        $row = DB::table('failed_jobs')->sole();
        $this->assertStringNotContainsString($user->email, $row->payload);
        $this->assertStringNotContainsString('PRIVATE', $row->exception);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $this->recovery());
        $this->assertInstanceOf(RecoverFailedMail::class, $this->recovery());
    }

    public function test_failed_record_exception_omits_stack_arguments_regardless_of_php_trace_settings(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        config(['mail.staff_alert_address' => 'private-alert@example.test']);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('PRIVATE provider details'));
        try {
            app(EmailNotificationService::class)->staff('Synthetic', 'Synthetic', ['Synthetic']);
            $record = DB::table('failed_jobs')->sole();
            $this->assertSame('Email dispatch failed: '.RuntimeException::class, $record->exception);
            $this->assertStringNotContainsString('private-alert@example.test', $record->payload);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function test_auth_transport_failure_is_retained_without_failing_the_caller(): void
    {
        $user = $this->matchingUser();
        $user->forceFill(['email_verified_at' => null])->save();
        Notification::shouldReceive('send')->andThrow(new TransportException('PRIVATE recipient@example.test'));
        $error = null;
        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportException $exception) {
            $error = $exception;
        }
        $this->assertNull($error, 'Provider failure must be archived instead of failing registration/resend.');
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertInstanceOf(RecoverFailedAuthNotification::class, $this->recovery());
    }

    public function test_manual_retry_runs_a_real_disposable_database_worker_and_cannot_requeue_a_removed_failure(): void
    {
        config(['queue.default' => 'database']);
        $user = $this->matchingUser();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Synthetic queue unavailable'));
        app(EmailNotificationService::class)->user($user, 'Synthetic', 'Synthetic', ['Synthetic']);
        $uuid = DB::table('failed_jobs')->sole()->uuid;
        $key = $this->recovery()->mail->deliveryIdempotencyKey;
        $this->restoreMail();
        $this->artisan('queue:retry', ['id' => [$uuid]])->assertSuccessful();
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:retry', ['id' => [$uuid]])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'emails', '--once' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertSent(TransactionalMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->deliveryIdempotencyKey === $key);
        Mail::assertSentCount(1);
    }

    public function test_abandoned_database_job_reservation_is_available_after_retry_after_without_sending_early(): void
    {
        config(['queue.default' => 'database']);
        $user = $this->matchingUser();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Synthetic initial enqueue failure'));
        app(EmailNotificationService::class)->user($user, 'Synthetic', 'Synthetic', ['Synthetic']);
        $uuid = DB::table('failed_jobs')->sole()->uuid;
        $this->restoreMail();
        $this->artisan('queue:retry', ['id' => [$uuid]])->assertSuccessful();
        $connection = Queue::connection('database');
        $abandoned = $connection->pop('emails'); // Simulate interruption before handle/delete.
        $this->assertNotNull($abandoned);
        $this->assertNull($connection->pop('emails'));
        Mail::assertNothingSent();
        $this->travel((int) config('queue.connections.database.retry_after') + 1)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'emails', '--once' => true, '--tries' => 3])->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public static function providerFailures(): array
    {
        return [['Synthetic provider timeout'], ['Synthetic provider rejected message']];
    }

    #[DataProvider('providerFailures')]
    public function test_recovery_job_rethrows_redacted_provider_failures_and_can_retry_with_same_key(string $reason): void
    {
        $user = $this->matchingUser();
        $mail = new TransactionalMail('Synthetic', 'Synthetic', ['Synthetic']);
        $job = new RecoverFailedMail($mail, $user->email, $user->id, $user->role->value);
        $key = $mail->deliveryIdempotencyKey;
        Mail::shouldReceive('to')->andThrow(new TransportException($reason.' PRIVATE '.$user->email));
        try {
            $job->handle();
            $this->fail('Provider rejection must reach the worker.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(TransportException::class, $exception->getMessage());
            $this->assertStringNotContainsString('PRIVATE', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->restoreMail();
        $job->handle();
        Mail::assertSent(TransactionalMail::class, fn ($sent) => $sent->deliveryIdempotencyKey === $key);
    }

    public function test_recovery_skips_changed_recipient_role_or_email(): void
    {
        $user = $this->matchingUser();
        $job = new RecoverFailedMail(new TransactionalMail('Synthetic', 'Synthetic', ['Synthetic']), $user->email, $user->id, $user->role->value);
        $user->update(['email' => 'changed@example.test']);
        Mail::fake();
        $job->handle();
        Mail::assertNothingSent();
    }

    public function test_dispatch_accepts_new_users_whose_default_role_is_not_loaded_in_memory(): void
    {
        $user = User::create(['first_name' => 'Synthetic', 'last_name' => 'Default role',
            'email' => 'default-role@example.test', 'password' => 'Password123!', 'email_verified_at' => now()]);
        $this->assertNull($user->role);
        Mail::fake();
        app(EmailNotificationService::class)->user($user, 'Synthetic', 'Synthetic', ['Synthetic']);
        Mail::assertQueuedCount(1);
    }

    public function test_expired_password_token_is_not_resurrected_by_recovery(): void
    {
        $user = $this->matchingUser();
        $token = Password::broker()->createToken($user);
        Notification::shouldReceive('send')->andThrow(new TransportException('Synthetic timeout'));
        $user->sendPasswordResetNotification($token);
        $job = $this->recovery();
        $raw = DB::table('failed_jobs')->sole()->payload;
        $this->assertStringNotContainsString($token, $raw);
        $this->assertStringNotContainsString($user->email, $raw);
        $this->restoreNotifications();
        $this->travel(61)->minutes();
        $job->handle();
        Notification::assertNothingSent();
    }

    public function test_valid_auth_recovery_uses_existing_notification_and_suppresses_already_verified_user(): void
    {
        $user = $this->matchingUser();
        $user->forceFill(['email_verified_at' => null])->save();
        Notification::shouldReceive('send')->andThrow(new TransportException('Synthetic rejection'));
        $user->sendEmailVerificationNotification();
        $job = $this->recovery();
        $this->restoreNotifications();
        $job->handle();
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
        $this->restoreNotifications();
        $user->markEmailAsVerified();
        $job->handle();
        Notification::assertNothingSent();
    }

    public function test_failed_password_email_does_not_fail_the_request_or_expose_account_existence(): void
    {
        $user = $this->matchingUser();
        Notification::shouldReceive('send')->andThrow(new TransportException('Synthetic provider timeout'));
        $known = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.request'));
        $message = session('status');
        $known->assertSessionHasNoErrors();
        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirect(route('password.request'))->assertSessionHas('status', $message);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_failure_storage_unavailable_is_explicit_instead_of_silently_losing_the_email(): void
    {
        $user = $this->matchingUser();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('PRIVATE queue unavailable'));
        $failureStore = Mockery::mock();
        $failureStore->shouldReceive('log')->andReturnNull();
        $this->app->instance('queue.failer', $failureStore);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email recovery storage is unavailable.');
        app(EmailNotificationService::class)->user($user, 'Synthetic', 'Synthetic', ['Synthetic']);
    }

    public function test_registration_provider_failure_preserves_the_account_and_verification_requirement(): void
    {
        Notification::shouldReceive('send')->andThrow(new TransportException('Synthetic provider timeout'));
        $this->post(route('register.store'), [
            'first_name' => 'Synthetic', 'last_name' => 'Registration', 'email' => 'registration@example.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'terms_accepted' => '1', 'privacy_consent' => '1',
            'region_code' => '1300000000', 'province_code' => '__direct__',
            'city_municipality_code' => '1380600000', 'barangay_code' => '1380606197',
            'street_address' => 'Synthetic address', 'zip_code' => '1016',
        ])->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();
        $user = User::where('email', 'registration@example.test')->sole();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_deferred_dispatch_failure_is_caught_after_commit_and_rollback_does_not_archive(): void
    {
        $user = $this->matchingUser();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Synthetic queue unavailable'));
        DB::beginTransaction();
        app(EmailNotificationService::class)->user($user, 'Synthetic', 'Synthetic', ['Synthetic']);
        $this->assertDatabaseCount('failed_jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('failed_jobs', 0);
        DB::beginTransaction();
        app(EmailNotificationService::class)->user($user, 'Synthetic', 'Synthetic', ['Synthetic']);
        DB::commit();
        $this->assertDatabaseCount('failed_jobs', 1);
    }
}
