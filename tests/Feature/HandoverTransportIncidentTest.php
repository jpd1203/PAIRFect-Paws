<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\HandoverEmailOutbox;
use App\Models\HandoverNotification;
use App\Models\HandoverTransportIncident;
use App\Models\Pet;
use App\Models\User;
use App\Services\HandoverMailOutboxService;
use App\Services\HandoverReminderService;
use App\Services\HandoverTransportIncidentService;
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

class HandoverTransportIncidentTest extends TestCase
{
    use BuildsMatchingFixtures, PreparesVerifiedHandovers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        config(['mail.staff_alert_address' => null, 'handover.transport_max_pending_hours' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'Asia/Manila')->utc());
    }

    private function placement(string $method = 'delivery'): array
    {
        $owner = $this->matchingUser();
        $staff = $this->matchingUser(Role::Administrator);
        $pet = Pet::create(['name' => 'Synthetic Transport Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now()]);
        $handover = Handover::create([
            'code' => 'INC-'.Str::uuid(), 'application_id' => $application->id, 'pet_id' => $pet->id,
            'user_id' => $owner->id, 'adopter_name' => $owner->full_name, 'adopter_address' => 'PRIVATE SYNTHETIC ADDRESS',
            'approved_at' => now(), 'history' => [], 'release_method' => $method, 'released_at' => now(),
            'scheduled_method' => $method, 'scheduled_start_at' => now()->subHour(), 'scheduled_end_at' => now()->addHour(),
            'schedule_status' => 'confirmed', 'schedule_version' => 1, 'schedule_confirmed_at' => now()->subHours(2),
            'schedule_confirmed_by_user_id' => $owner->id, 'tracking_url' => 'https://courier.example.test/PRIVATE',
        ]);

        return [$owner, $staff, $handover->fresh()];
    }

    private function report(User $staff, Handover $handover, array $extra = []): HandoverTransportIncident
    {
        $this->actingAs($staff)->post(route('admin.handover.incidents.store', $handover), array_replace([
            'release_attempt' => $handover->reopen_count, 'category' => 'animal_distress',
            'description' => 'PRIVATE description <script>syntheticMarker()</script>',
        ], $extra))->assertSessionHasNoErrors();

        return $handover->transportIncidents()->firstOrFail();
    }

    private function updateData(HandoverTransportIncident $case, array $extra = []): array
    {
        return array_replace([
            'version' => $case->version, 'status' => 'open', 'findings' => 'PRIVATE investigation notes',
            'next_follow_up_at' => CarbonImmutable::now('Asia/Manila')->addDay()->format('Y-m-d\TH:i'),
        ], $extra);
    }

    private function updateUrl(HandoverTransportIncident $case): string
    {
        return route('admin.handover.incidents.update', [$case->handover_id, $case]);
    }

    private function resolve(User $staff, HandoverTransportIncident $case, string $outcome = 'other_documented_resolution'): void
    {
        $this->actingAs($staff)->post($this->updateUrl($case), $this->updateData($case, [
            'status' => 'resolved', 'resolution_outcome' => $outcome, 'resolution_notes' => 'PRIVATE documented resolution',
        ]))->assertSessionHasNoErrors();
    }

    public function test_adopter_non_receipt_creates_unverified_attempt_linked_case_atomically_without_monitoring(): void
    {
        [$owner, , $handover] = $this->placement();
        $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), ['outcome' => 'not_received', 'note' => 'PRIVATE adopter report'])->assertSessionHasNoErrors();
        $case = HandoverTransportIncident::sole();
        $this->assertSame('adopter_non_receipt', $case->source);
        $this->assertSame('pet_not_received', $case->category);
        $this->assertSame('open', $case->status);
        $this->assertSame($owner->id, $case->reported_by_user_id);
        $this->assertSame($handover->reopen_count, $case->release_attempt);
        $this->assertSame('PRIVATE adopter report', $case->release_context['adopter_report_note']);
        $this->assertNull($case->resolution_outcome);
        $this->assertNull($handover->fresh()->received_at);
        $this->assertSame('Not received - requires investigation', $handover->refresh()->status_badge['label']);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->post(route('adopter.confirm.submit', $handover), ['outcome' => 'not_received'])->assertSessionHasErrors('outcome');
        $this->assertDatabaseCount('handover_transport_incidents', 1);
        $this->assertSame(1, HandoverEmailOutbox::where('kind', 'issue_logged')->count());
        $this->assertSame(0, HandoverEmailOutbox::where('kind', 'transport_case_attention')->count());
    }

    public static function categories(): array
    {
        return array_map(fn ($key) => [$key], ['pet_not_received', 'delayed_delivery', 'incorrect_destination', 'suspected_mishandling', 'injury_or_illness', 'animal_distress', 'other_transport_concern']);
    }

    #[DataProvider('categories')]
    public function test_staff_reports_categories_without_turning_them_into_findings(string $category): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover, ['category' => $category, 'reported_by_user_id' => 999, 'reported_at' => '1990-01-01', 'status' => 'resolved']);
        $this->assertSame($category, $case->category);
        $this->assertSame($staff->id, $case->reported_by_user_id);
        $this->assertTrue($case->reported_at->equalTo(now()));
        $this->assertSame('open', $case->status);
        $this->assertNull($case->resolved_at);
        $this->assertNull($handover->fresh()->adopter_outcome);
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public static function deniedActors(): array
    {
        return [['Adopter', true, true], ['Volunteer', false, true], ['Administrator', false, true], ['Volunteer', true, false]];
    }

    #[DataProvider('deniedActors')]
    public function test_ineligible_actors_cannot_create_manage_or_read_case_evidence(string $role, bool $active, bool $verified): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover, ['evidence' => UploadedFile::fake()->image('synthetic.png')]);
        $actor = $this->matchingUser(Role::from($role));
        $actor->forceFill(['is_active' => $active, 'email_verified_at' => $verified ? now() : null])->save();
        $this->actingAs($actor)->post(route('admin.handover.incidents.store', $handover), [])->assertRedirect();
        $this->post($this->updateUrl($case), $this->updateData($case))->assertRedirect();
        $this->get(route('admin.handover.incidents.evidence', [$handover, $case]))->assertRedirect();
        try {
            app(HandoverTransportIncidentService::class)->open($handover, $actor, ['release_attempt' => 0, 'category' => 'animal_distress', 'description' => 'denied']);
            $this->fail('Direct service invocation must reload authority.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame(1, $case->fresh()->version);
    }

    public function test_assignment_followup_and_status_are_audited_and_assigned_volunteer_can_work_but_not_resolve(): void
    {
        [, $staff, $handover] = $this->placement();
        $volunteer = $this->matchingUser(Role::Volunteer);
        $case = $this->report($volunteer, $handover);
        $this->post($this->updateUrl($case), $this->updateData($case))->assertForbidden();
        $this->actingAs($staff)->post($this->updateUrl($case), $this->updateData($case, ['status' => 'under_investigation', 'assigned_to_user_id' => $volunteer->id]))->assertSessionHasNoErrors();
        $case->refresh();
        $this->assertSame($volunteer->id, $case->assigned_to_user_id);
        $this->assertSame('2026-11-06 01:00', $case->next_follow_up_at->utc()->format('Y-m-d H:i'));
        $this->assertCount(2, $case->history);
        $this->post($this->updateUrl($case), $this->updateData($case, ['status' => 'under_investigation', 'assigned_to_user_id' => null]))->assertSessionHasErrors('assigned_to_user_id');
        $this->actingAs($volunteer)->post($this->updateUrl($case), $this->updateData($case, ['status' => 'under_investigation']))->assertSessionHasNoErrors();
        $case->refresh();
        $this->post($this->updateUrl($case), $this->updateData($case, ['assigned_to_user_id' => $staff->id]))->assertForbidden();
        $this->post($this->updateUrl($case), $this->updateData($case, ['status' => 'resolved', 'resolution_outcome' => 'other_documented_resolution', 'resolution_notes' => 'denied']))->assertForbidden();
        $this->assertSame('under_investigation', $case->fresh()->status);
        $this->assertStringNotContainsString('PRIVATE', AuditLog::where('entity_name', 'HandoverTransportIncident')->pluck('notes')->implode(' '));
        $this->actingAs($staff)->get(route('admin.handover.show', $handover))->assertOk()->assertSee('Investigation / Follow-Up')->assertSee('Nov 6, 2026 9:00 AM')
            ->assertSee('PRIVATE description <script>syntheticMarker()</script>')->assertDontSee('<script>syntheticMarker()</script>', false);
        $this->assertArrayNotHasKey('description', $case->toArray());
    }

    public function test_stale_updates_ineligible_assignees_and_invalid_input_are_rejected(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover);
        $url = $this->updateUrl($case);
        foreach ([['assigned_to_user_id' => $owner->id], ['next_follow_up_at' => '2026-10-01T09:00'], ['status' => 'verified_failed'], ['findings' => '   '], ['status' => 'under_investigation']] as $extra) {
            $this->post($url, $this->updateData($case, $extra))->assertSessionHasErrors();
        }
        $payload = $this->updateData($case);
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->post($url, $payload)->assertSessionHasErrors('incident');
        $this->assertSame(2, $case->fresh()->version);
        $this->assertCount(2, $case->fresh()->history);
        $staff->forceFill(['is_active' => false])->save();
        try {
            app(HandoverTransportIncidentService::class)->update($case, $staff, $this->updateData($case));
            $this->fail('Deactivated staff may not use the service.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_duplicate_reports_do_not_duplicate_cases_alerts_or_leave_unused_evidence(): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover, ['evidence' => UploadedFile::fake()->image('first.png')]);
        $this->report($staff, $handover, ['evidence' => UploadedFile::fake()->image('duplicate.png')]);
        $this->assertDatabaseCount('handover_transport_incidents', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        $this->assertDatabaseCount('handover_notifications', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame($case->evidence_path, $case->fresh()->evidence_path);
        $this->get(route('admin.handover.incidents.evidence', [$handover, $case]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_evidence_and_attempt_survive_reopen_and_remain_staff_only(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $proof = UploadedFile::fake()->image('original.png')->store('handover-proofs', 'local');
        $handover->update(['proof_path' => $proof]);
        $case = $this->report($staff, $handover, ['evidence' => UploadedFile::fake()->image('incident.png')]);
        $old = $case->getAttributes();
        $this->post(route('admin.handover.reopen', $handover), ['reason' => 'Synthetic new attempt'])->assertSessionHasNoErrors();
        $this->assertSame($old, $case->fresh()->getAttributes());
        $this->assertSame(0, $case->release_attempt);
        $this->assertSame(1, $handover->fresh()->reopen_count);
        $this->get(route('admin.handover.incidents.evidence', [$handover, $case]).'?kind=release')->assertOk();
        $this->post($this->updateUrl($case), $this->updateData($case))->assertSessionHasNoErrors();
        $row = HandoverEmailOutbox::where('kind', 'transport_case_attention')->latest('id')->firstOrFail();
        $this->assertTrue(app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token, fn () => true));
        $this->actingAs($owner)->get(route('admin.handover.incidents.evidence', [$handover, $case]))->assertRedirect();
        $this->get(route('adopter.handover.status', $handover))->assertOk()->assertDontSee('PRIVATE description');
    }

    public function test_route_binding_and_private_evidence_path_cannot_be_swapped(): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover);
        [, , $other] = $this->placement();
        $this->get(route('admin.handover.incidents.evidence', [$other, $case]))->assertNotFound();
        $this->post(route('admin.handover.incidents.update', [$other, $case]), $this->updateData($case))->assertNotFound();
        $case->forceFill(['evidence_path' => '../secret.env'])->save();
        $this->get(route('admin.handover.incidents.evidence', [$handover, $case]))->assertNotFound();
        $this->post(route('admin.handover.incidents.store', $handover), ['release_attempt' => 99, 'category' => 'delayed_delivery', 'description' => 'stale'])->assertSessionHasErrors('incident');
    }

    public static function resolutions(): array
    {
        return [['animal_returned_to_shelter'], ['delivery_failure_confirmed'], ['other_documented_resolution']];
    }

    #[DataProvider('resolutions')]
    public function test_documented_case_resolution_never_mutates_receipt_adoption_pet_or_monitoring(string $outcome): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover);
        $application = $handover->application->getAttributes();
        $pet = $handover->pet->getAttributes();
        $this->resolve($staff, $case, $outcome);
        $case->refresh();
        $this->assertSame('resolved', $case->status);
        $this->assertSame($staff->id, $case->resolved_by_user_id);
        $this->assertNotNull($case->resolved_at);
        $this->assertNull($case->next_follow_up_at);
        $this->assertSame($application, $handover->application->fresh()->getAttributes());
        $this->assertSame($pet, $handover->pet->fresh()->getAttributes());
        $this->assertNull($handover->fresh()->adopter_outcome);
        $this->assertNull($handover->received_at);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->post($this->updateUrl($case), $this->updateData($case))->assertSessionHasErrors('incident');
    }

    public function test_receipt_resolution_requires_real_current_attempt_confirmation_and_existing_proof_rules(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover);
        $this->post($this->updateUrl($case), $this->updateData($case, ['status' => 'resolved', 'resolution_outcome' => 'adopter_confirmed_receipt', 'resolution_notes' => 'Not valid yet']))->assertSessionHasErrors('resolution_outcome');
        $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), ['outcome' => 'received'])->assertSessionHasErrors('receipt_proof');
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->post(route('adopter.confirm.submit', $handover), ['outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('receipt.png')])->assertSessionHasNoErrors();
        $this->assertSame('open', $case->fresh()->status);
        $this->assertDatabaseCount('post_adoption_logs', 3);
        $this->resolve($staff, $case, 'adopter_confirmed_receipt');
        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertDatabaseCount('post_adoption_logs', 3);
    }

    public function test_reminder_thresholds_and_overdue_visibility_are_preserved_without_duplicate_case_alerts(): void
    {
        [, $staff, $handover] = $this->placement();
        $end = $handover->scheduled_end_at;
        $service = app(HandoverReminderService::class);
        $this->travelTo($end->copy()->addMinutes(59));
        $this->assertSame(0, $service->process());
        $this->travelTo($end->copy()->addHour());
        $this->assertSame(1, $service->process());
        $this->assertDatabaseCount('handover_transport_incidents', 0);
        $this->travelTo($end->copy()->addHours(3)->subSecond());
        $this->assertSame(0, $service->process());
        $this->travelTo($end->copy()->addHours(3));
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $case = HandoverTransportIncident::sole();
        $this->assertSame('overdue_delivery', $case->source);
        $this->assertNotNull($handover->fresh()->follow_up_flagged_at);
        $this->assertSame(1, HandoverEmailOutbox::where('kind', 'delivery_follow_up')->count());
        $this->assertSame(0, HandoverEmailOutbox::where('kind', 'transport_case_attention')->count());
        $this->assertSame(0, app(HandoverTransportIncidentService::class)->process());
        $this->actingAs($staff)->get(route('admin.handover.index', ['tab' => 'overdue']))->assertOk()->assertSee($handover->code);
        $this->get(route('admin.handover.index', ['tab' => 'investigations']))->assertOk()->assertSee('Needs assignment');
        $this->get(route('admin.handover.show', $handover))->assertOk()->assertSee('Overdue delivery receipt')->assertDontSee('Delivery failed');
    }

    public function test_pickup_and_unreleased_delivery_do_not_get_transport_timer_cases(): void
    {
        [, , $pickup] = $this->placement('pickup');
        $pickup->update(['released_at' => null]);
        [, $staff, $delivery] = $this->placement();
        $delivery->update(['released_at' => null]);
        $this->travelTo(now()->addHours(5));
        $this->assertSame(1, app(HandoverReminderService::class)->process());
        $this->assertNotNull($pickup->fresh()->missed_pickup_notified_at);
        $this->assertDatabaseCount('handover_transport_incidents', 0);
        $this->actingAs($staff)->post(route('admin.handover.incidents.store', $delivery), ['release_attempt' => 0, 'category' => 'delayed_delivery', 'description' => 'No release'])->assertSessionHasErrors('incident');
    }

    public function test_due_followup_is_once_per_revision_and_configured_maximum_only_alerts_for_review(): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover);
        $this->post($this->updateUrl($case), $this->updateData($case))->assertSessionHasNoErrors();
        $this->travelTo(now()->addDays(2));
        $service = app(HandoverTransportIncidentService::class);
        $this->assertNull($service->maximumPendingHours());
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $this->assertNotNull($case->fresh()->follow_up_notified_at);
        $this->assertNull($case->max_pending_notified_at);
        config(['handover.transport_max_pending_hours' => '24']);
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $this->assertNotNull($case->fresh()->max_pending_notified_at);
        $this->assertNull($handover->fresh()->adopter_outcome);
        $this->assertSame('Adopted', $handover->pet->availability_status->value);
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public function test_queue_failure_recovers_durable_case_alert_without_recreating_case(): void
    {
        [, $staff, $handover] = $this->placement();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Synthetic queue outage'));
        $case = $this->report($staff, $handover);
        $row = HandoverEmailOutbox::sole();
        $this->assertNull($row->queued_at);
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->assertSame(1, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertSame(0, app(HandoverMailOutboxService::class)->dispatchPending());
        $this->assertNotNull($row->fresh()->queued_at);
        $this->assertNull($row->transport_accepted_at);
        $this->assertDatabaseCount('handover_transport_incidents', 1);
        $this->assertSame('open', $case->fresh()->status);
    }

    public static function obsoleteChanges(): array
    {
        return [['resolved'], ['version'], ['inactive'], ['email'], ['role']];
    }

    #[DataProvider('obsoleteChanges')]
    public function test_obsolete_case_alerts_and_ineligible_recipients_are_suppressed(string $change): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover);
        $row = HandoverEmailOutbox::sole();
        match ($change) {
            'resolved' => $this->resolve($staff, $case),
            'version' => $this->post($this->updateUrl($case), $this->updateData($case))->assertSessionHasNoErrors(),
            'inactive' => $staff->update(['is_active' => false]),
            'email' => $staff->update(['email' => 'changed@example.test']),
            'role' => $staff->update(['role' => 'Adopter']),
        };
        $calls = 0;
        app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token, function () use (&$calls): bool {
            $calls++;

            return true;
        });
        $this->assertSame(0, $calls);
        $this->assertNotNull($row->fresh()->suppressed_at);
    }

    public function test_alert_payload_is_private_and_transaction_rolls_back_when_outbox_fails(): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover);
        $row = HandoverEmailOutbox::sole();
        $payload = json_encode($row->payload);
        $this->assertStringNotContainsString('PRIVATE', $payload);
        $this->assertStringNotContainsString('courier.example', $payload);
        $this->assertStringNotContainsString($staff->email, DB::table('handover_email_outbox')->sole()->payload);
        $this->assertTrue(app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token, fn () => true));
        $this->assertNull(app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token, fn () => throw new RuntimeException('Duplicate')));
        Event::listen('eloquent.created: '.HandoverEmailOutbox::class, fn () => throw new RuntimeException('Synthetic database outage'));
        $this->withoutExceptionHandling();
        try {
            $this->report($staff, $handover, ['category' => 'delayed_delivery', 'evidence' => UploadedFile::fake()->image('rollback.png')]);
            $this->fail('Outbox failure must roll back report, history and notices.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic database outage', $error->getMessage());
        }
        $this->assertDatabaseCount('handover_transport_incidents', 1);
        $this->assertDatabaseCount('handover_email_outbox', 1);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->assertCount(1, $case->fresh()->history);
    }

    public function test_invalid_evidence_unknown_category_and_whitespace_notes_are_rejected(): void
    {
        [, $staff, $handover] = $this->placement();
        foreach ([['category' => 'missing'], ['description' => '  '], ['evidence' => UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml')]] as $extra) {
            $this->actingAs($staff)->post(route('admin.handover.incidents.store', $handover), array_replace([
                'release_attempt' => 0, 'category' => 'animal_distress', 'description' => 'Synthetic report',
            ], $extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('handover_transport_incidents', 0);
    }

    public function test_case_assignment_alert_goes_to_admins_and_assigned_volunteer_not_unassigned_volunteers(): void
    {
        [, $staff, $handover] = $this->placement();
        $assigned = $this->matchingUser(Role::Volunteer);
        $other = $this->matchingUser(Role::Volunteer);
        $case = $this->report($staff, $handover);
        $this->post($this->updateUrl($case), $this->updateData($case, ['assigned_to_user_id' => $assigned->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, HandoverEmailOutbox::where('recipient_user_id', $assigned->id)->count());
        $this->assertSame(0, HandoverEmailOutbox::where('recipient_user_id', $other->id)->count());
        $notice = HandoverNotification::where('user_id', $assigned->id)->sole();
        $this->actingAs($assigned)->get(route('notifications.open', $notice))
            ->assertRedirect('/admin/handover/'.$handover->id.'#transport-case-'.$case->id);
        $row = HandoverEmailOutbox::where('recipient_user_id', $assigned->id)->sole();
        $case->refresh();
        $this->actingAs($staff)->post($this->updateUrl($case), $this->updateData($case, ['assigned_to_user_id' => $other->id]))->assertSessionHasNoErrors();
        app(HandoverMailOutboxService::class)->deliver($row->id, $row->enqueue_token, fn () => throw new RuntimeException('Former assignee must not receive this'));
        $this->assertNotNull($row->fresh()->suppressed_at);
    }

    public function test_case_transport_failure_retries_and_existing_nonreceipt_alert_is_obsolete_after_resolution(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover);
        $row = HandoverEmailOutbox::sole();
        $outbox = app(HandoverMailOutboxService::class);
        try {
            $outbox->deliver($row->id, $row->enqueue_token, fn () => throw new RuntimeException('Synthetic transport outage'));
            $this->fail('Transport failure must propagate for worker retry.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('transport failed', $error->getMessage());
        }
        $this->assertSame(1, $row->fresh()->delivery_attempts);
        $this->assertTrue($outbox->deliver($row->id, $row->enqueue_token, fn () => true));
        $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), ['outcome' => 'not_received'])->assertSessionHasNoErrors();
        $report = HandoverTransportIncident::where('source', 'adopter_non_receipt')->sole();
        $issue = HandoverEmailOutbox::where('kind', 'issue_logged')->sole();
        $this->resolve($staff, $report);
        $outbox->deliver($issue->id, $issue->enqueue_token, fn () => throw new RuntimeException('Resolved report must not send an old alert'));
        $this->assertSame('transport_case_resolved', $issue->fresh()->suppression_reason);
        $this->assertNull($handover->fresh()->received_at);
    }

    public function test_legacy_unresolved_report_is_recovered_without_resetting_report_time_or_realerting(): void
    {
        [$owner, , $handover] = $this->placement();
        $reported = now()->subMinutes(5);
        $handover->update(['adopter_outcome' => 'not_received', 'adopter_confirmed_at' => $reported, 'adopter_note' => 'PRIVATE legacy note']);
        $service = app(HandoverTransportIncidentService::class);
        $this->assertSame(0, $service->process());
        $this->assertSame(0, $service->process());
        $case = HandoverTransportIncident::sole();
        $this->assertSame($owner->id, $case->reported_by_user_id);
        $this->assertTrue($case->reported_at->equalTo($reported));
        $this->assertSame('PRIVATE legacy note', $case->release_context['adopter_report_note']);
        $this->assertDatabaseCount('handover_email_outbox', 0);
    }

    public function test_optional_maximum_can_raise_review_before_three_hour_escalation_without_inventing_outcomes(): void
    {
        [, , $handover] = $this->placement();
        config(['handover.transport_max_pending_hours' => '1']);
        $this->travelTo(now()->addHour());
        $service = app(HandoverTransportIncidentService::class);
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $this->assertNotNull(HandoverTransportIncident::sole()->max_pending_notified_at);
        $this->assertNull($handover->fresh()->follow_up_flagged_at);
        $this->assertNull($handover->adopter_outcome);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        foreach ([null, '', '0', '-1', '24.5', 'oops'] as $value) {
            config(['handover.transport_max_pending_hours' => $value]);
            $this->assertNull($service->maximumPendingHours());
        }
    }

    public function test_stale_attempt_report_and_evidence_replacement_do_not_change_history_or_leave_files(): void
    {
        [, $staff, $handover] = $this->placement();
        $case = $this->report($staff, $handover, ['evidence' => UploadedFile::fake()->image('original.png')]);
        $this->post($this->updateUrl($case), $this->updateData($case, ['evidence' => UploadedFile::fake()->image('replacement.png')]))->assertSessionHasErrors('evidence');
        $this->assertSame(1, $case->fresh()->version);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->post(route('admin.handover.reopen', $handover), ['reason' => 'Synthetic retry'])->assertSessionHasNoErrors();
        $this->post(route('admin.handover.incidents.store', $handover), ['release_attempt' => 0, 'category' => 'delayed_delivery', 'description' => 'Stale report'])->assertSessionHasErrors('incident');
        $handover->refresh()->update(['released_at' => now(), 'release_method' => 'delivery', 'adopter_outcome' => 'received', 'received_at' => now(), 'receipt_proof_path' => 'handover-receipts/synthetic.png']);
        $this->post($this->updateUrl($case), $this->updateData($case, ['status' => 'resolved', 'resolution_outcome' => 'adopter_confirmed_receipt', 'resolution_notes' => 'Wrong attempt']))->assertSessionHasErrors('resolution_outcome');
        $this->assertSame('open', $case->fresh()->status);
    }

    public function test_nonreceipt_and_its_case_roll_back_together_if_case_persistence_fails(): void
    {
        [$owner, , $handover] = $this->placement();
        Event::listen('eloquent.creating: '.HandoverTransportIncident::class, fn () => throw new RuntimeException('Synthetic case storage failure'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), ['outcome' => 'not_received']);
            $this->fail('Non-receipt must not commit without its investigation.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic case storage failure', $error->getMessage());
        }
        $this->assertNull($handover->fresh()->adopter_outcome);
        $this->assertDatabaseCount('handover_transport_incidents', 0);
        $this->assertDatabaseCount('handover_notifications', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    public function test_overdue_case_marker_and_initial_alert_roll_back_together_on_outbox_failure(): void
    {
        [, , $handover] = $this->placement();
        $handover->update(['receipt_reminder_sent_at' => now()]);
        $this->travelTo($handover->scheduled_end_at->copy()->addHours(3));
        Event::listen('eloquent.creating: '.HandoverEmailOutbox::class, fn () => throw new RuntimeException('Synthetic outbox failure'));
        try {
            app(HandoverReminderService::class)->process();
            $this->fail('Escalation must not commit without its existing durable alert.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic outbox failure', $error->getMessage());
        }
        $this->assertNull($handover->fresh()->follow_up_flagged_at);
        $this->assertDatabaseCount('handover_transport_incidents', 0);
        $this->assertDatabaseCount('handover_email_outbox', 0);
        $this->assertDatabaseCount('handover_notifications', 0);
        Mail::assertNothingQueued();
    }
}
