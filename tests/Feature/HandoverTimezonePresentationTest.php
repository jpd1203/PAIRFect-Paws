<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\HandoverNotification;
use App\Models\Pet;
use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\PreparesVerifiedHandovers;
use Tests\TestCase;

class HandoverTimezonePresentationTest extends TestCase
{
    use BuildsMatchingFixtures, PreparesVerifiedHandovers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Asia/Manila']);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public static function boundaries(): array
    {
        return [
            'before Manila midnight' => ['2026-11-12 15:59:00', '2026-11-12', '23:59', 'Nov 12, 2026 · 11:59 PM'],
            'at Manila midnight' => ['2026-11-12 16:00:00', '2026-11-13', '00:00', 'Nov 13, 2026 · 12:00 AM'],
            'new year in Manila' => ['2026-12-31 16:00:00', '2027-01-01', '00:00', 'Jan 1, 2027 · 12:00 AM'],
            'Manila leap day' => ['2028-02-28 16:00:00', '2028-02-29', '00:00', 'Feb 29, 2028 · 12:00 AM'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_release_defaults_use_one_manila_clock_without_changing_global_timezone(string $utc, string $date, string $time, string $display): void
    {
        $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
        $staff = $this->matchingUser(Role::Administrator);
        $handover = $this->placement();
        $raw = (array) DB::table('handovers')->find($handover->id);
        $response = $this->actingAs($staff)->get(route('admin.handover.show', $handover))->assertOk();

        $this->assertSame($date, $this->inputValue($response->getContent(), 'release-date'));
        $this->assertSame($time, $this->inputValue($response->getContent(), 'release-time'));
        $this->assertSame($display, ManilaTime::now()->format('M j, Y · g:i A'));
        $response->assertSee('Date of release (Asia/Manila)')->assertSee('Time of release (Asia/Manila)');
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame($raw, (array) DB::table('handovers')->find($handover->id));
    }

    #[DataProvider('boundaries')]
    public function test_canonical_timestamps_render_consistently_without_mutating_storage(string $utc, string $date, string $time, string $display): void
    {
        $instant = CarbonImmutable::parse($utc, 'UTC');
        $this->travelTo($instant->addDay());
        $handover = $this->placement([
            'approved_at' => $utc, 'released_at' => $utc, 'release_method' => 'pickup',
            'adopter_outcome' => 'not_received', 'adopter_confirmed_at' => $utc,
            'history' => [['action' => 'Synthetic timezone event', 'by' => 'Synthetic staff', 'at' => $instant->toIso8601String()]],
        ]);
        $raw = (array) DB::table('handovers')->find($handover->id);
        $staff = $this->matchingUser(Role::Administrator);
        $this->actingAs($staff)->get(route('admin.handover.show', $handover))->assertOk()->assertSee($display);
        $this->get(route('admin.handover.index'))->assertOk()->assertSee('Approved '.ManilaTime::format($instant, 'M j, Y'));

        $this->actingAs($handover->user)->get(route('adopter.handover.status', $handover))
            ->assertOk()->assertSee($display)->assertSee('Approved '.ManilaTime::format($instant, 'M j, Y'));
        $this->get(route('adopter.confirm', $handover))->assertOk()->assertSee($display);
        $this->assertSame($raw, (array) DB::table('handovers')->find($handover->id));

        // An already-local object/explicit offset is not shifted by another eight hours.
        $local = $instant->setTimezone('Asia/Manila');
        $this->assertSame($display, ManilaTime::format($local, 'M j, Y · g:i A'));
        $this->assertSame($display, ManilaTime::parseAndFormat($local->toIso8601String(), 'M j, Y · g:i A'));
        $this->assertSame($local->toIso8601String(), $instant->setTimezone('Asia/Manila')->toIso8601String());
    }

    public function test_confirmation_card_and_timeline_agree_at_manila_midnight(): void
    {
        $handover = $this->placement(['released_at' => '2026-12-31 15:00:00', 'adopter_outcome' => 'received',
            'adopter_confirmed_at' => '2026-12-31 16:05:00', 'received_at' => '2026-12-31 16:05:00',
            'history' => [['action' => 'Synthetic receipt', 'by' => 'Synthetic adopter', 'at' => '2026-12-31T16:05:00Z']]]);
        $html = $this->actingAs($this->matchingUser(Role::Administrator))
            ->get(route('admin.handover.show', $handover))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'Jan 1, 2027 · 12:05 AM'));
    }

    public function test_submitted_and_saved_wall_clock_fields_are_not_converted_again(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-31 16:20:00', 'UTC'));
        $staff = $this->matchingUser(Role::Administrator);
        $handover = $this->placement(['release_date' => '2027-01-01', 'release_time' => '00:15',
            'released_at' => '2026-12-31 16:20:00', 'release_method' => 'pickup']);
        $this->actingAs($staff)->get(route('admin.handover.show', $handover))
            ->assertOk()->assertSee('Jan 1, 2027 · 12:15 AM')->assertDontSee('Jan 1, 2027 · 8:15 AM');
        $this->actingAs($handover->user)->get(route('adopter.handover.status', $handover))
            ->assertOk()->assertSee('Jan 1, 2027 · 12:15 AM');
        $this->get(route('adopter.confirm', $handover))->assertOk()->assertSee('Jan 1, 2027 · 12:15 AM');

        $handover->update(['released_at' => null]);
        $html = $this->actingAs($staff)->withSession(['_old_input' => ['release_date' => '2027-01-02', 'release_time' => '23:47']])
            ->get(route('admin.handover.show', $handover))->assertOk()->getContent();
        $this->assertSame('2027-01-02', $this->inputValue($html, 'release-date'));
        $this->assertSame('23:47', $this->inputValue($html, 'release-time'));
    }

    public static function methods(): array
    {
        return [['pickup'], ['delivery']];
    }

    #[DataProvider('methods')]
    public function test_manila_defaults_submit_as_wall_clock_fields_while_release_timestamp_stays_utc(string $method): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-31 16:00:00', 'UTC'));
        $staff = $this->matchingUser(Role::Administrator);
        $handover = $this->placement();
        $this->prepareHandover($handover, $staff, $method);
        $html = $this->actingAs($staff)->get(route('admin.handover.show', $handover))->assertOk()->getContent();
        $payload = ['release_method' => $method, 'release_date' => $this->inputValue($html, 'release-date'),
            'release_time' => $this->inputValue($html, 'release-time'), 'staff_id' => $staff->id];
        if ($method === 'delivery') {
            $payload += ['courier_provider' => 'pet_to_go_express', 'tracking_number' => 'SYNTHETIC-161',
                'tracking_url' => 'https://tracking.example.test/phase161'];
        }
        $this->post(route('admin.handover.release', $handover), $payload)->assertSessionHasNoErrors();
        $raw = (array) DB::table('handovers')->find($handover->id);
        $this->assertSame('2027-01-01', $raw['release_date']);
        $this->assertSame('00:00', $raw['release_time']);
        $this->assertSame('2026-12-31 16:00:00', $raw['released_at']);
        $this->assertTrue($handover->fresh()->scheduled_start_at->isFuture());
    }

    public static function reminderTimes(): array
    {
        return [['2026-12-31T16:05:00Z', 'Jan 1, 12:05 AM'],
            ['2027-01-01T00:05:00+08:00', 'Jan 1, 12:05 AM'], ['invalid timestamp', 'Time not available']];
    }

    #[DataProvider('reminderTimes')]
    public function test_reminder_metadata_is_offset_aware_and_handles_invalid_history(string $at, string $display): void
    {
        $handover = $this->placement(['released_at' => '2026-12-31 15:00:00',
            'reminders' => [['channel' => 'Email', 'at' => $at]]]);
        $this->actingAs($this->matchingUser(Role::Administrator))->get(route('admin.handover.show', $handover))
            ->assertOk()->assertSee('last '.$display);
    }

    public function test_owned_handover_notice_timestamp_uses_manila(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-31 16:05:00', 'UTC'));
        $handover = $this->placement();
        HandoverNotification::create(['handover_id' => $handover->id, 'user_id' => $handover->user_id,
            'kind' => 'prepared', 'title' => 'Synthetic notice', 'body' => 'Fictional QA only', 'channels' => ['In-app']]);
        $this->actingAs($handover->user)->get(route('adopter.handover.notifications', $handover))
            ->assertOk()->assertSee('Jan 1, 12:05 AM');
    }

    private function placement(array $attributes = []): Handover
    {
        $user = $this->matchingUser();
        $pet = Pet::create(['name' => 'Synthetic timezone pet', 'species' => 'Dog', 'age' => 36, 'weight_kg' => '12.000', 'availability_status' => 'Adopted']);
        $application = AdoptionApplication::create(['user_id' => $user->id, 'pet_id' => $pet->id, 'status' => 'Approved']);

        return Handover::create(array_replace(['application_id' => $application->id, 'pet_id' => $pet->id,
            'user_id' => $user->id, 'code' => 'PHASE161', 'adopter_name' => 'Synthetic adopter',
            'adopter_email' => $user->email, 'adopter_address' => '16 Fictional Street, Manila', 'approved_at' => now()], $attributes));
    }

    private function inputValue(string $html, string $id): string
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return (new DOMXPath($dom))->query('//input[@id="'.$id.'"]')->item(0)->getAttribute('value');
    }
}
