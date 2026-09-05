<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Enums\Species;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_reports_delivery_health_without_exposing_credentials_or_changing_records(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 08:00:00', 'Asia/Manila');
        $this->configureHealthySmtpAndDatabaseQueue();

        User::factory()->create([
            'email' => 'admin@pairfectpaws.com',
            'role' => Role::Administrator,
        ]);
        User::factory()->create([
            'email' => 'real-volunteer@example.test',
            'role' => Role::Volunteer,
        ]);
        $adopter = User::factory()->unverified()->create([
            'email' => 'unverified-adopter@example.test',
        ]);
        $log = $this->dueLogFor($adopter);

        $now = now()->timestamp;
        DB::table('jobs')->insert([
            [
                'queue' => 'emails',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now,
            ],
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now,
            ],
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'emails',
            'payload' => '{}',
            'exception' => 'Test failure',
            'failed_at' => now(),
        ]);

        $before = PostAdoptionLog::findOrFail($log->id)->getAttributes();
        $exitCode = Artisan::call('email:health');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Mailer: smtp', $output);
        $this->assertStringContainsString('SMTP host: smtp.gmail.com', $output);
        $this->assertStringContainsString('SMTP username configured: Yes', $output);
        $this->assertStringContainsString('SMTP password configured: Yes', $output);
        $this->assertStringNotContainsString('health-check-secret', $output);
        $this->assertStringContainsString('Staff alert address: staff-alerts@example.test', $output);
        $this->assertStringContainsString('Pending emails jobs: 1', $output);
        $this->assertStringContainsString('Pending default jobs: 1', $output);
        $this->assertStringContainsString('Failed jobs: 1', $output);
        $this->assertStringContainsString('Reminder task registered: Yes', $output);
        $this->assertStringContainsString('Next due (Asia/Manila):', $output);
        $this->assertStringContainsString('Active verified staff accounts: real-volunteer@example.test', $output);
        $this->assertStringNotContainsString('Active verified staff accounts: admin@pairfectpaws.com', $output);
        $this->assertStringContainsString('Effective staff recipients: real-volunteer@example.test, staff-alerts@example.test', $output);
        $this->assertStringContainsString('Due logs with unverified adopters: 1', $output);
        $this->assertStringContainsString("Blocked log IDs: #{$log->id}", $output);
        $this->assertStringContainsString('Result: READY WITH OPERATIONAL WARNINGS', $output);
        $this->assertSame($before, PostAdoptionLog::findOrFail($log->id)->getAttributes());
    }

    public function test_existing_failed_jobs_and_a_sync_queue_are_warnings_not_command_failures(): void
    {
        config([
            'mail.default' => 'array',
            'mail.from.address' => 'sender@example.test',
            'mail.staff_alert_address' => 'staff@example.test',
            'queue.default' => 'sync',
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'sync',
            'queue' => 'emails',
            'payload' => '{}',
            'exception' => 'Prior failure',
            'failed_at' => now(),
        ]);

        $exitCode = Artisan::call('email:health');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Active verified staff accounts: None', $output);
        $this->assertStringContainsString('Effective staff recipients: staff@example.test', $output);
        $this->assertStringContainsString('WARN: The sync queue processes mail during the web request and is not durable.', $output);
        $this->assertStringContainsString('WARN: 1 failed queue job(s) require review.', $output);
        $this->assertStringContainsString('Result: READY WITH OPERATIONAL WARNINGS', $output);
    }

    public function test_it_returns_failure_for_actionable_mail_configuration_errors(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '',
            'mail.mailers.smtp.port' => null,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.mailers.smtp.url' => null,
            'mail.from.address' => 'not-an-email',
            'mail.staff_alert_address' => 'also-not-an-email',
            'queue.default' => 'sync',
        ]);

        $exitCode = Artisan::call('email:health');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('FAIL: The SMTP host is not configured.', $output);
        $this->assertStringContainsString('FAIL: The SMTP password is not configured.', $output);
        $this->assertStringContainsString('FAIL: The global from address is missing or invalid.', $output);
        $this->assertStringContainsString('FAIL: The configured staff alert address is invalid.', $output);
        $this->assertStringContainsString('FAIL: No valid staff alert recipient is configured or active.', $output);
        $this->assertStringContainsString('Result: CONFIGURATION ERROR', $output);
    }

    private function configureHealthySmtpAndDatabaseQueue(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'health-user@example.test',
            'mail.mailers.smtp.password' => 'health-check-secret',
            'mail.from.address' => 'sender@example.test',
            'mail.staff_alert_address' => 'staff-alerts@example.test',
            'mail.staff_notification_excluded_addresses' => [
                'admin@pairfectpaws.com',
                'volunteer@pairfectpaws.com',
            ],
            'queue.default' => 'database',
            'queue.connections.database.connection' => null,
            'queue.connections.database.table' => 'jobs',
            'queue.connections.database.queue' => 'default',
        ]);
    }

    private function dueLogFor(User $adopter): PostAdoptionLog
    {
        $pet = Pet::create([
            'name' => 'Milo',
            'species' => Species::Dog,
            'availability_status' => AvailabilityStatus::Adopted,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => ApplicationStatus::Approved,
            'adopted_at' => '2026-08-20 00:00:00',
        ]);

        return PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => '2026-08-23',
        ]);
    }
}
