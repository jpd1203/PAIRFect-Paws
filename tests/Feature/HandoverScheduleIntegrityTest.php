<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\HandoverNotification;
use App\Models\Pet;
use App\Models\User;
use App\Services\HandoverScheduleService;
use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\PreparesVerifiedHandovers;
use Tests\TestCase;

class HandoverScheduleIntegrityTest extends TestCase
{
    use BuildsMatchingFixtures;
    use PreparesVerifiedHandovers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        config(['app.display_timezone' => 'Asia/Manila', 'mail.staff_alert_address' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'Asia/Manila')->utc());
    }

    public function test_initial_method_selection_is_versioned_and_duplicate_or_stale_forms_cannot_restore_it(): void
    {
        [$owner, , , $handover] = $this->placement();
        $url = route('adopter.handover.schedule.method', $handover);
        $this->actingAs($owner)->get(route('adopter.handover.status', $handover))
            ->assertOk()->assertSee($url, false)->assertSee('name="schedule_version" value="0"', false);
        $this->post($url, ['method' => 'pickup', 'schedule_version' => 0])->assertSessionHasNoErrors();
        $this->assertSame(1, $handover->fresh()->schedule_version);
        $this->assertSame('pickup', $handover->fresh()->scheduled_method);
        $this->assertSame('unscheduled', $handover->fresh()->schedule_status);
        $this->assertNull($handover->fresh()->schedule_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.method_selected', 'entity_id' => $handover->id]);
        $this->assertSame(1, $this->staffNoticeCount($handover, 'method_selected'));

        $this->post($url, ['method' => 'delivery', 'schedule_version' => 1])->assertSessionHasNoErrors();
        $handover->refresh();
        $this->assertSame(2, $handover->schedule_version);
        $this->assertSame('delivery', $handover->scheduled_method);
        $this->assertSame(2, $this->staffNoticeCount($handover, 'method_selected'));
        $before = $this->snapshot($handover);
        $this->post($url, ['method' => 'delivery', 'schedule_version' => 2])->assertSessionHasNoErrors();
        $this->assertSame($before, $this->snapshot($handover));
        foreach ([['method' => 'pickup', 'schedule_version' => 0], ['method' => 'pickup', 'schedule_version' => 1]] as $payload) {
            $this->post($url, $payload)->assertSessionHasErrors('schedule');
            $this->assertSame($before, $this->snapshot($handover));
        }
    }

    public function test_method_selection_requires_revision_and_remains_owner_protected(): void
    {
        [$owner, , , $handover] = $this->placement();
        $url = route('adopter.handover.schedule.method', $handover);
        $this->actingAs($owner)->post($url, ['method' => 'pickup'])->assertSessionHasErrors('schedule_version');
        $this->post($url, ['method' => 'pickup', 'schedule_version' => -1])->assertSessionHasErrors('schedule_version');
        $this->post($url, ['method' => 'invalid', 'schedule_version' => 0])->assertSessionHasErrors('method');
        $this->actingAs($this->matchingUser())->post($url, ['method' => 'pickup', 'schedule_version' => 0])->assertForbidden();
        $this->assertNull($handover->fresh()->scheduled_method);
        $this->assertSame(0, $handover->fresh()->schedule_version);
    }

    public static function agreedStates(): array
    {
        return [['proposed'], ['confirmed'], ['reschedule_pending'], ['confirmed_pending']];
    }

    #[DataProvider('agreedStates')]
    public function test_direct_method_change_is_blocked_for_proposed_confirmed_and_pending_agreements(string $state): void
    {
        [$owner, $staff, , $handover] = $this->placement();
        $this->propose($staff, $handover);
        if (str_starts_with($state, 'confirmed')) {
            $this->confirm($owner, $handover);
        }
        if (str_contains($state, 'pending')) {
            $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), [
                'schedule_version' => $handover->refresh()->schedule_version, 'options' => [$this->slot(7)],
            ])->assertSessionHasNoErrors();
        }
        $before = $this->snapshot($handover);
        $url = route('adopter.handover.schedule.method', $handover);
        $this->actingAs($owner)->get(route('adopter.handover.status', $handover))
            ->assertOk()->assertDontSee($url, false);
        $this->post($url, ['method' => 'pickup', 'schedule_version' => $handover->refresh()->schedule_version])
            ->assertSessionHasErrors('method');
        $this->assertSame($before, $this->snapshot($handover));
    }

    public function test_stale_method_and_staff_proposal_forms_cannot_replace_a_newer_revision(): void
    {
        [$owner, $staff, , $handover] = $this->placement();
        $this->actingAs($owner)->post(route('adopter.handover.schedule.method', $handover), [
            'method' => 'pickup', 'schedule_version' => 0,
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('admin.handover.schedule', $handover), [
            'method' => 'delivery', 'schedule_version' => 0, ...$this->slot(),
        ])->assertSessionHasErrors('schedule');
        $this->propose($staff, $handover);
        $before = $this->snapshot($handover);
        $this->actingAs($owner)->post(route('adopter.handover.schedule.method', $handover), [
            'method' => 'pickup', 'schedule_version' => 1,
        ])->assertSessionHasErrors('schedule');
        $this->assertSame($before, $this->snapshot($handover));
    }

    public function test_service_reloads_the_locked_record_instead_of_trusting_a_stale_method_model(): void
    {
        [$owner, $staff, , $handover] = $this->placement();
        $stale = clone $handover;
        $this->propose($staff, $handover);
        try {
            app(HandoverScheduleService::class)->selectMethod($stale, $owner, 'pickup', 0);
            $this->fail('A stale model must not overwrite the current proposal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schedule', $exception->errors());
        }
        $this->assertSame('delivery', $handover->fresh()->scheduled_method);
        $this->assertSame('proposed', $handover->fresh()->schedule_status);
    }

    public function test_inconsistent_unscheduled_record_cannot_use_method_selection_to_preserve_old_agreement_fields(): void
    {
        [$owner, $staff, , $handover] = $this->placement();
        $this->propose($staff, $handover);
        $this->confirm($owner, $handover);
        $handover->update(['schedule_status' => 'unscheduled']);
        $before = $this->snapshot($handover);
        $this->actingAs($owner)->post(route('adopter.handover.schedule.method', $handover), [
            'method' => 'pickup', 'schedule_version' => $handover->schedule_version,
        ])->assertSessionHasErrors('method');
        $this->assertSame($before, $this->snapshot($handover));
        $this->get(route('adopter.handover.status', $handover))
            ->assertOk()->assertDontSee(route('adopter.handover.schedule.method', $handover), false);
    }

    public function test_adopter_requested_window_can_be_accepted_but_injected_method_changes_and_stale_reviews_cannot(): void
    {
        [$owner, $staff, , $handover] = $this->placement();
        $this->propose($staff, $handover);
        $this->confirm($owner, $handover);
        $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), [
            'schedule_version' => $handover->schedule_version, 'options' => [$this->slot(7)],
            'method' => 'pickup', 'scheduled_method' => 'pickup',
        ])->assertSessionHasNoErrors();
        $version = $handover->refresh()->schedule_version;
        $review = ['schedule_version' => $version, 'decision' => 'approved', 'option_index' => 0, 'method' => 'pickup'];
        $this->actingAs($staff)->post(route('admin.handover.reschedule.review', $handover), $review)
            ->assertSessionHasNoErrors();
        $handover->refresh();
        $this->assertSame('delivery', $handover->scheduled_method);
        $this->assertSame('confirmed', $handover->schedule_status);
        $this->assertSame($owner->id, $handover->schedule_confirmed_by_user_id);
        $this->assertSame($version + 1, $handover->schedule_version);
        $before = $this->snapshot($handover);
        $this->post(route('admin.handover.reschedule.review', $handover), $review)->assertSessionHasErrors('schedule');
        $this->assertSame($before, $this->snapshot($handover));
    }

    public static function renewedAgreements(): array
    {
        return [['pickup'], ['delivery']];
    }

    #[DataProvider('renewedAgreements')]
    public function test_staff_reproposal_requires_new_adopter_confirmation_and_rejects_stale_confirmation(string $method): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover);
        $this->confirm($owner, $handover);
        $oldVersion = $handover->schedule_version;
        $handover->update(['schedule_24h_reminder_sent_at' => now(), 'follow_up_flagged_at' => now()]);
        $this->propose($staff, $handover, $method, 7);
        $this->assertSame($oldVersion + 1, $handover->schedule_version);
        $this->assertSame('proposed', $handover->schedule_status);
        $this->assertNull($handover->schedule_confirmed_at);
        $this->assertNull($handover->schedule_confirmed_by_user_id);
        $this->assertNull($handover->schedule_24h_reminder_sent_at);
        $this->assertNull($handover->follow_up_flagged_at);
        $this->assertSame(2, HandoverNotification::where('handover_id', $handover->id)->where('kind', 'schedule_proposed')->count());
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff, $method))
            ->assertSessionHasErrors('schedule');
        $this->actingAs($owner)->post(route('adopter.handover.schedule.confirm', $handover), ['schedule_version' => $oldVersion])
            ->assertSessionHasErrors('schedule');
        $this->confirm($owner, $handover);
        $this->verifiedIdentity($application, $staff);
        if ($method === 'pickup') {
            $this->verifiedIdentity($application, $staff, 'pickup_handover');
        } else {
            $this->prepareDeliveryClearance($handover, $staff);
        }
        $this->travelTo($handover->scheduled_start_at->copy()->addMinutes(30));
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff, $method))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($handover->fresh()->released_at);
    }

    public static function releaseBoundaries(): array
    {
        return [
            ['pickup', 'before', true], ['delivery', 'before', true],
            ['pickup', 'start', true], ['delivery', 'start', true],
            ['pickup', 'during', true], ['delivery', 'during', true],
            ['pickup', 'end', true], ['delivery', 'end', true],
            ['pickup', 'expired', false], ['delivery', 'expired', false],
        ];
    }

    #[DataProvider('releaseBoundaries')]
    public function test_release_checks_the_server_window_in_manila_for_pickup_and_delivery(string $method, string $boundary, bool $allowed): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover, $method);
        $this->confirm($owner, $handover);
        $this->verifiedIdentity($application, $staff);
        if ($method === 'pickup') {
            $this->verifiedIdentity($application, $staff, 'pickup_handover');
        } else {
            $this->prepareDeliveryClearance($handover, $staff);
        }
        $this->assertSame('02:00', $handover->scheduled_start_at->utc()->format('H:i'));
        $instant = match ($boundary) {
            'before' => $handover->scheduled_start_at->copy()->subMinutes(30),
            'start' => $handover->scheduled_start_at,
            'during' => $handover->scheduled_start_at->copy()->addHour(),
            'end' => $handover->scheduled_end_at,
            'expired' => $handover->scheduled_end_at->copy()->addSecond(),
        };
        $this->travelTo($instant);
        $before = $this->snapshot($handover);
        $payload = $this->releaseData($staff, $method);
        if ($boundary === 'expired') {
            // Client-supplied timestamps and agreement fields cannot extend the server's window.
            $payload += ['scheduled_end_at' => now()->addWeek()->toDateTimeString(), 'schedule_status' => 'confirmed', 'released_at' => $handover->scheduled_start_at->toDateTimeString()];
            $payload['release_date'] = ManilaTime::format($handover->scheduled_start_at, 'Y-m-d');
            $payload['release_time'] = '10:30';
            $payload['proof'] = UploadedFile::fake()->image('proof.png');
        }
        $response = $this->actingAs($staff)->post(route('admin.handover.release', $handover), $payload);
        if ($allowed) {
            $response->assertSessionHasNoErrors()->assertSessionHas('toast.type', 'success');
            $this->assertNotNull($handover->fresh()->released_at);
            $this->assertSame($method, $handover->fresh()->release_method);
            $this->assertDatabaseCount('post_adoption_logs', 0);
            $after = $this->snapshot($handover);
            $this->post(route('admin.handover.release', $handover), $payload)->assertSessionHasErrors('release_method');
            $this->assertSame($after, $this->snapshot($handover));
        } else {
            $response->assertSessionHasErrors(['schedule' => 'The confirmed handover window has expired. Staff must propose a new schedule and obtain adopter confirmation before release.']);
            $this->assertSame($before, $this->snapshot($handover));
            $this->assertNull($handover->fresh()->released_at);
            $this->assertSame([], Storage::disk('local')->allFiles());
            $this->assertSame('Approved', $application->fresh()->status->value);
            $this->assertSame('Adopted', $handover->pet->fresh()->availability_status->value);
            $this->assertDatabaseCount('post_adoption_logs', 0);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'handover.released', 'entity_id' => $handover->id]);
        }
    }

    #[DataProvider('renewedAgreements')]
    public function test_early_release_still_requires_staff_authorization_identity_and_delivery_details(string $method): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover, $method);
        $this->confirm($owner, $handover);
        $this->travelTo($handover->scheduled_start_at->copy()->subMinutes(30));
        $url = route('admin.handover.release', $handover);
        $payload = $this->releaseData($staff, $method);
        $this->actingAs($owner)->post($url, $payload)->assertRedirect(route('access-denied'));
        $this->actingAs($staff)->post($url, $payload)->assertSessionHasErrors('identity');
        $this->verifiedIdentity($application, $staff);
        if ($method === 'pickup') {
            $this->post($url, $payload)->assertSessionHasErrors('identity');
            $this->verifiedIdentity($application, $staff, 'pickup_handover');
        } else {
            $incomplete = array_diff_key($payload, array_flip(['courier_provider', 'tracking_number', 'tracking_url']));
            $this->post($url, $incomplete)->assertSessionHasErrors(['courier_provider', 'tracking_number', 'tracking_url']);
            $this->post($url, array_replace($payload, ['tracking_url' => 'http://example.test/insecure']))
                ->assertSessionHasErrors('tracking_url');
            $this->prepareDeliveryClearance($handover, $staff);
        }
        $this->assertNull($handover->fresh()->released_at);
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->assertNotNull($handover->fresh()->released_at);
    }

    public function test_an_expired_confirmed_window_stays_blocked_while_rescheduling_is_pending(): void
    {
        [$owner, $staff, $application, $handover] = $this->placement();
        $this->propose($staff, $handover);
        $this->confirm($owner, $handover);
        $this->verifiedIdentity($application, $staff);
        $this->actingAs($owner)->post(route('adopter.handover.reschedule', $handover), [
            'schedule_version' => $handover->schedule_version, 'options' => [$this->slot(7)],
        ])->assertSessionHasNoErrors();
        $this->prepareDeliveryClearance($handover, $staff);
        $this->travelTo($handover->scheduled_end_at->copy()->addMinute());
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff))
            ->assertSessionHasErrors('schedule');
        $this->assertSame('confirmed', $handover->fresh()->schedule_status);
        $this->assertSame('pending', $handover->fresh()->reschedule_status);
        $this->assertNull($handover->fresh()->released_at);
    }

    public function test_expiry_during_release_processing_blocks_the_final_write_and_removes_new_proof(): void
    {
        [, $staff, , $handover] = $this->placement();
        $this->prepareHandover($handover, $staff);
        $this->travelTo($handover->scheduled_end_at->copy()->subSecond());
        $expiredAt = $handover->scheduled_end_at->copy()->addSecond();
        // Simulate processing crossing the cutoff, not a concurrent-database claim.
        DB::listen(function (QueryExecuted $query) use ($expiredAt): void {
            if (str_contains($query->sql, 'identity_verifications')) {
                $this->travelTo($expiredAt);
            }
        });
        $before = $this->snapshot($handover);
        $payload = $this->releaseData($staff) + ['proof' => UploadedFile::fake()->image('release-proof.png')];
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $payload)
            ->assertSessionHasErrors('schedule');
        $this->assertSame($before, $this->snapshot($handover));
        $this->assertNull($handover->fresh()->released_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public static function receiptOutcomes(): array
    {
        return [['received'], ['not_received']];
    }

    #[DataProvider('receiptOutcomes')]
    public function test_receipt_reporting_after_expiry_still_works_for_a_pet_released_during_the_window(string $outcome): void
    {
        [$owner, $staff, , $handover] = $this->placement();
        $this->prepareHandover($handover, $staff);
        $this->travelTo($handover->scheduled_start_at->copy()->addMinute());
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff))
            ->assertSessionHasNoErrors();
        $this->travelTo($handover->scheduled_end_at->copy()->addDay());
        $data = ['outcome' => $outcome];
        if ($outcome === 'received') {
            $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), $data)
                ->assertSessionHasErrors('receipt_proof');
            $data['receipt_proof'] = UploadedFile::fake()->image('receipt.png');
        }
        $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), $data)->assertSessionHasNoErrors();
        $this->assertSame($outcome, $handover->fresh()->adopter_outcome);
        $this->assertDatabaseCount('post_adoption_logs', $outcome === 'received' ? 3 : 0);
    }

    private function placement(): array
    {
        $owner = $this->matchingUser();
        $staff = $this->matchingUser(Role::Administrator);
        $pet = Pet::create(['name' => 'Schedule Test Pet', 'species' => 'Dog', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now()]);
        $handover = Handover::create([
            'code' => 'SCH-'.Str::uuid(), 'application_id' => $application->id, 'user_id' => $owner->id,
            'pet_id' => $pet->id, 'adopter_name' => $owner->full_name, 'approved_at' => now(), 'history' => [],
        ]);

        return [$owner, $staff, $application, $handover];
    }

    private function slot(int $days = 1): array
    {
        return ['date' => ManilaTime::now()->addDays($days)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00'];
    }

    private function propose(User $staff, Handover $handover, string $method = 'delivery', int $days = 1): void
    {
        $this->actingAs($staff)->post(route('admin.handover.schedule', $handover), [
            'method' => $method, 'schedule_version' => $handover->refresh()->schedule_version, ...$this->slot($days),
        ])->assertSessionHasNoErrors();
        $handover->refresh();
    }

    private function confirm(User $owner, Handover $handover): void
    {
        $this->actingAs($owner)->post(route('adopter.handover.schedule.confirm', $handover), [
            'schedule_version' => $handover->refresh()->schedule_version,
        ])->assertSessionHasNoErrors();
        $handover->refresh();
    }

    private function releaseData(User $staff, string $method = 'delivery'): array
    {
        return [
            'release_method' => $method, 'release_date' => ManilaTime::now()->toDateString(),
            'release_time' => ManilaTime::now()->format('H:i'), 'staff_id' => $staff->id,
            'courier_provider' => 'pet_to_go_express', 'tracking_number' => 'SYNTHETIC-REF',
            'tracking_url' => 'https://example.test/synthetic-tracking',
        ];
    }

    private function snapshot(Handover $handover): array
    {
        return [
            $handover->fresh()->getAttributes(),
            AuditLog::where('entity_name', 'Handover')->where('entity_id', $handover->id)->count(),
            HandoverNotification::count(),
        ];
    }

    private function staffNoticeCount(Handover $handover, string $kind): int
    {
        return HandoverNotification::where('kind', 'handover_'.$kind)->where('entity_id', $handover->id)->count();
    }
}
