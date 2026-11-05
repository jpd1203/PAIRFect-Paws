<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\HandoverEmailOutbox;
use App\Models\HandoverNotification;
use App\Models\Pet;
use App\Models\User;
use App\Services\HandoverDeliveryClearanceService;
use App\Services\HandoverMailOutboxService;
use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\PreparesVerifiedHandovers;
use Tests\TestCase;

class HandoverDeliveryClearanceTest extends TestCase
{
    use BuildsMatchingFixtures, PreparesVerifiedHandovers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        config(['mail.staff_alert_address' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'Asia/Manila')->utc());
    }

    private function placement(string $method = 'delivery'): array
    {
        $owner = $this->matchingUser();
        $staff = $this->matchingUser(Role::Administrator);
        $pet = Pet::create(['name' => 'Synthetic Delivery Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now()]);
        $handover = Handover::create([
            'code' => 'DEL-'.Str::uuid(), 'application_id' => $application->id, 'pet_id' => $pet->id,
            'user_id' => $owner->id, 'adopter_name' => $owner->full_name,
            'adopter_address' => '123 Private Synthetic Street, Test Barangay, Manila',
            'approved_at' => now(), 'history' => [], 'scheduled_method' => $method,
            'scheduled_start_at' => now()->addDay(), 'scheduled_end_at' => now()->addDay()->addHours(2),
            'schedule_status' => 'confirmed', 'schedule_version' => 1, 'schedule_confirmed_at' => now(),
            'schedule_confirmed_by_user_id' => $owner->id,
        ]);
        $this->verifiedIdentity($application, $staff);
        if ($method === 'pickup') {
            $this->verifiedIdentity($application, $staff, 'pickup_handover');
        }

        return [$owner, $staff, $handover->fresh()];
    }

    private function revision(Handover $handover): array
    {
        $handover->refresh();

        return ['schedule_version' => $handover->schedule_version, 'clearance_version' => $handover->delivery_clearance_version,
            'destination_fingerprint' => app(HandoverDeliveryClearanceService::class)->destinationFingerprint($handover)];
    }

    private function verify(User $staff, Handover $handover, array $extra = []): void
    {
        $this->actingAs($staff)->post(route('admin.handover.delivery.address', $handover),
            array_replace($this->revision($handover), ['status' => 'verified'], $extra))->assertSessionHasNoErrors();
    }

    private function readiness(User $staff, Handover $handover, array $extra = []): void
    {
        $this->actingAs($staff)->post(route('admin.handover.delivery.readiness', $handover),
            array_replace($this->revision($handover), ['recipient' => 'adopter', 'available' => 1, 'contact_method' => 'phone'], $extra))
            ->assertSessionHasNoErrors();
    }

    private function releaseData(User $staff): array
    {
        return ['release_method' => 'delivery', 'release_date' => ManilaTime::now()->toDateString(), 'release_time' => '09:00',
            'staff_id' => $staff->id, 'courier_provider' => 'pet_to_go_express', 'tracking_number' => 'SYNTHETIC-BOOKING',
            'tracking_url' => 'https://courier.example.test/private/synthetic-link'];
    }

    public static function staffRoles(): array
    {
        return [['Administrator'], ['Volunteer']];
    }

    #[DataProvider('staffRoles')]
    public function test_active_verified_staff_review_the_snapshot_and_server_records_actor_and_time(string $role): void
    {
        [$owner, , $handover] = $this->placement();
        $staff = $this->matchingUser(Role::from($role));
        $address = $handover->adopter_address;
        $owner->update(['street_address' => 'Different current profile address']);
        $this->verify($staff, $handover, ['delivery_address_verified_by_user_id' => $owner->id,
            'delivery_address_verified_at' => '1990-01-01', 'adopter_address' => 'Injected destination']);
        $handover->refresh();
        $this->assertTrue(app(HandoverDeliveryClearanceService::class)->addressIsVerified($handover));
        $this->assertSame($address, $handover->adopter_address);
        $this->assertSame($staff->id, $handover->delivery_address_verified_by_user_id);
        $this->assertTrue($handover->delivery_address_verified_at->equalTo(now()));
        $this->assertNull($handover->delivery_recipient_available);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.address_verified', 'user_id' => $staff->id]);
        $this->assertStringNotContainsString($address, AuditLog::where('action', 'delivery.address_verified')->sole()->notes);
        $this->actingAs($staff)->get(route('admin.handover.show', $handover))->assertOk()
            ->assertSee($address)->assertSee('Verified for this destination')->assertSee($staff->full_name)
            ->assertSee('Nov 5, 2026 9:00 AM')->assertSee('Nov 5, 2026 · 9:00 AM')->assertSee('Current readiness not established');
    }

    public static function deniedActors(): array
    {
        return [['Adopter', true, true], ['Volunteer', false, true], ['Administrator', false, true], ['Volunteer', true, false]];
    }

    #[DataProvider('deniedActors')]
    public function test_clearance_endpoints_and_service_block_unauthorized_actors(string $role, bool $active, bool $verified): void
    {
        [, , $handover] = $this->placement();
        $actor = $this->matchingUser(Role::from($role));
        $actor->forceFill(['is_active' => $active, 'email_verified_at' => $verified ? now() : null])->save();
        foreach (['admin.handover.delivery.address', 'admin.handover.delivery.readiness'] as $route) {
            $this->actingAs($actor)->post(route($route, $handover), $this->revision($handover) + ['status' => 'verified', 'recipient' => 'adopter', 'available' => 1, 'contact_method' => 'phone'])->assertRedirect();
        }
        try {
            app(HandoverDeliveryClearanceService::class)->verifyAddress($handover, $actor, $this->revision($handover) + ['status' => 'verified']);
            $this->fail('Unauthorized service invocation must not succeed.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertNull($handover->fresh()->delivery_address_status);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public function test_missing_address_or_clearance_blocks_delivery_and_frontend_flags_cannot_bypass_it(): void
    {
        [, $staff, $handover] = $this->placement();
        $url = route('admin.handover.release', $handover);
        $forged = ['delivery_address_status' => 'verified', 'delivery_recipient_available' => true,
            'delivery_address_verified_by_user_id' => $staff->id, 'delivery_contacted_at' => now()->toDateTimeString()];
        $this->actingAs($staff)->post($url, $this->releaseData($staff) + $forged)->assertSessionHasErrors('delivery_address');
        $this->verify($staff, $handover);
        $this->post($url, $this->releaseData($staff) + $forged)->assertSessionHasErrors('delivery_readiness');
        $handover->update(['adopter_address' => '']);
        $this->post(route('admin.handover.delivery.address', $handover), $this->revision($handover) + ['status' => 'verified'])->assertSessionHasErrors('delivery_address');
        $this->assertNull($handover->fresh()->released_at);
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public function test_readiness_requires_address_verification_and_explicit_valid_contact_data(): void
    {
        [, $staff, $handover] = $this->placement();
        $url = route('admin.handover.delivery.readiness', $handover);
        $payload = $this->revision($handover) + ['recipient' => 'adopter', 'available' => 1, 'contact_method' => 'phone'];
        $this->actingAs($staff)->post($url, $payload)->assertSessionHasErrors('delivery_address');
        $this->verify($staff, $handover);
        foreach ([[], ['recipient' => 'delegate', 'available' => 1, 'contact_method' => 'phone'],
            ['recipient' => 'adopter', 'available' => 1, 'contact_method' => 'other'],
            ['recipient' => 'adopter', 'available' => 'perhaps', 'contact_method' => 'automatic_reminder']] as $input) {
            $this->post($url, $this->revision($handover) + $input)->assertSessionHasErrors();
        }
        $this->assertNull($handover->fresh()->delivery_contacted_at);
    }

    public function test_explicit_unavailability_and_revoked_address_block_release(): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover, ['available' => 0]);
        $this->assertFalse($handover->fresh()->delivery_recipient_available);
        $this->post(route('admin.handover.release', $handover), $this->releaseData($staff))->assertSessionHasErrors('delivery_readiness');
        $this->assertDatabaseCount('handover_email_outbox', 0);
        $this->readiness($staff, $handover);
        $this->verify($staff, $handover, ['status' => 'needs_review']);
        $this->post(route('admin.handover.release', $handover), $this->releaseData($staff))->assertSessionHasErrors('delivery_address');
        $this->verify($staff, $handover);
        $this->assertNull($handover->fresh()->delivery_recipient_available);
        $this->post(route('admin.handover.release', $handover), $this->releaseData($staff))->assertSessionHasErrors('delivery_readiness');
    }

    public function test_readiness_metadata_and_note_are_recorded_by_server_not_the_request(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover, ['contact_method' => 'other', 'notes' => '<script>syntheticOnly()</script>',
            'delivery_contacted_by_user_id' => $owner->id, 'delivery_contacted_at' => '1990-01-01',
            'delivery_readiness_schedule_version' => 9999]);
        $handover->refresh();
        $this->assertSame($staff->id, $handover->delivery_contacted_by_user_id);
        $this->assertTrue($handover->delivery_contacted_at->equalTo(now()));
        $this->assertSame($handover->schedule_version, $handover->delivery_readiness_schedule_version);
        $this->assertSame($handover->reopen_count, $handover->delivery_readiness_release_attempt);
        $this->assertTrue(app(HandoverDeliveryClearanceService::class)->readinessIsCurrent($handover));
        $this->get(route('admin.handover.show', $handover))->assertOk()
            ->assertSee('<script>syntheticOnly()</script>')->assertDontSee('<script>syntheticOnly()</script>', false);
        $this->assertArrayNotHasKey('delivery_readiness_notes', $handover->toArray());
    }

    public function test_address_changes_invalidate_verification_even_after_the_original_address_is_restored(): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        $original = $handover->adopter_address;
        $oldForm = $this->revision($handover);
        $handover->update(['adopter_address' => '456 Different Synthetic Destination']);
        $handover->update(['adopter_address' => $original]);
        $this->assertFalse(app(HandoverDeliveryClearanceService::class)->addressIsVerified($handover->fresh()));
        $this->assertFalse(app(HandoverDeliveryClearanceService::class)->readinessIsCurrent($handover));
        $this->actingAs($staff)->post(route('admin.handover.delivery.address', $handover), $oldForm + ['status' => 'verified'])->assertSessionHasErrors('delivery_readiness');
        $this->post(route('admin.handover.release', $handover), $this->releaseData($staff))->assertSessionHasErrors('delivery_address');
    }

    public static function staleChanges(): array
    {
        return [['schedule_version'], ['scheduled_method'], ['scheduled_end_at'], ['reopen_count'], ['schedule_confirmed_by_user_id'], ['adopter_address']];
    }

    #[DataProvider('staleChanges')]
    public function test_current_clearance_cannot_survive_changed_delivery_agreement(string $field): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        $value = match ($field) {
            'schedule_version' => $handover->schedule_version + 1,
            'scheduled_method' => 'pickup', 'scheduled_end_at' => now()->addWeek(),
            'reopen_count' => 1, 'schedule_confirmed_by_user_id' => $staff->id,
            'adopter_address' => 'Changed exact snapshot',
        };
        // Direct DB updates additionally prove the fingerprint checks do not rely on model events alone.
        DB::table('handovers')->where('id', $handover->id)->update([$field => $value]);
        $this->assertFalse(app(HandoverDeliveryClearanceService::class)->readinessIsCurrent($handover->fresh()));
        $response = $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff));
        $response->assertSessionHasErrors();
        $this->assertNull($handover->fresh()->released_at);
    }

    public function test_repeated_forms_are_versioned_and_idempotent_without_duplicate_notifications(): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $addressState = $handover->fresh()->getAttributes();
        $this->verify($staff, $handover);
        $this->assertSame($addressState, $handover->fresh()->getAttributes());
        $staleForm = $this->revision($handover) + ['recipient' => 'adopter', 'available' => 1, 'contact_method' => 'phone'];
        $this->readiness($staff, $handover);
        $state = $handover->fresh()->getAttributes();
        $this->readiness($staff, $handover);
        $this->assertSame($state, $handover->fresh()->getAttributes());
        $this->post(route('admin.handover.delivery.readiness', $handover), $staleForm)->assertSessionHasErrors('delivery_readiness');
        $this->assertSame(1, HandoverNotification::where('kind', 'delivery_pre_dispatch')->count());
        $this->assertSame(1, HandoverEmailOutbox::where('kind', 'delivery_pre_dispatch')->count());
        $this->assertSame(1, AuditLog::where('action', 'delivery.readiness_confirmed')->count());
    }

    public static function releaseTimes(): array
    {
        return [['early', true], ['during', true], ['end', true], ['expired', false]];
    }

    #[DataProvider('releaseTimes')]
    public function test_delivery_release_preserves_expiry_only_boundary_and_duplicate_protection(string $time, bool $allowed): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        $instant = match ($time) {
            'early' => $handover->scheduled_start_at->copy()->subHour(),
            'during' => $handover->scheduled_start_at->copy()->addHour(),
            'end' => $handover->scheduled_end_at,
            'expired' => $handover->scheduled_end_at->copy()->addSecond(),
        };
        $this->travelTo($instant);
        $payload = $this->releaseData($staff) + ['proof' => UploadedFile::fake()->image('synthetic-proof.png')];
        $response = $this->actingAs($staff)->post(route('admin.handover.release', $handover), $payload);
        if ($allowed) {
            $response->assertSessionHasNoErrors();
            $this->assertNotNull($handover->fresh()->released_at);
            $state = $handover->refresh()->getAttributes();
            $this->post(route('admin.handover.release', $handover), $payload)->assertSessionHasErrors('release_method');
            $this->assertSame($state, $handover->fresh()->getAttributes());
        } else {
            $response->assertSessionHasErrors('schedule');
            $this->assertNull($handover->fresh()->released_at);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public function test_pickup_has_no_delivery_gates_and_received_legacy_records_remain_readable(): void
    {
        [$owner, $staff, $handover] = $this->placement('pickup');
        $payload = $this->releaseData($staff);
        $payload['release_method'] = 'pickup';
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $payload)->assertSessionHasNoErrors();
        $this->assertNull($handover->fresh()->delivery_address_status);
        $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), ['outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('synthetic-receipt.png')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('post_adoption_logs', 3);
        $this->actingAs($staff)->get(route('admin.handover.show', $handover))->assertOk()->assertSee('not required for shelter pickup');
    }

    public function test_pre_dispatch_notice_is_private_correctly_addressed_and_does_not_claim_courier_booking(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        $notification = HandoverNotification::where('kind', 'delivery_pre_dispatch')->sole();
        $row = HandoverEmailOutbox::where('kind', 'delivery_pre_dispatch')->sole();
        $this->assertSame($owner->id, $notification->user_id);
        $this->assertSame($handover->id, $notification->handover_id);
        $this->assertSame(route('adopter.handover.status', $handover), $notification->action_url);
        $this->assertSame($owner->email, $row->payload['recipient']);
        $this->assertNotNull($row->queued_at);
        $this->assertNull($row->transport_accepted_at);
        $this->assertNull($handover->fresh()->released_at);
        $this->assertNull($handover->courier);
        $content = json_encode([$notification->getAttributes(), $row->payload]);
        $this->assertStringNotContainsString($handover->adopter_address, $content);
        $this->assertStringNotContainsString('https://courier', $content);
        $this->assertStringContainsString('not yet been marked as released', $content);
        $this->assertStringNotContainsString($owner->email, DB::table('handover_email_outbox')->where('id', $row->id)->value('payload'));
        $this->actingAs($this->matchingUser())->get(route('adopter.handover.status', $handover))->assertForbidden();
        $this->get(route('admin.handover.show', $handover))->assertRedirect(route('access-denied'));
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public function test_failed_notification_enqueue_is_recoverable_without_reconfirming_readiness(): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Synthetic queue outage'));
        $this->readiness($staff, $handover);
        $row = HandoverEmailOutbox::where('kind', 'delivery_pre_dispatch')->sole();
        $this->assertNull($row->queued_at);
        $this->assertTrue(app(HandoverDeliveryClearanceService::class)->readinessIsCurrent($handover->fresh()));
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertNotNull($row->fresh()->queued_at);
        $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertDatabaseCount('handover_email_outbox', 1);
    }

    public static function obsoleteEvents(): array
    {
        return [['unavailable'], ['address'], ['schedule'], ['method'], ['released'], ['received'], ['expired'], ['reopened']];
    }

    #[DataProvider('obsoleteEvents')]
    public function test_queued_pre_dispatch_messages_are_suppressed_after_relevant_changes(string $change): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        $row = HandoverEmailOutbox::where('kind', 'delivery_pre_dispatch')->sole();
        match ($change) {
            'unavailable' => $this->readiness($staff, $handover, ['available' => 0]),
            'address' => $handover->update(['adopter_address' => 'Changed destination']),
            'schedule' => $handover->update(['schedule_version' => $handover->schedule_version + 1]),
            'method' => $handover->update(['scheduled_method' => 'pickup']),
            'released' => $handover->update(['released_at' => now()]),
            'received' => $handover->update(['released_at' => now(), 'received_at' => now(), 'adopter_outcome' => 'received']),
            'expired' => $this->travelTo($handover->scheduled_end_at->copy()->addSecond()),
            'reopened' => $handover->update(['reopen_count' => 1]),
        };
        $calls = 0;
        app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token, function () use (&$calls): bool {
            $calls++;

            return true;
        });
        $this->assertSame(0, $calls);
        $this->assertNotNull($row->fresh()->suppressed_at);
        $this->assertNull($row->transport_accepted_at);
    }

    public function test_valid_pre_dispatch_transport_failure_can_be_retried_and_duplicate_jobs_are_noops(): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        $row = HandoverEmailOutbox::where('kind', 'delivery_pre_dispatch')->sole();
        $service = app(HandoverMailOutboxService::class);
        try {
            $service->deliver($row->id, $row->enqueue_token, fn () => throw new RuntimeException('Synthetic transport failure'));
            $this->fail('The simulated failure should be rethrown.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('transport failed', $error->getMessage());
        }
        $this->assertSame(1, $row->fresh()->delivery_attempts);
        $this->assertNull($row->transport_accepted_at);
        $this->assertTrue($service->deliver($row->id, $row->enqueue_token, fn () => true));
        $this->assertNull($service->deliver($row->id, $row->enqueue_token, fn () => throw new RuntimeException('Must not send twice')));
        $this->assertSame(2, $row->fresh()->delivery_attempts);
        $this->assertNotNull($row->refresh()->transport_accepted_at);
    }

    public function test_pending_reschedule_preserves_agreement_but_requires_new_readiness_for_the_new_revision(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        $start = $handover->scheduled_start_at;
        $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), ['schedule_version' => $handover->schedule_version,
            'options' => [['date' => ManilaTime::now()->addDays(3)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00']]])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $handover->fresh()->schedule_status);
        $this->assertEquals($start, $handover->scheduled_start_at);
        $this->assertFalse(app(HandoverDeliveryClearanceService::class)->readinessIsCurrent($handover));
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff))->assertSessionHasErrors('delivery_readiness');
        $this->readiness($staff, $handover);
        $this->post(route('admin.handover.release', $handover), $this->releaseData($staff))->assertSessionHasNoErrors();
    }

    public function test_readiness_cannot_be_recorded_for_an_unconfirmed_or_expired_schedule(): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        foreach (['proposed', 'expired'] as $state) {
            $handover->update(['schedule_status' => $state === 'proposed' ? 'proposed' : 'confirmed']);
            if ($state === 'expired') {
                $this->travelTo($handover->scheduled_end_at->copy()->addSecond());
            }
            $this->actingAs($staff)->post(route('admin.handover.delivery.readiness', $handover), $this->revision($handover)
                + ['recipient' => 'adopter', 'available' => 1, 'contact_method' => 'phone'])->assertSessionHasErrors('schedule');
        }
        $this->assertNull($handover->fresh()->delivery_contacted_at);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public function test_authorization_is_rechecked_from_database_inside_the_locked_release_transaction(): void
    {
        [, $staff, $handover] = $this->placement();
        $handler = $this->matchingUser(Role::Volunteer);
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        DB::table('users')->where('id', $staff->id)->update(['is_active' => false]);
        // The authenticated object still says active; neither it nor the selected handler authorizes this actor.
        $this->assertTrue($staff->is_active);
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($handler))->assertForbidden();
        $this->assertNull($handover->fresh()->released_at);
    }

    public function test_readiness_and_notice_roll_back_together_if_outbox_storage_fails(): void
    {
        [, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $before = $handover->refresh()->getAttributes();
        Event::listen('eloquent.created: '.HandoverEmailOutbox::class, fn () => throw new RuntimeException('Synthetic outbox database failure'));
        $this->withoutExceptionHandling();
        try {
            $this->readiness($staff, $handover);
            $this->fail('Outbox failure must propagate and roll back the entire readiness event.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic outbox database failure', $error->getMessage());
        }
        $this->assertSame($before, $handover->fresh()->getAttributes());
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'delivery.readiness_confirmed']);
        Mail::assertNothingQueued();
    }

    public function test_delivery_receipt_and_not_received_keep_existing_proof_and_monitoring_rules(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $this->verify($staff, $handover);
        $this->readiness($staff, $handover);
        $this->post(route('admin.handover.release', $handover), $this->releaseData($staff))->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), ['outcome' => 'received'])->assertSessionHasErrors('receipt_proof');
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->post(route('adopter.confirm.submit', $handover), ['outcome' => 'not_received'])->assertSessionHasNoErrors();
        $this->assertSame('not_received', $handover->fresh()->adopter_outcome);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->actingAs($staff)->post(route('admin.handover.reopen', $handover), ['reason' => 'Synthetic retry'])->assertSessionHasNoErrors();
        $this->assertFalse(app(HandoverDeliveryClearanceService::class)->readinessIsCurrent($handover->fresh()));
    }
}
