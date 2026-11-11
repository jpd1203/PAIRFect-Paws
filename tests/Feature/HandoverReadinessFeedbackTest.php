<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\HandoverEmailOutbox;
use App\Models\Pet;
use App\Services\HandoverDeliveryClearanceService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class HandoverReadinessFeedbackTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'Asia/Manila')->utc());
        // Simulate the browser cookie jar across POST/redirect/GET with array sessions.
        $this->withSession([]);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function placement(): Handover
    {
        $owner = $this->matchingUser();
        $staff = $this->matchingUser(Role::Administrator);
        $pet = Pet::create(['name' => 'Synthetic Readiness Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now()]);
        $record = Handover::create(['code' => 'QA-READINESS', 'application_id' => $application->id,
            'pet_id' => $pet->id, 'user_id' => $owner->id, 'adopter_name' => $owner->full_name,
            'adopter_address' => '123 Synthetic Test Street, Manila', 'approved_at' => now(), 'history' => [],
            'scheduled_method' => 'delivery', 'schedule_status' => 'confirmed', 'schedule_version' => 1,
            'scheduled_start_at' => now()->addDay(), 'scheduled_end_at' => now()->addDay()->addHours(2),
            'schedule_confirmed_at' => now(), 'schedule_confirmed_by_user_id' => $owner->id]);
        app(HandoverDeliveryClearanceService::class)->verifyAddress($record, $staff, $this->revision($record) + ['status' => 'verified']);
        $this->actingAs($staff);

        return $record->refresh();
    }

    private function revision(Handover $record): array
    {
        return ['schedule_version' => $record->schedule_version, 'clearance_version' => $record->delivery_clearance_version,
            'destination_fingerprint' => app(HandoverDeliveryClearanceService::class)->destinationFingerprint($record)];
    }

    private function payload(Handover $record): array
    {
        return $this->revision($record) + ['recipient' => 'adopter', 'available' => '1', 'contact_method' => 'phone'];
    }

    private function timeline(string $html): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $nodes = (new DOMXPath($document))->query('//h3[normalize-space()="Handover Log"]/parent::section');
        $this->assertSame(1, $nodes->length);

        return $document->saveHTML($nodes->item(0));
    }

    public static function prerequisites(): array
    {
        return [
            ['address', 'Verify the current delivery destination using Record destination review above.', 'delivery_address'],
            ['proposed', 'Establish a delivery schedule and have the adopter confirm it on Handover Status.', 'schedule'],
            ['wrong_confirmer', 'Establish a delivery schedule and have the adopter confirm it on Handover Status.', 'schedule'],
            ['missing_window', 'Establish a delivery schedule and have the adopter confirm it on Handover Status.', 'schedule'],
            ['expired', 'The confirmed delivery window has expired. Establish a new schedule and obtain adopter confirmation.', 'schedule'],
        ];
    }

    #[DataProvider('prerequisites')]
    public function test_disabled_button_explains_exact_missing_prerequisite_and_server_still_blocks_it(string $change, string $guidance, string $error): void
    {
        $record = $this->placement();
        $record->forceFill(match ($change) {
            'address' => ['delivery_address_fingerprint' => str_repeat('0', 64)],
            'proposed' => ['schedule_status' => 'proposed'],
            'wrong_confirmer' => ['schedule_confirmed_by_user_id' => auth()->id()],
            'missing_window' => ['scheduled_end_at' => null],
            'expired' => ['scheduled_end_at' => now()->subSecond()],
        })->save();
        $page = $this->get(route('admin.handover.show', $record))->assertOk()->assertSee($guidance);
        $this->assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*disabled[^>]*aria-describedby="deliveryReadinessPrerequisites"/s', $page->getContent());
        $before = $record->fresh()->getAttributes();
        $this->post(route('admin.handover.delivery.readiness', $record), $this->payload($record))->assertSessionHasErrors($error);
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public static function contacts(): array
    {
        return [['phone', null], ['email', null], ['other', 'Synthetic documented direct conversation']];
    }

    #[DataProvider('contacts')]
    public function test_confirmed_contact_returns_to_local_success_status_without_claiming_email_delivery(string $method, ?string $notes): void
    {
        $record = $this->placement();
        $input = array_replace($this->payload($record), ['contact_method' => $method, 'notes' => $notes]);
        $this->post(route('admin.handover.delivery.readiness', $record), $input)
            ->assertSessionHasNoErrors()->assertSessionHas('delivery_readiness_recorded', true)
            ->assertRedirect(route('admin.handover.show', $record).'#deliveryReadiness');
        $record->refresh();
        $this->assertTrue(app(HandoverDeliveryClearanceService::class)->readinessIsCurrent($record));
        $this->assertSame($method, $record->delivery_contact_method);
        $this->assertSame($notes, $record->delivery_readiness_notes);
        $this->assertSame(auth()->id(), $record->delivery_contacted_by_user_id);
        $this->assertTrue($record->delivery_contacted_at->equalTo(now()));
        $page = $this->get(route('admin.handover.show', $record))->assertOk()
            ->assertSee('role="status"', false)->assertSee('Adopter availability confirmed.')
            ->assertSee('this does not prove email delivery or record courier release.');
        $timeline = $this->timeline($page->getContent());
        $this->assertStringContainsString('Adopter availability confirmed', $timeline);
        $this->assertStringNotContainsString('clearance version', $timeline);
        $this->assertSame('readiness confirmed (clearance version 2)', $record->history[1]['label']);
        $audit = AuditLog::where('action', 'delivery.readiness_confirmed')->sole();
        $this->assertStringContainsString('Clearance version 2; schedule version 1; destination reference ', $audit->notes);
        $this->assertStringContainsString(app(HandoverDeliveryClearanceService::class)->destinationFingerprint($record), $audit->notes);
        $this->assertDatabaseCount('handover_notifications', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        $this->assertNull(HandoverEmailOutbox::sole()->transport_accepted_at);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->assertNull($record->released_at);
        $state = $record->getAttributes();
        $this->post(route('admin.handover.delivery.readiness', $record), array_replace($input, $this->revision($record)))->assertSessionHasNoErrors();
        $this->assertSame($state, $record->fresh()->getAttributes());
        $this->assertDatabaseCount('handover_email_outbox', 1);
        $this->assertSame(1, AuditLog::where('action', 'delivery.readiness_confirmed')->count());
    }

    public function test_unavailable_outcome_is_visible_does_not_create_a_notice_or_clear_release(): void
    {
        $record = $this->placement();
        $this->post(route('admin.handover.delivery.readiness', $record), array_replace($this->payload($record), ['available' => '0']))
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_starts_with($message, 'Adopter reported unavailable.'));
        $this->assertFalse($record->fresh()->delivery_recipient_available);
        $this->assertFalse(app(HandoverDeliveryClearanceService::class)->readinessIsCurrent($record));
        $this->get(route('admin.handover.show', $record))->assertOk()->assertSee('Adopter reported unavailable (last recorded contact)')
            ->assertSee('delivery release remains blocked');
        $this->assertSame('recipient unavailable (clearance version 2)', $record->fresh()->history[1]['label']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.recipient_unavailable']);
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public static function missingFields(): array
    {
        return [['contact_method', ''], ['available', ''], ['notes', null]];
    }

    #[DataProvider('missingFields')]
    public function test_validation_is_local_accessible_and_preserves_form_choices(string $field, mixed $value): void
    {
        $record = $this->placement();
        $input = array_replace($this->payload($record), ['contact_method' => 'other', 'notes' => 'Synthetic <b>contact</b>'], [$field => $value]);
        $this->post(route('admin.handover.delivery.readiness', $record), $input)
            ->assertSessionHasErrors($field)->assertRedirect(route('admin.handover.show', $record).'#deliveryReadiness');
        $this->assertSame('adopter', session()->getOldInput('recipient'));
        $page = $this->get(route('admin.handover.show', $record))->assertOk()->assertSee('Contact and availability were not recorded.')
            ->assertSee('id="deliveryReadinessErrors"', false);
        $this->assertMatchesRegularExpression('/aria-invalid="true" aria-describedby="delivery(?:ContactMethod|Available|ReadinessNotes)Error"/', $page->getContent());
        if ($field !== 'contact_method') {
            $this->assertMatchesRegularExpression('/value="other"\s+selected/', $page->getContent());
        }
        if ($field !== 'available') {
            $this->assertMatchesRegularExpression('/value="1"\s+selected/', $page->getContent());
        }
        if ($field !== 'notes') {
            $page->assertSee('Synthetic <b>contact</b>')->assertDontSee('Synthetic <b>contact</b>', false);
        }
        $this->assertNull($record->fresh()->delivery_contacted_at);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public static function staleFields(): array
    {
        return [['schedule_version', 0], ['clearance_version', 0], ['destination_fingerprint', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']];
    }

    #[DataProvider('staleFields')]
    public function test_stale_submission_is_not_replayed_and_explains_refresh_locally(string $field, mixed $value): void
    {
        $record = $this->placement();
        $before = $record->getAttributes();
        $this->post(route('admin.handover.delivery.readiness', $record), array_replace($this->payload($record), [$field => $value]))
            ->assertSessionHasErrors('delivery_readiness')->assertRedirect(route('admin.handover.show', $record).'#deliveryReadiness');
        $this->get(route('admin.handover.show', $record))->assertSee('Refresh and review the current details before continuing.')
            ->assertSee('Contact and availability were not recorded.');
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public function test_stale_previously_positive_contact_is_not_displayed_as_current(): void
    {
        $record = $this->placement();
        $this->post(route('admin.handover.delivery.readiness', $record), $this->payload($record))->assertSessionHasNoErrors();
        $record->refresh()->update(['schedule_version' => 2]);
        $this->get(route('admin.handover.show', $record))->assertSee('The previous availability confirmation no longer covers the current agreement.')
            ->assertSee('Current readiness not established');
    }

    public function test_json_validation_remains_422_and_third_party_contact_is_rejected(): void
    {
        $record = $this->placement();
        $this->postJson(route('admin.handover.delivery.readiness', $record), array_replace($this->payload($record), ['recipient' => 'delegate']))
            ->assertUnprocessable()->assertJsonValidationErrors('recipient');
        $this->postJson(route('admin.handover.delivery.readiness', $record), array_replace($this->payload($record), ['contact_method' => 'other']))
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->assertNull($record->fresh()->delivery_contacted_at);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public function test_active_verified_volunteer_can_record_contact_and_stale_deactivated_actor_cannot(): void
    {
        $record = $this->placement();
        $volunteer = $this->matchingUser(Role::Volunteer);
        $this->actingAs($volunteer)->post(route('admin.handover.delivery.readiness', $record), $this->payload($record))->assertSessionHasNoErrors();
        $this->assertSame($volunteer->id, $record->refresh()->delivery_contacted_by_user_id);
        $state = $record->getAttributes();
        // Keep the authenticated object stale to exercise the locked database authorization.
        DB::table('users')->where('id', $volunteer->id)->update(['is_active' => false]);
        $this->post(route('admin.handover.delivery.readiness', $record), array_replace($this->payload($record), ['available' => '0']))->assertForbidden();
        $this->assertSame($state, $record->fresh()->getAttributes());
    }

    public static function invalidHandovers(): array
    {
        return [['unapproved'], ['released'], ['received'], ['pickup']];
    }

    #[DataProvider('invalidHandovers')]
    public function test_invalid_handover_cannot_record_contact(string $state): void
    {
        $record = $this->placement();
        if ($state === 'unapproved') {
            $record->application->update(['status' => 'Pending']);
        } else {
            $record->update(match ($state) {
                'released' => ['released_at' => now()],
                'received' => ['released_at' => now(), 'received_at' => now(), 'adopter_outcome' => 'received'],
                'pickup' => ['scheduled_method' => 'pickup'],
            });
        }
        $before = $record->fresh()->getAttributes();
        $this->post(route('admin.handover.delivery.readiness', $record), $this->payload($record))->assertSessionHasErrors();
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public function test_history_adapter_preserves_original_evidence_order_actor_timestamp_and_escaping(): void
    {
        $record = $this->placement();
        $history = [
            ['label' => 'address verified (clearance version 3)', 'actor' => 'Earlier Staff', 'at' => '2026-11-04T01:00:00+00:00'],
            ['label' => 'schedule.confirmed (schedule version 1)', 'actor' => 'Synthetic Adopter', 'at' => '2026-11-04T02:00:00+00:00'],
            ['label' => 'recipient unavailable (clearance version 4)', 'actor' => 'Latest Staff', 'at' => '2026-11-05T01:00:00+00:00'],
            ['label' => '<script>syntheticOnly()</script>', 'actor' => '<b>Synthetic Actor</b>', 'at' => '2026-11-05T02:00:00+00:00'],
        ];
        $record->update(['history' => $history]);
        // MySQL normalizes JSON object-key order on write. Compare values to
        // the fixture and byte-for-byte persisted evidence before/after render.
        $storedHistory = $record->fresh()->getRawOriginal('history');
        $auditBefore = AuditLog::all()->map->getAttributes()->all();
        $page = $this->get(route('admin.handover.show', $record))->assertOk();
        $timeline = $this->timeline($page->getContent());
        $this->assertStringContainsString('Nov 5, 2026 · 9:00 AM', html_entity_decode(strip_tags($timeline)));
        $this->assertStringContainsString('Latest Staff', $timeline);
        $this->assertLessThan(strpos($timeline, 'Delivery address verified'), strpos($timeline, 'Adopter reported unavailable'));
        $this->assertStringContainsString('Adopter confirmed handover schedule', $timeline);
        $this->assertStringContainsString('&lt;script&gt;syntheticOnly()&lt;/script&gt;', $timeline);
        $this->assertStringNotContainsString('<script>', $timeline);
        $this->assertStringContainsString('&lt;b&gt;Synthetic Actor&lt;/b&gt;', $timeline);
        foreach (['clearance version', 'schedule version', 'schedule.confirmed', app(HandoverDeliveryClearanceService::class)->destinationFingerprint($record)] as $technical) {
            $this->assertStringNotContainsString($technical, $timeline);
        }
        $this->assertEquals($history, $record->fresh()->history);
        $this->assertSame($storedHistory, $record->fresh()->getRawOriginal('history'));
        $this->assertSame($auditBefore, AuditLog::all()->map->getAttributes()->all());
    }
}
