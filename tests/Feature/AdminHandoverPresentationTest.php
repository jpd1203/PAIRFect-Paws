<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\HandoverTransportIncident;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\PreparesVerifiedHandovers;
use Tests\TestCase;

class AdminHandoverPresentationTest extends TestCase
{
    use BuildsMatchingFixtures, PreparesVerifiedHandovers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-16 09:00:00', 'Asia/Manila')->utc());
    }

    private function placement(string $method = 'delivery'): Handover
    {
        $owner = $this->matchingUser();
        $staff = $this->matchingUser(Role::Administrator);
        $pet = $this->matchingPet(['name' => 'Synthetic <b>pet</b>']);
        $application = AdoptionApplication::create(['user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => 'Approved', 'adopted_at' => now()]);
        $record = Handover::create(['code' => 'UI-TEST', 'application_id' => $application->id, 'pet_id' => $pet->id,
            'user_id' => $owner->id, 'adopter_name' => 'Synthetic <b>adopter</b>', 'adopter_address' => '123 Synthetic Street',
            'approved_at' => now(), 'history' => []]);
        $this->prepareHandover($record, $staff, $method);
        $this->actingAs($staff);

        return $record->fresh();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function page(Handover $record): string
    {
        return $this->get(route('admin.handover.show', $record))->assertOk()->getContent();
    }

    public function test_confirmed_early_delivery_shows_release_action_and_collapses_completed_forms(): void
    {
        $record = $this->placement();
        $before = $record->getAttributes();
        $counts = [DB::table('audit_logs')->count(), DB::table('handover_email_outbox')->count()];
        $html = $this->page($record);
        $xpath = $this->xpath($html);
        $this->assertStringContainsString('Record the pet&#039;s release.', $html);
        foreach (['scheduleProposal', 'deliveryAddressReview', 'deliveryContactUpdate', 'deliveryVerificationDetails', 'transportInvestigations', 'handoverLog'] as $id) {
            $this->assertSame(1, $xpath->query('//details[@id="'.$id.'" and not(@open)]')->length, $id);
        }
        $this->assertSame(1, $xpath->query('//details[@id="releaseFormSection" and @open]')->length);
        $this->assertSame(1, $xpath->query('//button[@id="markReleased" and not(@disabled)]')->length);
        $this->assertSame(4, $xpath->query('//section[@aria-label="Handover pipeline"]/ol/li')->length);
        $this->assertStringNotContainsString('Ready for courier dispatch', $html);
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertSame($counts, [DB::table('audit_logs')->count(), DB::table('handover_email_outbox')->count()]);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_expired_verifications_never_present_release_as_ready(): void
    {
        $record = $this->placement();
        $record->update(['scheduled_end_at' => now()->subSecond()]);
        $html = $this->page($record);
        $this->assertStringContainsString('Delivery window expired. Propose a new schedule', $html);
        $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//details[@id="scheduleProposal" and @open]')->length);
        $this->assertSame(1, $xpath->query('//button[@id="markReleased" and @disabled]')->length);
        $this->assertStringNotContainsString('Ready for courier dispatch', $html);
    }

    public function test_missing_and_proposed_schedules_show_the_correct_next_step(): void
    {
        $record = $this->placement();
        $record->forceFill(['schedule_status' => 'unscheduled', 'scheduled_start_at' => null, 'scheduled_end_at' => null,
            'schedule_confirmed_at' => null, 'schedule_confirmed_by_user_id' => null])->save();
        $this->assertStringContainsString('Propose a handover schedule.', $this->page($record));
        $record->forceFill(['schedule_status' => 'proposed', 'scheduled_start_at' => now()->addDay(), 'scheduled_end_at' => now()->addDay()->addHour()])->save();
        $this->assertStringContainsString('Awaiting adopter confirmation of the proposed schedule.', $this->page($record));
    }

    public function test_stale_confirmer_is_not_presented_as_a_confirmed_agreement(): void
    {
        $record = $this->placement();
        $record->update(['schedule_confirmed_by_user_id' => auth()->id()]);
        $xpath = $this->xpath($this->page($record));
        $this->assertSame(1, $xpath->query('//button[@id="markReleased" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//details[@id="scheduleProposal" and @open]')->length);
    }

    public function test_pending_reschedule_stays_visible_without_invalidating_a_valid_current_agreement(): void
    {
        $record = $this->placement();
        $record->update(['reschedule_status' => 'pending', 'reschedule_reason' => 'Synthetic new availability',
            'reschedule_options' => [['date' => now()->addDays(2)->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00']]]);
        $html = $this->page($record);
        $this->assertStringContainsString('Review reschedule request', $html);
        $this->assertStringContainsString('id="rescheduleReviewSection"', $html);
        $this->assertSame(1, $this->xpath($html)->query('//button[@id="markReleased" and not(@disabled)]')->length);
    }

    public function test_missing_destination_and_availability_forms_are_open(): void
    {
        $record = $this->placement();
        $record->forceFill(['delivery_address_status' => 'needs_review', 'delivery_recipient_available' => null])->save();
        $html = $this->page($record);
        $this->assertStringContainsString('Verify the delivery destination.', $html);
        $xpath = $this->xpath($html);
        foreach (['deliveryAddressReview', 'deliveryContactUpdate'] as $id) {
            $this->assertSame(1, $xpath->query('//details[@id="'.$id.'" and @open]')->length);
        }
    }

    public function test_unavailable_adopter_is_visible_and_not_release_ready(): void
    {
        $record = $this->placement();
        $record->forceFill(['delivery_recipient_available' => false])->save();
        $html = $this->page($record);
        $this->assertStringContainsString('Adopter unavailable.', $html);
        $this->assertSame(1, $this->xpath($html)->query('//button[@id="markReleased" and @disabled]')->length);
    }

    public function test_pickup_identity_is_required_and_completed_pickup_preserves_early_release(): void
    {
        $record = $this->placement('pickup');
        $html = $this->page($record);
        $this->assertStringNotContainsString('id="deliveryAddressReview"', $html);
        $this->assertSame(1, $this->xpath($html)->query('//button[@id="markReleased" and not(@disabled)]')->length);
        DB::table('identity_verifications')->where('application_id', $record->application_id)->where('stage', 'pickup_handover')->delete();
        $html = $this->page($record);
        $this->assertStringContainsString('Complete final pickup identity verification.', $html);
        $this->assertSame(1, $this->xpath($html)->query('//details[@id="pickupIdentityDetails" and @open]')->length);
        $this->assertSame(1, $this->xpath($html)->query('//button[@id="markReleased" and @disabled]')->length);
    }

    public function test_released_and_received_states_keep_receipt_separate_from_release(): void
    {
        $record = $this->placement();
        $record->update(['released_at' => now(), 'release_method' => 'delivery']);
        $html = $this->page($record);
        $this->assertStringContainsString('Awaiting adopter receipt confirmation.', $html);
        $this->assertStringContainsString('id="adopterConfirmationSection"', $html);
        $this->assertStringNotContainsString('id="handoverForm"', $html);
        $record->update(['adopter_outcome' => 'received', 'received_at' => now(), 'adopter_confirmed_at' => now()]);
        $this->assertStringContainsString('Post-adoption monitoring is active.', $this->page($record));
    }

    public function test_resolved_cases_are_not_labeled_active_and_open_cases_are_prioritized(): void
    {
        $record = $this->placement();
        $case = (new HandoverTransportIncident)->forceFill(['handover_id' => $record->id, 'release_attempt' => 0,
            'source' => 'staff_report', 'category' => 'delayed_delivery', 'status' => 'resolved', 'description' => 'Synthetic resolved concern', 'reported_at' => now(),
            'resolved_at' => now(), 'resolution_outcome' => 'other_documented_resolution', 'resolution_notes' => 'Synthetic closure', 'release_context' => [], 'history' => []]);
        $case->save();
        $html = $this->page($record);
        $this->assertStringNotContainsString('Active Investigations', $html);
        $this->assertStringContainsString('No active issues', $html);
        $case->forceFill(['status' => 'open', 'next_follow_up_at' => now()->subMinute()])->save();
        $html = $this->page($record);
        $this->assertStringContainsString('Review the transport concern', $html);
        $this->assertStringContainsString('Overdue follow-up', $html);
        $this->assertSame(1, $this->xpath($html)->query('//details[@id="transportInvestigations" and @open]')->length);
    }

    public function test_validation_errors_expose_release_form_without_weakening_validation(): void
    {
        $record = $this->placement();
        $this->withSession([]);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->from(route('admin.handover.show', $record))->post(route('admin.handover.release', $record), [
            'release_method' => 'delivery', 'release_date' => 'not-a-date', 'release_time' => 'bad',
            'staff_id' => auth()->id(), '_ui_form' => 'handoverForm',
        ])->assertSessionHasErrors(['release_date', 'courier_provider', 'tracking_number', 'tracking_url']);
        $html = $this->page($record);
        $this->assertSame(1, $this->xpath($html)->query('//details[@id="releaseFormSection" and @open]')->length);
        $this->assertStringContainsString('data-failed-form="handoverForm"', $html);
        $this->assertNull($record->fresh()->released_at);
    }

    public function test_pet_names_and_metadata_are_escaped_and_history_controls_are_native(): void
    {
        $record = $this->placement();
        $html = $this->page($record);
        $this->assertStringContainsString('Synthetic &lt;b&gt;pet&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('Synthetic <b>pet</b>', $html);
        $this->assertStringContainsString('Synthetic &lt;b&gt;adopter&lt;/b&gt;', $html);
        $this->assertSame(1, $this->xpath($html)->query('//details[@id="handoverLog"]/summary')->length);
    }
}
